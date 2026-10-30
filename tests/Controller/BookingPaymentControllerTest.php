<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\BasicFixtures;
use App\DataFixtures\BookingFixtures;
use App\DataFixtures\BookingWithOutAmountFixture;
use App\DataFixtures\BookingWithOutInvoiceFixture;
use App\DataFixtures\BookingWithPaymentFixture;
use App\DataFixtures\HalfDayPriceFixtures;
use App\DataFixtures\PriceFixtures;
use App\DataFixtures\VoucherFixtures;
use App\DataFixtures\Test\BookingWithInvoiceFixture;
use App\Entity\Booking;
use App\Entity\BookingType;
use App\Entity\Payment;
use App\Entity\User;
use App\Entity\VoucherType;
use App\Manager\BookingManager;
use App\Repository\BookingRepository;
use App\Repository\BookingTypeRepository;
use App\Repository\BusinessDayRepository;
use App\Repository\InvoiceRepository;
use App\Repository\PriceRepository;
use App\Repository\RoomRepository;
use App\Repository\UserRepository;
use App\Repository\VoucherRepository;
use App\Repository\VoucherTypeRepository;
use App\Service\InvoiceGenerator;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;

class BookingPaymentControllerTest extends WebTestCase
{
    use ClockSensitiveTrait;

    public const FAKE_UUID = 'hyf-5678-hnbgyu';

    public function testStepPaymentLogsErrorAndRedirectsWhenBookingIsNotFound(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BasicFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($testUser);

        $uri = '/booking/' . self::FAKE_UUID . '/payment';
        $client->request('GET', $uri);

        static::assertResponseRedirects('/booking');
        $logger = static::getContainer()->get('monolog.logger');
        self::assertNotNull($logger);
        $testHandler = null;

        foreach ($logger->getHandlers() as $handler) {
            if ($handler instanceof TestHandler) {
                $testHandler = $handler;
            }
        }
        self::assertNotNull($testHandler);
        self::assertTrue($testHandler->hasRecordThatContains(
            'Booking not found.',
            Level::fromName('error')
        ));
        self::assertTrue($testHandler->hasRecordThatContains(
            self::FAKE_UUID,
            Level::fromName('error')
        ));
    }

    public function testStepPaymentChecksIfBookingUserIsConnectedUser(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithOutAmountFixture::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $bookingUser = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $date        = new \DateTimeImmutable(BookingWithOutAmountFixture::BUSINESS_DAY_DATE);
        $booking     = $this->getBooking($bookingUser, $date);

        $uri = '/booking/' . $booking->getUuid() . '/payment';
        $client->request('GET', $uri);

        static::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testStepPaymentRendersTemplateOnGet(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithOutAmountFixture::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable(BookingWithOutAmountFixture::BUSINESS_DAY_DATE);
        $booking = $this->getBooking($bookingUser, $date);

        $uri     = '/booking/' . $booking->getUuid() . '/payment';
        $crawler = $client->request('GET', $uri);

        static::assertResponseIsSuccessful();
    }

    public function testStepPaymentTemplateContainsPaymentMethodForm(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithOutAmountFixture::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable(BookingWithOutAmountFixture::BUSINESS_DAY_DATE);
        $booking = $this->getBooking($bookingUser, $date);

        $uri     = '/booking/' . $booking->getUuid() . '/payment';
        $crawler = $client->request('GET', $uri);

        static::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('form'));
    }

    public function testStepPaymentFormSubmitErrorWithInvalidCsrfToken(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithOutAmountFixture::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable(BookingWithOutAmountFixture::BUSINESS_DAY_DATE);
        $booking = $this->getBooking($bookingUser, $date);
        $uri     = '/booking/' . $booking->getUuid() . '/payment';
        $crawler = $client->request('GET', $uri);

        $form = $crawler->filter('form')->form();
        $form->getPhpValues();
        $form->setValues(['token' => 'invalid']);
        $client->submit($form);

        static::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        static::assertSelectorTextContains('section#flash-messages', 'Ungültiges CSRF-Token.');
    }

    public function testStepPaymentFormSubmitWithoutPriceId(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithOutAmountFixture::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable(BookingWithOutAmountFixture::BUSINESS_DAY_DATE);
        $booking = $this->getBooking($bookingUser, $date);

        $uri     = '/booking/' . $booking->getUuid() . '/payment';
        $crawler = $client->request('GET', $uri);
        $form    = $crawler->filter('form')->form();
        $form->disableValidation();
        unset($form['priceId']);
        $client->submit($form);

        static::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        static::assertSelectorTextContains('section#flash-messages', 'Bitte ein Preis auswählen.');
    }

    public function testStepPaymentFormSubmitWithInvalidPriceId(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithOutAmountFixture::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable(BookingWithOutAmountFixture::BUSINESS_DAY_DATE);
        $booking = $this->getBooking($bookingUser, $date);

        $uri     = '/booking/' . $booking->getUuid() . '/payment';
        $crawler = $client->request('GET', $uri);
        $form    = $crawler->filter('form')->form();
        $form->disableValidation();
        $form->setValues(['priceId' => '999999']);
        $client->submit($form);

        static::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        static::assertSelectorTextContains('section#flash-messages', 'Der ausgewählte Preis konnte nicht gefunden werden.');
    }

    public function testStepPaymentFormSubmitWithMissingPaymentMethod(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithOutAmountFixture::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable(BookingWithOutAmountFixture::BUSINESS_DAY_DATE);
        $booking = $this->getBooking($bookingUser, $date);

        $uri     = '/booking/' . $booking->getUuid() . '/payment';
        $crawler = $client->request('GET', $uri);
        $form    = $crawler->filter('form')->form();
        $form->disableValidation();
        unset($form['paymentMethod']);
        $client->submit($form);

        static::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        static::assertSelectorTextContains('section#flash-messages', 'Bitte eine Zahlungsmethode auswählen.');
    }

    public function testStepPaymentFormSubmitWithInvalidPaymentMethod(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithOutAmountFixture::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable(BookingWithOutAmountFixture::BUSINESS_DAY_DATE);
        $booking = $this->getBooking($bookingUser, $date);

        $uri     = '/booking/' . $booking->getUuid() . '/payment';
        $crawler = $client->request('GET', $uri);
        $form    = $crawler->filter('form')->form();
        $form->disableValidation();
        $form['paymentMethod'] = 'creditCard';
        $client->submit($form);

        static::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        static::assertSelectorTextContains('section#flash-messages', 'Die ausgewählte Zahlungsmethode konnte nicht gefunden werden.');
    }

    public function testStepPaymentFormSubmitWithPaymentMethodInvoiceAddsAmountToBookingAndGeneratesInvoiceAndRedirects(): void
    {
        $this->mockTime(new \DateTimeImmutable('2024-02-20'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithOutAmountFixture::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable(BookingWithOutAmountFixture::BUSINESS_DAY_DATE);
        $booking = $this->getBooking($bookingUser, $date);
        self::assertNull($booking->getAmount());

        $uri                   = '/booking/' . $booking->getUuid() . '/payment';
        $crawler               = $client->request('GET', $uri);
        $form                  = $crawler->filter('form')->form();
        $form['paymentMethod'] = 'invoice';
        $client->submit($form);

        $booking = static::getContainer()->get(BookingRepository::class)->findOneBy(['uuid' => $booking->getUuid()]);
        self::assertNotNull($booking->getAmount());
        self::assertNotNull($booking->getInvoice());
        self::assertSame($booking->getAmount(), $booking->getInvoice()->getAmount());

        $invoiceGenerator = static::getContainer()->get('App\Service\InvoiceGenerator');
        $filePath         = $invoiceGenerator->getTargetDirectory($booking->getInvoice());
        self::assertFileExists($filePath);

        static::assertResponseRedirects('/booking/' . $booking->getUuid() . '/payment/confirmation');
    }

    public function testStepPaymentFormSubmitWithPaymentMethodInvoiceSendsInvoiceByMailAndRedirects(): void
    {
        $this->mockTime(new \DateTimeImmutable('2024-02-20'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithOutInvoiceFixture::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable(BookingWithOutInvoiceFixture::BUSINESS_DAY_DATE);
        $booking = $this->getBooking($bookingUser, $date);
        self::assertNull($booking->getInvoice());

        $uri                   = '/booking/' . $booking->getUuid() . '/payment';
        $crawler               = $client->request('GET', $uri);
        $form                  = $crawler->filter('form')->form();
        $form['paymentMethod'] = 'invoice';
        $client->submit($form);

        static::assertEmailCount(2);

        $email = $this->getMailerMessage();
        static::assertEmailAttachmentCount($email, 1);

        $userInvoiceEmail = $this->getMailerMessage(1);
        static::assertEmailHtmlBodyContains($userInvoiceEmail, 'Widerrufsbelehrung');
        static::assertEmailHtmlBodyContains($userInvoiceEmail, '/vertrag-widerrufen?contract=');
        static::assertResponseRedirects('/booking/' . $booking->getUuid() . '/payment/confirmation');
    }

    public function testStepPaymentFormSubmitWithPaymentMethodPayPalAddsRedirects(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithInvoiceFixture::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable(BookingWithInvoiceFixture::BOOKING_DATE);
        $booking = $this->getBooking($bookingUser, $date);

        $uri                   = '/booking/' . $booking->getUuid() . '/payment';
        $crawler               = $client->request('GET', $uri);
        $form                  = $crawler->filter('form')->form();
        $form['paymentMethod'] = 'paypal';
        $client->submit($form);

        static::assertResponseRedirects('/invoice/' . $booking->getInvoice()->getUuid() . '/paypal');
    }

    public function testStepPaymentFormSubmitWithPaymentMethodVoucherRedirects(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithInvoiceFixture::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable(BookingWithInvoiceFixture::BOOKING_DATE);
        $booking = $this->getBooking($bookingUser, $date);

        $uri                   = '/booking/' . $booking->getUuid() . '/payment';
        $crawler               = $client->request('GET', $uri);
        $form                  = $crawler->filter('form')->form();
        $form['paymentMethod'] = 'voucher';
        $client->submit($form);

        static::assertResponseRedirects('/booking/' . $booking->getUuid() . '/payment/voucher');
    }

    public function testPayWithVoucherLogsErrorAndRedirectsWhenBookingIsNotFound(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($testUser);

        $uri = '/booking/hyf-5678-hnbgyu/payment/voucher';
        $client->request('GET', $uri);

        static::assertResponseRedirects('/booking');
        $logger = static::getContainer()->get('monolog.logger');
        self::assertNotNull($logger);

        $testHandler = null;
        foreach ($logger->getHandlers() as $handler) {
            if ($handler instanceof TestHandler) {
                $testHandler = $handler;
            }
        }
        self::assertNotNull($testHandler);
        self::assertTrue($testHandler->hasRecordThatContains(
            'Booking not found.',
            Level::fromName('error')
        ));
        self::assertTrue($testHandler->hasRecordThatContains(
            'hyf-5678-hnbgyu',
            Level::fromName('error')
        ));
    }

    public function testPayWithVoucherChecksIfBookingUserIsConnectedUser(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithInvoiceFixture::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $bookingUser = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $date        = new \DateTimeImmutable(BookingWithInvoiceFixture::BOOKING_DATE);
        $booking     = $this->getBooking($bookingUser, $date);
        $uri         = '/booking/' . $booking->getUuid() . '/payment/voucher';
        $client->request('GET', $uri);

        static::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testPayWithVoucherRedirectsWhenBookingHasNoAmount(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithOutAmountFixture::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable(BookingWithOutAmountFixture::BUSINESS_DAY_DATE);
        $booking = $this->getBooking($bookingUser, $date);
        $uri     = '/booking/' . $booking->getUuid() . '/payment/voucher';
        $client->request('GET', $uri);

        static::assertResponseRedirects('/booking/' . $booking->getUuid() . '/payment');
    }

    public function testPayWithVoucherRedirectsWhenBookingIsAlreadyPaid(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithPaymentFixture::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable(BookingWithPaymentFixture::BUSINESS_DAY_DATE);
        $booking = $this->getBooking($bookingUser, $date);
        $uri     = '/booking/' . $booking->getUuid() . '/payment/voucher';
        $client->request('GET', $uri);

        static::assertResponseRedirects('/booking/' . $booking->getUuid() . '/payment/confirmation');
    }

    public function testPayWithVoucherRendersTemplateOnGet(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithOutInvoiceFixture::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable(BookingWithOutInvoiceFixture::BUSINESS_DAY_DATE);
        $booking = $this->getBooking($bookingUser, $date);
        $uri     = '/booking/' . $booking->getUuid() . '/payment/voucher';
        $crawler = $client->request('GET', $uri);

        static::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('form'));
    }

    public function testPayWithVoucherFormSubmitErrorWithInvalidCsrfToken(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithOutInvoiceFixture::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable(BookingWithOutInvoiceFixture::BUSINESS_DAY_DATE);
        $booking = $this->getBooking($bookingUser, $date);
        $uri     = '/booking/' . $booking->getUuid() . '/payment/voucher';

        $crawler = $client->request('GET', $uri);

        $form = $crawler->filter('form')->form();
        $form->getPhpValues();
        $form->setValues(['token' => 'invalid']);
        $client->submit($form);

        static::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        static::assertSelectorTextContains('section#flash-messages', 'Ungültiges CSRF-Token.');
    }

    public function testPayWithVoucherFormSubmitErrorWithoutVoucherCode(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithOutInvoiceFixture::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable(BookingWithOutInvoiceFixture::BUSINESS_DAY_DATE);
        $booking = $this->getBooking($bookingUser, $date);
        $uri     = '/booking/' . $booking->getUuid() . '/payment/voucher';

        $crawler = $client->request('GET', $uri);

        $form = $crawler->filter('form')->form();
        $form->disableValidation();
        unset($form['voucher']);
        $client->submit($form);

        static::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        static::assertSelectorTextContains('section#flash-messages', 'Bitte einen Gutscheincode eingeben.');
    }

    public function testPayWithVoucherFormSubmitErrorWithInvalidVoucherCode(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithOutInvoiceFixture::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable(BookingWithOutInvoiceFixture::BUSINESS_DAY_DATE);
        $booking = $this->getBooking($bookingUser, $date);
        $uri     = '/booking/' . $booking->getUuid() . '/payment/voucher';

        $crawler = $client->request('GET', $uri);

        $form = $crawler->filter('form')->form();
        $form->setValues(['voucher' => 'invalid']);
        $client->submit($form);

        static::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        static::assertSelectorTextContains('section#flash-messages', 'Ungültiger Gutscheincode.');
    }

    public function testPayWithVoucherFormSubmitErrorWhenVoucherIsNotValidForUser(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithOutInvoiceFixture::class, VoucherFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $adminVoucher = static::getContainer()->get(VoucherRepository::class)->findOneBy(['code' => VoucherFixtures::ADMIN_VOUCHER_CODE]);
        $date         = new \DateTimeImmutable(BookingWithOutInvoiceFixture::BUSINESS_DAY_DATE);
        $booking      = $this->getBooking($bookingUser, $date);
        $uri          = '/booking/' . $booking->getUuid() . '/payment/voucher';
        $crawler      = $client->request('GET', $uri);
        $form         = $crawler->filter('form')->form();
        $form->setValues(['voucher' => $adminVoucher->getCode()]);
        $client->submit($form);

        static::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        static::assertSelectorTextContains('section#flash-messages', 'Gutscheincode kann nicht für diesen Benutzer verwendet werden.');
    }

    public function testPayWithVoucherFormSubmitErrorWhenVoucherIsExpired(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithOutInvoiceFixture::class, VoucherFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable(BookingWithOutInvoiceFixture::BUSINESS_DAY_DATE);
        $booking = $this->getBooking($bookingUser, $date);
        $uri     = '/booking/' . $booking->getUuid() . '/payment/voucher';
        $crawler = $client->request('GET', $uri);
        $form    = $crawler->filter('form')->form();
        $form->setValues(['voucher' => VoucherFixtures::EXPIRED_VOUCHER_CODE]);
        $client->submit($form);

        static::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        static::assertSelectorTextContains('section#flash-messages', 'Gutscheincode ist abgelaufen.');
    }

    public function testPayWithVoucherFormSubmitErrorWhenVoucherHasAlreadyBeenUsed(): void
    {
        $this->mockTime(new \DateTimeImmutable('2024-04-04'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithOutInvoiceFixture::class, VoucherFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);
        $voucher = static::getContainer()->get(VoucherRepository::class)->findOneBy(['code' => VoucherFixtures::ALREADY_USED_VOUCHER_CODE]);
        self::assertNotNull($voucher);

        $date    = new \DateTimeImmutable(BookingWithOutInvoiceFixture::BUSINESS_DAY_DATE);
        $booking = $this->getBooking($bookingUser, $date);
        $uri     = '/booking/' . $booking->getUuid() . '/payment/voucher';
        $crawler = $client->request('GET', $uri);
        $form    = $crawler->filter('form')->form();
        $form->setValues(['voucher' => $voucher->getCode()]);
        $client->submit($form);

        static::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        static::assertSelectorTextContains('section#flash-messages', 'Gutscheincode wurde bereits am 14.03.2024 verwendet.');
    }

    public function testPayWithVoucherFormSubmitErrorWhenVoucherHasNotBeenPaidFor(): void
    {
        $this->mockTime(new \DateTimeImmutable('2024-04-04'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([VoucherFixtures::class, BookingFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable(BookingFixtures::BOOKING_STANDARD_DATE);
        $booking = $this->getBooking($bookingUser, $date);
        $uri     = '/booking/' . $booking->getUuid() . '/payment/voucher';
        $crawler = $client->request('GET', $uri);
        $form    = $crawler->filter('form')->form();
        $form->setValues(['voucher' => VoucherFixtures::VOUCHER_WITHOUT_PAYMENT_CODE]);
        $client->submit($form);

        static::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        static::assertSelectorTextContains('section#flash-messages', 'Gutscheincode wurde noch nicht bezahlt und kann somit noch nicht genutzt werden.');
    }

    public function testPayWithVoucherFormSubmitWithValidVoucherRedirects(): void
    {
        $this->mockTime(new \DateTimeImmutable('2024-04-04'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithInvoiceFixture::class, VoucherFixtures::class, BookingFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $voucher = $bookingUser->getValidVouchers()->first();

        $date    = new \DateTimeImmutable(BookingWithInvoiceFixture::BOOKING_DATE);
        $booking = $this->getBooking($bookingUser, $date);
        $uri     = '/booking/' . $booking->getUuid() . '/payment/voucher';
        $crawler = $client->request('GET', $uri);
        $form    = $crawler->filter('form')->form();
        $form->setValues(['voucher' => $voucher->getCode()]);
        $client->submit($form);

        static::assertResponseRedirects('/booking/' . $booking->getUuid() . '/payment/confirmation');
    }

    public function testPayWithVoucherFormSubmitWithValidVoucherCreatesInvoicePdf(): void
    {
        $this->mockTime(new \DateTimeImmutable('2024-04-04'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithInvoiceFixture::class, VoucherFixtures::class, BookingFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $voucher = $bookingUser->getValidVouchers()->first();

        $date    = new \DateTimeImmutable(BookingWithInvoiceFixture::BOOKING_DATE);
        $booking = $this->getBooking($bookingUser, $date);
        $uri     = '/booking/' . $booking->getUuid() . '/payment/voucher';
        $crawler = $client->request('GET', $uri);
        $form    = $crawler->filter('form')->form();
        $form->setValues(['voucher' => $voucher->getCode()]);
        $client->submit($form);

        $invoiceGenerator = static::getContainer()->get(InvoiceGenerator::class);
        $filePath         = $invoiceGenerator->getTargetDirectory($booking->getInvoice());
        self::assertFileExists($filePath);
    }

    public function testPayWithVoucherFormSubmitWithValidVoucherCreatesVoucherPayment(): void
    {
        $this->mockTime(new \DateTimeImmutable('2024-04-04'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithInvoiceFixture::class, VoucherFixtures::class, BookingFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $voucher = $bookingUser->getValidVouchers()->first();

        $date    = new \DateTimeImmutable(BookingWithInvoiceFixture::BOOKING_DATE);
        $booking = $this->getBooking($bookingUser, $date);
        $uri     = '/booking/' . $booking->getUuid() . '/payment/voucher';
        $crawler = $client->request('GET', $uri);
        $form    = $crawler->filter('form')->form();
        $form->setValues(['voucher' => $voucher->getCode()]);
        $client->submit($form);

        $invoice = static::getContainer()
                         ->get(InvoiceRepository::class)
                         ->findInvoiceForBookingAndUserAndPaymentType($booking->getId(), $bookingUser->getId(), Payment::PAYMENT_TYPE_VOUCHER);

        self::assertNotNull($invoice);
        self::assertCount(1, $invoice->getPayments());
        self::assertSame($voucher->getId(), $invoice->getPayments()->first()->getVoucher()->getId());
    }

    public function testPayWithVoucherFormSubmitWithValidVoucherSendsInvoiceToClient(): void
    {
        $this->mockTime(new \DateTimeImmutable('2024-04-04'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithInvoiceFixture::class, VoucherFixtures::class, BookingFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $voucher = $bookingUser->getValidVouchers()->first();
        $date    = new \DateTimeImmutable(BookingWithInvoiceFixture::BOOKING_DATE);
        $booking = $this->getBooking($bookingUser, $date);
        $uri     = '/booking/' . $booking->getUuid() . '/payment/voucher';
        $crawler = $client->request('GET', $uri);
        $form    = $crawler->filter('form')->form();
        $form->setValues(['voucher' => $voucher->getCode()]);
        $client->submit($form);

        static::assertEmailCount(1);

        $email = $this->getMailerMessage();
        static::assertEmailAttachmentCount($email, 1);
    }

    public function testPaymentConfirmationLogsErrorAndRedirectsWhenBookingIsNotFound(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($testUser);

        $uri = '/booking/' . self::FAKE_UUID . '/payment/confirmation';
        $client->request('GET', $uri);

        static::assertResponseRedirects('/booking');
        $logger = static::getContainer()->get('monolog.logger');
        self::assertNotNull($logger);
        $testHandler = null;

        foreach ($logger->getHandlers() as $handler) {
            if ($handler instanceof TestHandler) {
                $testHandler = $handler;
            }
        }
        self::assertNotNull($testHandler);
        self::assertTrue($testHandler->hasRecordThatContains(
            'Booking not found.',
            Level::fromName('error')
        ));
        self::assertTrue($testHandler->hasRecordThatContains(
            self::FAKE_UUID,
            Level::fromName('error')
        ));
    }

    public function testPaymentConfirmationChecksIfBookingUserIsConnectedUser(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithInvoiceFixture::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $bookingUser = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $date        = new \DateTimeImmutable(BookingWithInvoiceFixture::BOOKING_DATE);
        $booking     = $this->getBooking($bookingUser, $date);
        $uri         = '/booking/' . $booking->getUuid() . '/payment/confirmation';
        $client->request('GET', $uri);

        static::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testPaymentConfirmationRedirectsIfNoInvoice(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithOutInvoiceFixture::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable(BookingWithOutInvoiceFixture::BUSINESS_DAY_DATE);
        $booking = $this->getBooking($bookingUser, $date);
        $uri     = '/booking/' . $booking->getUuid() . '/payment/confirmation';
        $client->request('GET', $uri);

        static::assertResponseRedirects('/booking/' . $booking->getUuid() . '/payment');
    }

    public function testPaymentConfirmationRendersTemplate(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithPaymentFixture::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $invoice = static::getContainer()->get(InvoiceRepository::class)->findOneBy(['number' => BookingWithPaymentFixture::INVOICE_NUMBER]);
        $uri     = '/booking/' . $invoice->getBookings()->first()->getUuid() . '/payment/confirmation';
        $crawler = $client->request('GET', $uri);
        static::assertResponseIsSuccessful();

        $expectedUri = '/invoice/' . $invoice->getUuid() . '/download';
        $link        = $crawler->filter('section button a')->first();
        self::assertSame($expectedUri, $link->attr('href'));
    }

    public function testStepPaymentOffersThePriceOfTheBookingType(): void
    {
        $this->mockTime(new \DateTimeImmutable('2024-04-04'));
        $client = static::createClient();
        static::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([BasicFixtures::class, HalfDayPriceFixtures::class]);

        $bookingUser = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $halfDayBooking = $this->createBookingOfType($bookingUser, BookingType::NAME_HALF_DAY, '2024-04-22');
        $fullDayBooking = $this->createBookingOfType($bookingUser, BookingType::NAME_FULL_DAY, '2024-04-23');

        $halfDayPrice = static::getContainer()->get(PriceRepository::class)->findOneBy(['amount' => PriceFixtures::HALF_DAY_SINGLE_PRICE_AMOUNT]);
        $fullDayPrice = static::getContainer()->get(PriceRepository::class)->findOneBy(['amount' => PriceFixtures::SINGLE_PRICE_AMOUNT]);

        $crawler = $client->request('GET', '/booking/' . $halfDayBooking->getUuid() . '/payment');
        static::assertResponseIsSuccessful();
        self::assertSame((string) $halfDayPrice->getId(), $crawler->filter('input[name="priceId"]')->attr('value'));

        $crawler = $client->request('GET', '/booking/' . $fullDayBooking->getUuid() . '/payment');
        static::assertResponseIsSuccessful();
        self::assertSame((string) $fullDayPrice->getId(), $crawler->filter('input[name="priceId"]')->attr('value'));
    }

    public function testStepPaymentFormSubmitRejectsPriceOfAnotherBookingType(): void
    {
        $this->mockTime(new \DateTimeImmutable('2024-04-04'));
        $client = static::createClient();
        static::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([BasicFixtures::class, HalfDayPriceFixtures::class]);

        $bookingUser = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $fullDayBooking = $this->createBookingOfType($bookingUser, BookingType::NAME_FULL_DAY, '2024-04-22');
        $halfDayPrice   = static::getContainer()->get(PriceRepository::class)->findOneBy(['amount' => PriceFixtures::HALF_DAY_SINGLE_PRICE_AMOUNT]);

        $crawler = $client->request('GET', '/booking/' . $fullDayBooking->getUuid() . '/payment');
        $form    = $crawler->filter('form')->form();
        $form->disableValidation();
        $form->setValues(['priceId' => (string) $halfDayPrice->getId()]);
        $client->submit($form);

        static::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        static::assertSelectorTextContains('section#flash-messages', 'Der ausgewählte Preis konnte nicht gefunden werden.');
        self::assertNull($fullDayBooking->getAmount());
    }

    public function testPayWithVoucherFormSubmitErrorWhenVoucherIsForAnotherBookingType(): void
    {
        $this->mockTime(new \DateTimeImmutable('2024-04-04'));
        $client = static::createClient();
        static::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([VoucherFixtures::class, HalfDayPriceFixtures::class]);

        $bookingUser = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $voucher = $bookingUser->getValidVouchers()->first();
        $booking = $this->createBookingOfType($bookingUser, BookingType::NAME_HALF_DAY, '2024-04-22', PriceFixtures::HALF_DAY_SINGLE_PRICE_AMOUNT);

        $crawler = $client->request('GET', '/booking/' . $booking->getUuid() . '/payment/voucher');
        $form    = $crawler->filter('form')->form();
        $form->setValues(['voucher' => $voucher->getCode()]);
        $client->submit($form);

        static::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        static::assertSelectorTextContains('section#flash-messages', 'Dieser Gutscheincode gilt nur für Buchungen vom Typ Ganztag.');
    }

    public function testPayWithVoucherFormSubmitAcceptsVoucherOfTheSameBookingType(): void
    {
        $this->mockTime(new \DateTimeImmutable('2024-04-04'));
        $client = static::createClient();
        static::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([VoucherFixtures::class, HalfDayPriceFixtures::class]);

        $bookingUser = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $halfDayType = static::getContainer()->get(BookingTypeRepository::class)->findOneBy(['name' => BookingType::NAME_HALF_DAY]);
        $voucher     = $bookingUser->getValidVouchers()->first();
        $voucher->setVoucherType(static::getContainer()->get(VoucherTypeRepository::class)->findOneBy(['bookingType' => $halfDayType]));
        static::getContainer()->get('doctrine.orm.entity_manager')->flush();

        $booking = $this->createBookingOfType($bookingUser, BookingType::NAME_HALF_DAY, '2024-04-22', PriceFixtures::HALF_DAY_SINGLE_PRICE_AMOUNT);

        $crawler = $client->request('GET', '/booking/' . $booking->getUuid() . '/payment/voucher');
        $form    = $crawler->filter('form')->form();
        $form->setValues(['voucher' => $voucher->getCode()]);
        $client->submit($form);

        static::assertResponseRedirects('/booking/' . $booking->getUuid() . '/payment');
    }

    public function testPayWithVoucherFormSubmitAcceptsVoucherWithoutBookingTypeForAnyBookingType(): void
    {
        $this->mockTime(new \DateTimeImmutable('2024-04-04'));
        $client = static::createClient();
        static::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([VoucherFixtures::class, HalfDayPriceFixtures::class]);

        $bookingUser = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $voucher = $bookingUser->getValidVouchers()->first();
        $voucher->setVoucherType(static::getContainer()->get(VoucherTypeRepository::class)->findOneBy(['name' => VoucherType::NAME_REFUND]));
        static::getContainer()->get('doctrine.orm.entity_manager')->flush();

        $booking = $this->createBookingOfType($bookingUser, BookingType::NAME_HALF_DAY, '2024-04-22', PriceFixtures::HALF_DAY_SINGLE_PRICE_AMOUNT);

        $crawler = $client->request('GET', '/booking/' . $booking->getUuid() . '/payment/voucher');
        $form    = $crawler->filter('form')->form();
        $form->setValues(['voucher' => $voucher->getCode()]);
        $client->submit($form);

        static::assertResponseRedirects('/booking/' . $booking->getUuid() . '/payment');
    }

    private function createBookingOfType(User $user, string $typeName, string $date, ?int $amount = null): Booking
    {
        $container   = static::getContainer();
        $businessDay = $container->get(BusinessDayRepository::class)->findOneBy(['date' => new \DateTimeImmutable($date)]);
        $room        = $container->get(RoomRepository::class)->findOneBy(['name' => BasicFixtures::ROOM_FOR_BOOKINGS]);
        $type        = $container->get(BookingTypeRepository::class)->findOneBy(['name' => $typeName]);

        $booking = $container->get(BookingManager::class)->saveBooking($user, $businessDay, $room, $type);
        if (null !== $amount) {
            $container->get(BookingManager::class)->addAmountToBooking($booking, $amount);
        }

        return $booking;
    }

    private function getBooking(User $user, \DateTimeImmutable $date): Booking
    {
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);
        $room        = static::getContainer()->get(RoomRepository::class)->findOneBy(['name' => BasicFixtures::ROOM_FOR_BOOKINGS]);
        $booking     = static::getContainer()->get(BookingRepository::class)
                             ->findOneBy([
                                 'room'        => $room,
                                 'businessDay' => $businessDay,
                                 'user'        => $user,
                             ])
        ;

        return $booking;
    }
}
