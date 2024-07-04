<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\BasicFixtures;
use App\DataFixtures\BookingFixtures;
use App\DataFixtures\BookingWithInvoiceNoPaymentFixture;
use App\DataFixtures\BookingWithOutAmountFixture;
use App\DataFixtures\BookingWithOutInvoiceFixture;
use App\DataFixtures\BookingWithPaymentFixture;
use App\DataFixtures\PriceFixtures;
use App\DataFixtures\VoucherFixtures;
use App\Entity\Booking;
use App\Entity\Payment;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\BusinessDayRepository;
use App\Repository\InvoiceRepository;
use App\Repository\RoomRepository;
use App\Repository\UserRepository;
use App\Repository\VoucherRepository;
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

    protected function setUp(): void
    {
        parent::setUp();
    }

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
        self::assertCount(3, $crawler->filter('li'));
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
        static::assertSelectorTextContains('div.alert', 'Ungültiges CSRF-Token.');
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
        static::assertSelectorTextContains('div.alert', 'Bitte ein Preis auswählen.');
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
        static::assertSelectorTextContains('div.alert', 'Der ausgewählte Preis konnte nicht gefunden werden.');
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
        static::assertSelectorTextContains('div.alert', 'Bitte eine Zahlungsmethode auswählen.');
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
        static::assertSelectorTextContains('div.alert', 'Die ausgewählte Zahlungsmethode konnte nicht gefunden werden.');
    }

    public function testStepPaymentFormSubmitWithPaymentMethodInvoiceAddsAmountToBookingAndGeneratesInvoiceAndRedirects(): void
    {
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
        static::assertResponseRedirects('/booking/' . $booking->getUuid() . '/payment/confirmation');
    }

    public function testStepPaymentFormSubmitWithPaymentMethodPayPalAddsRedirects(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithInvoiceNoPaymentFixture::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable(BookingWithInvoiceNoPaymentFixture::BUSINESS_DAY_DATE);
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
        $databaseTool->loadFixtures([BookingWithInvoiceNoPaymentFixture::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable(BookingWithInvoiceNoPaymentFixture::BUSINESS_DAY_DATE);
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
        $databaseTool->loadFixtures([BookingWithInvoiceNoPaymentFixture::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $bookingUser = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $date        = new \DateTimeImmutable(BookingWithInvoiceNoPaymentFixture::BUSINESS_DAY_DATE);
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
        static::assertSelectorTextContains('div.alert', 'Ungültiges CSRF-Token.');
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
        static::assertSelectorTextContains('div.alert', 'Bitte einen Gutscheincode eingeben.');
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
        static::assertSelectorTextContains('div.alert', 'Ungültiger Gutscheincode.');
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
        static::assertSelectorTextContains('div.alert', 'Gutscheincode kann nicht für diesen Benutzer verwendet werden.');
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
        static::assertSelectorTextContains('div.alert', 'Gutscheincode ist abgelaufen.');
    }

    public function testPayWithVoucherFormSubmitErrorWhenVoucherHasAlreadyBeenUsed(): void
    {
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
        static::assertSelectorTextContains('div.alert', 'Gutscheincode wurde bereits am 2024-03-14 verwendet.');
    }

    public function testPayWithVoucherFormSubmitErrorWhenVoucherHasNotBeenPaidFor(): void
    {
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
        static::assertSelectorTextContains('div.alert', 'Gutscheincode wurde noch nicht bezahlt und kann somit noch nicht genutzt werden.');
    }

    public function testPayWithVoucherFormSubmitWithValidVoucherRedirects(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithInvoiceNoPaymentFixture::class, VoucherFixtures::class, BookingFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $voucher = $bookingUser->getValidVouchers()->first();

        $date    = new \DateTimeImmutable(BookingWithInvoiceNoPaymentFixture::BUSINESS_DAY_DATE);
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
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithInvoiceNoPaymentFixture::class, VoucherFixtures::class, BookingFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $voucher = $bookingUser->getValidVouchers()->first();

        $date    = new \DateTimeImmutable(BookingWithInvoiceNoPaymentFixture::BUSINESS_DAY_DATE);
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
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithInvoiceNoPaymentFixture::class, VoucherFixtures::class, BookingFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $voucher = $bookingUser->getValidVouchers()->first();

        $date    = new \DateTimeImmutable(BookingWithInvoiceNoPaymentFixture::BUSINESS_DAY_DATE);
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
        self::assertSame($voucher->getId(), $invoice->getPayments()->first()->getVoucher()->getId());
    }

    public function testPayWithVoucherFormSubmitWithValidVoucherSendsInvoiceToClient(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingWithInvoiceNoPaymentFixture::class, VoucherFixtures::class, BookingFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $voucher = $bookingUser->getValidVouchers()->first();
        $date    = new \DateTimeImmutable(BookingWithInvoiceNoPaymentFixture::BUSINESS_DAY_DATE);
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
        $databaseTool->loadFixtures([BookingWithInvoiceNoPaymentFixture::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $bookingUser = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $date        = new \DateTimeImmutable(BookingWithInvoiceNoPaymentFixture::BUSINESS_DAY_DATE);
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
        $link        = $crawler->filter('a')->first();
        self::assertSame($expectedUri, $link->attr('href'));
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

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
