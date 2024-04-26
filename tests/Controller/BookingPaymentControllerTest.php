<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\BookingFixtures;
use App\DataFixtures\PaymentFixtures;
use App\DataFixtures\PriceFixtures;
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
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;

class BookingPaymentControllerTest extends WebTestCase
{
    use ClockSensitiveTrait;

    protected ?AbstractDatabaseTool $databaseTool;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testStepPaymentLogsErrorAndRedirectsWhenBookingIsNotFound(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($testUser);

        $uri = '/booking/hyf-5678-hnbgyu/payment';
        $client->request('GET', $uri);

        $this->assertResponseRedirects('/booking');
        $logger = static::getContainer()->get('monolog.logger');
        self::assertNotNull($logger);

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

    public function testStepPaymentChecksIfBookingUserIsConnectedUser(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $bookingUser = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $date        = new \DateTimeImmutable('2024-04-01');
        $booking     = $this->getBooking($bookingUser, $date);

        $uri = '/booking/' . $booking->getUuid() . '/payment';
        $client->request('GET', $uri);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testStepPaymentRendersTemplateOnGet(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable('2024-04-01');
        $booking = $this->getBooking($bookingUser, $date);

        $uri     = '/booking/' . $booking->getUuid() . '/payment';
        $crawler = $client->request('GET', $uri);

        $this->assertResponseIsSuccessful();
        self::assertCount(3, $crawler->filter('li'));
    }

    public function testStepPaymentTemplateContainsPaymentMethodForm(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable('2024-04-01');
        $booking = $this->getBooking($bookingUser, $date);

        $uri     = '/booking/' . $booking->getUuid() . '/payment';
        $crawler = $client->request('GET', $uri);

        $this->assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('form'));
    }

    public function testStepPaymentFormSubmitErrorWithInvalidCsrfToken(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable('2024-04-01');
        $booking = $this->getBooking($bookingUser, $date);
        $uri     = '/booking/' . $booking->getUuid() . '/payment';
        $crawler = $client->request('GET', $uri);

        $form = $crawler->filter('form')->form();
        $form->getPhpValues();
        $form->setValues(['token' => 'invalid']);
        $client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Invalid CSRF Token.');
    }

    public function testStepPaymentFormSubmitWithoutPriceId(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable('2024-04-01');
        $booking = $this->getBooking($bookingUser, $date);

        $uri     = '/booking/' . $booking->getUuid() . '/payment';
        $crawler = $client->request('GET', $uri);
        $form    = $crawler->filter('form')->form();
        $form->disableValidation();
        unset($form['priceId']);
        $client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'PriceId is missing.');
    }

    public function testStepPaymentFormSubmitWithInvalidPriceId(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable('2024-04-01');
        $booking = $this->getBooking($bookingUser, $date);

        $uri     = '/booking/' . $booking->getUuid() . '/payment';
        $crawler = $client->request('GET', $uri);
        $form    = $crawler->filter('form')->form();
        $form->disableValidation();
        $form->setValues(['priceId' => '999999']);
        $client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Price not found.');
    }

    public function testStepPaymentFormSubmitWithMissingPaymentMethod(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable('2024-04-01');
        $booking = $this->getBooking($bookingUser, $date);

        $uri     = '/booking/' . $booking->getUuid() . '/payment';
        $crawler = $client->request('GET', $uri);
        $form    = $crawler->filter('form')->form();
        $form->disableValidation();
        unset($form['paymentMethod']);
        $client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Payment method is missing.');
    }

    public function testStepPaymentFormSubmitWithInvalidPaymentMethod(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable('2024-04-01');
        $booking = $this->getBooking($bookingUser, $date);

        $uri     = '/booking/' . $booking->getUuid() . '/payment';
        $crawler = $client->request('GET', $uri);
        $form    = $crawler->filter('form')->form();
        $form->disableValidation();
        $form['paymentMethod'] = 'creditCard';
        $client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Payment method not found.');
    }

    public function testStepPaymentFormSubmitWithPaymentMethodInvoiceAddsAmountToBookingAndRedirects(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable('2024-04-01');
        $booking = $this->getBooking($bookingUser, $date);

        $uri                   = '/booking/' . $booking->getUuid() . '/payment';
        $crawler               = $client->request('GET', $uri);
        $form                  = $crawler->filter('form')->form();
        $form['paymentMethod'] = 'invoice';
        $client->submit($form);

        $booking = static::getContainer()->get(BookingRepository::class)->findOneBy(['uuid' => $booking->getUuid()]);
        self::assertNotNull($booking->getAmount());

        $this->assertResponseRedirects('/booking/' . $booking->getUuid() . '/payment/confirmation');
    }

    public function testStepPaymentFormSubmitWithPaymentMethodInvoiceCreatesInvoice(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable('2024-04-01');
        $booking = $this->getBooking($bookingUser, $date);

        $uri                   = '/booking/' . $booking->getUuid() . '/payment';
        $crawler               = $client->request('GET', $uri);
        $form                  = $crawler->filter('form')->form();
        $form['paymentMethod'] = 'invoice';
        $client->submit($form);

        $invoice = static::getContainer()->get(InvoiceRepository::class)
                         ->findOneBy(['user' => $bookingUser])
        ;

        self::assertNotNull($invoice);
        self::assertSame($booking->getId(), $invoice->getBookings()->first()->getId());
    }

    public function testStepPaymentFormSubmitWithPaymentMethodInvoiceGeneratesInvoice(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable('2024-04-01');
        $booking = $this->getBooking($bookingUser, $date);

        $uri                   = '/booking/' . $booking->getUuid() . '/payment';
        $crawler               = $client->request('GET', $uri);
        $form                  = $crawler->filter('form')->form();
        $form['paymentMethod'] = 'invoice';
        $client->submit($form);

        $invoice = static::getContainer()->get(InvoiceRepository::class)
                         ->findOneBy(['user' => $bookingUser])
        ;

        $invoiceGenerator = static::getContainer()->get('App\Service\InvoiceGenerator');
        $filePath         = $invoiceGenerator->getTargetDirectory($invoice);
        self::assertFileExists($filePath);
    }

    public function testStepPaymentFormSubmitWithPaymentMethodInvoiceSendsInvoiceByMail(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable('2024-04-01');
        $booking = $this->getBooking($bookingUser, $date);

        $uri                   = '/booking/' . $booking->getUuid() . '/payment';
        $crawler               = $client->request('GET', $uri);
        $form                  = $crawler->filter('form')->form();
        $form['paymentMethod'] = 'invoice';
        $client->submit($form);

        $this->assertEmailCount(2);

        $email = $this->getMailerMessage();
        $this->assertEmailAttachmentCount($email, 1);
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

        $this->assertResponseRedirects('/booking');
        $logger = static::getContainer()->get('monolog.logger');
        self::assertNotNull($logger);

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
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $bookingUser = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $date        = new \DateTimeImmutable('2024-04-01');
        $booking     = $this->getBooking($bookingUser, $date);
        $uri         = '/booking/' . $booking->getUuid() . '/payment/voucher';
        $client->request('GET', $uri);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testPayWithVoucherRedirectsWhenBookingIsAlreadyPaid(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([PaymentFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable('2024-04-11');
        $booking = $this->getBooking($bookingUser, $date);
        $uri     = '/booking/' . $booking->getUuid() . '/payment/voucher';
        $client->request('GET', $uri);

        $this->assertResponseRedirects('/booking/' . $booking->getUuid() . '/payment/confirmation');
    }

    public function testPayWithVoucherRendersTemplateOnGet(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([PaymentFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable('2024-04-01');
        $booking = $this->getBooking($bookingUser, $date);
        $uri     = '/booking/' . $booking->getUuid() . '/payment/voucher';
        $crawler = $client->request('GET', $uri);

        $this->assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('form'));
    }

    public function testPayWithVoucherFormSubmitErrorWithInvalidCsrfToken(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([PaymentFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable('2024-04-01');
        $booking = $this->getBooking($bookingUser, $date);
        $uri     = '/booking/' . $booking->getUuid() . '/payment/voucher';

        $crawler = $client->request('GET', $uri);

        $form = $crawler->filter('form')->form();
        $form->getPhpValues();
        $form->setValues(['token' => 'invalid']);
        $client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Invalid CSRF Token.');
    }

    public function testPayWithVoucherFormSubmitErrorWithoutVoucherCode(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([PaymentFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable('2024-04-01');
        $booking = $this->getBooking($bookingUser, $date);
        $uri     = '/booking/' . $booking->getUuid() . '/payment/voucher';

        $crawler = $client->request('GET', $uri);

        $form = $crawler->filter('form')->form();
        $form->disableValidation();
        unset($form['voucher']);
        $client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Voucher code is missing.');
    }

    public function testPayWithVoucherFormSubmitErrorWithInvalidVoucherCode(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([PaymentFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable('2024-04-01');
        $booking = $this->getBooking($bookingUser, $date);
        $uri     = '/booking/' . $booking->getUuid() . '/payment/voucher';

        $crawler = $client->request('GET', $uri);

        $form = $crawler->filter('form')->form();
        $form->setValues(['voucher' => 'invalid']);
        $client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Voucher not found.');
    }

    public function testPayWithVoucherFormSubmitErrorWhenVoucherIsNotValidForUser(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([PaymentFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $adminVoucher = static::getContainer()->get(VoucherRepository::class)->findOneBy(['code' => 'VO20240002']);
        $date         = new \DateTimeImmutable('2024-04-01');
        $booking      = $this->getBooking($bookingUser, $date);
        $uri          = '/booking/' . $booking->getUuid() . '/payment/voucher';
        $crawler      = $client->request('GET', $uri);
        $form         = $crawler->filter('form')->form();
        $form->setValues(['voucher' => $adminVoucher->getCode()]);
        $client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Voucher is not valid for this user.');
    }

    public function testPayWithVoucherFormSubmitErrorWhenVoucherIsExpired(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([PaymentFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable('2024-04-01');
        $booking = $this->getBooking($bookingUser, $date);
        $uri     = '/booking/' . $booking->getUuid() . '/payment/voucher';
        $crawler = $client->request('GET', $uri);
        $form    = $crawler->filter('form')->form();
        $form->setValues(['voucher' => 'VO20240004']);
        $client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Voucher is expired.');
    }

    public function testPayWithVoucherFormSubmitErrorWhenVoucherHasAlreadyBeenUsed(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([PaymentFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);
        $useDate = new \DateTimeImmutable('2024-04-06');
        $voucher = static::getContainer()->get(VoucherRepository::class)->findOneBy(['useDate' => $useDate]);

        $date    = new \DateTimeImmutable('2024-04-01');
        $booking = $this->getBooking($bookingUser, $date);
        $uri     = '/booking/' . $booking->getUuid() . '/payment/voucher';
        $crawler = $client->request('GET', $uri);
        $form    = $crawler->filter('form')->form();
        $form->setValues(['voucher' => $voucher->getCode()]);
        $client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Voucher has already been used on 2024-04-06.');
    }

    public function testPayWithVoucherFormSubmitErrorWhenVoucherHasNotBeenPaidFor(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([PaymentFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable('2024-04-01');
        $booking = $this->getBooking($bookingUser, $date);
        $uri     = '/booking/' . $booking->getUuid() . '/payment/voucher';
        $crawler = $client->request('GET', $uri);
        $form    = $crawler->filter('form')->form();
        $form->setValues(['voucher' => 'VO20240033']);
        $client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Voucher has not been paid and cannot be used.');
    }

    public function testPayWithVoucherFormSubmitWithValidVoucherRedirects(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([PaymentFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $voucher = $bookingUser->getValidVouchers()->first();

        $date    = new \DateTimeImmutable('2024-04-01');
        $booking = $this->getBooking($bookingUser, $date);
        $uri     = '/booking/' . $booking->getUuid() . '/payment/voucher';
        $crawler = $client->request('GET', $uri);
        $form    = $crawler->filter('form')->form();
        $form->setValues(['voucher' => $voucher->getCode()]);
        $client->submit($form);

        $this->assertResponseRedirects('/booking/' . $booking->getUuid() . '/payment/confirmation');
    }

    public function testPayWithVoucherFormSubmitWithValidVoucherCreatesInvoice(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([PaymentFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $voucher = $bookingUser->getValidVouchers()->first();

        $date    = new \DateTimeImmutable('2024-04-01');
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

        $invoiceGenerator = static::getContainer()->get(InvoiceGenerator::class);
        $filePath         = $invoiceGenerator->getTargetDirectory($booking->getInvoice());
        self::assertFileExists($filePath);
    }

    public function testPayWithVoucherFormSubmitWithValidVoucherCreatesVoucherPayment(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([PaymentFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $voucher = $bookingUser->getValidVouchers()->first();

        $date    = new \DateTimeImmutable('2024-04-01');
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
        $databaseTool->loadFixtures([PaymentFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $voucher = $bookingUser->getValidVouchers()->first();
        $date    = new \DateTimeImmutable('2024-04-01');
        $booking = $this->getBooking($bookingUser, $date);
        $uri     = '/booking/' . $booking->getUuid() . '/payment/voucher';
        $crawler = $client->request('GET', $uri);
        $form    = $crawler->filter('form')->form();
        $form->setValues(['voucher' => $voucher->getCode()]);
        $client->submit($form);

        $this->assertEmailCount(1);

        $email = $this->getMailerMessage();
        $this->assertEmailAttachmentCount($email, 1);
    }

    public function testPaymentConfirmationLogsErrorAndRedirectsWhenBookingIsNotFound(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($testUser);

        $uri = '/booking/hyf-5678-hnbgyu/payment/confirmation';
        $client->request('GET', $uri);

        $this->assertResponseRedirects('/booking');
        $logger = static::getContainer()->get('monolog.logger');
        self::assertNotNull($logger);

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

    public function testPaymentConfirmationChecksIfBookingUserIsConnectedUser(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $bookingUser = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $date        = new \DateTimeImmutable('2024-04-01');
        $booking     = $this->getBooking($bookingUser, $date);
        $uri         = '/booking/' . $booking->getUuid() . '/payment/confirmation';
        $client->request('GET', $uri);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testPaymentConfirmationRendersTemplate(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([PaymentFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $invoice = static::getContainer()->get(InvoiceRepository::class)->findOneBy(['number' => 'CO20240044']);
        $uri     = '/booking/' . $invoice->getBookings()->first()->getUuid() . '/payment/confirmation';
        $crawler = $client->request('GET', $uri);

        $this->assertResponseIsSuccessful();
        $expectedUri = '/invoice/' . $invoice->getUuid() . '/download';
        $link        = $crawler->filter('a')->first();
        self::assertSame($expectedUri, $link->attr('href'));
    }

    private function getBooking(User $user, \DateTimeImmutable $date): Booking
    {
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);
        $room        = static::getContainer()->get(RoomRepository::class)->findOneBy(['name' => 'Room 3']);
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
        $this->databaseTool = null;
    }
}
