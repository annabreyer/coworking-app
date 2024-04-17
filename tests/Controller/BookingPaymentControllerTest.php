<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Booking;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\BusinessDayRepository;
use App\Repository\InvoiceRepository;
use App\Repository\RoomRepository;
use App\Repository\UserRepository;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;

class BookingPaymentControllerTest extends WebTestCase
{
    use ClockSensitiveTrait;

    /**
     * @var AbstractDatabaseTool
     */
    protected $databaseTool;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testStepPaymentChecksIfBookingUserIsConnectedUser(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
            'App\DataFixtures\BookingFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $bookingUser = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $date        = new \DateTimeImmutable('2024-04-01');
        $booking     = $this->getBooking($bookingUser, $date);

        $uri = '/booking/' . $booking->getId() . '/cancel';
        $client->request('POST', $uri, ['bookingId' => $booking->getId()]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testStepPaymentRendersTemplateOnGet(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
            'App\DataFixtures\PriceFixtures',
            'App\DataFixtures\BookingFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $booking = $this->getBooking($bookingUser, $date);

        $uri     = '/booking/' . $booking->getId() . '/payment';
        $crawler = $client->request('GET', $uri);

        $this->assertResponseIsSuccessful();
        self::assertCount(3, $crawler->filter('li'));
    }

    public function testStepPaymentTemplateContainsPaymentMethodForm(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
            'App\DataFixtures\PriceFixtures',
            'App\DataFixtures\BookingFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $booking = $this->getBooking($bookingUser, $date);

        $uri     = '/booking/' . $booking->getId() . '/payment';
        $crawler = $client->request('GET', $uri);

        $this->assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('form'));
    }

    public function testStepPaymentFormSubmitWithoutPriceId(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
            'App\DataFixtures\PriceFixtures',
            'App\DataFixtures\BookingFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $booking = $this->getBooking($bookingUser, $date);

        $uri     = '/booking/' . $booking->getId() . '/payment';
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
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
            'App\DataFixtures\PriceFixtures',
            'App\DataFixtures\BookingFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $booking = $this->getBooking($bookingUser, $date);

        $uri     = '/booking/' . $booking->getId() . '/payment';
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
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
            'App\DataFixtures\PriceFixtures',
            'App\DataFixtures\BookingFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $booking = $this->getBooking($bookingUser, $date);

        $uri     = '/booking/' . $booking->getId() . '/payment';
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
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
            'App\DataFixtures\PriceFixtures',
            'App\DataFixtures\BookingFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $booking = $this->getBooking($bookingUser, $date);

        $uri     = '/booking/' . $booking->getId() . '/payment';
        $crawler = $client->request('GET', $uri);
        $form    = $crawler->filter('form')->form();
        $form->disableValidation();
        $form['paymentMethod'] = 'creditCard';
        $client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Payment method not found.');
    }

    public function testStepPaymentFormSubmitWithPaymentMethodInvoiceRedirects(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
            'App\DataFixtures\PriceFixtures',
            'App\DataFixtures\BookingFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $booking = $this->getBooking($bookingUser, $date);

        $uri     = '/booking/' . $booking->getId() . '/payment';
        $crawler = $client->request('GET', $uri);
        $form    = $crawler->filter('form')->form();
        $form['paymentMethod'] = 'invoice';
        $client->submit($form);

        $this->assertResponseRedirects('/booking/' . $booking->getId() . '/invoice');
    }

    public function testStepPaymentFormSubmitWithPaymentMethodInvoiceCreatesInvoice(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
            'App\DataFixtures\PriceFixtures',
            'App\DataFixtures\BookingFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $booking = $this->getBooking($bookingUser, $date);

        $uri     = '/booking/' . $booking->getId() . '/payment';
        $crawler = $client->request('GET', $uri);
        $form    = $crawler->filter('form')->form();
        $form['paymentMethod'] = 'invoice';
        $client->submit($form);

        $invoice = static::getContainer()->get(InvoiceRepository::class)
                                         ->findOneBy(['user' => $bookingUser]);

        $this->assertNotNull($invoice);
        $this->assertSame($booking->getId(), $invoice->getBookings()->first()->getId());
    }

    public function testStepPaymentFormSubmitWithPaymentMethodInvoiceGeneratesInvoice(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
            'App\DataFixtures\PriceFixtures',
            'App\DataFixtures\BookingFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $booking = $this->getBooking($bookingUser, $date);

        $uri     = '/booking/' . $booking->getId() . '/payment';
        $crawler = $client->request('GET', $uri);
        $form    = $crawler->filter('form')->form();
        $form['paymentMethod'] = 'invoice';
        $client->submit($form);

        $invoice = static::getContainer()->get(InvoiceRepository::class)
                         ->findOneBy(['user' => $bookingUser]);

        $invoiceGenerator = static::getContainer()->get('App\Service\InvoiceGenerator');
        $filePath = $invoiceGenerator->getTargetDirectory($invoice);
        $this->assertFileExists($filePath);
    }

    public function testStepPaymentFormSubmitWithPaymentMethodInvoiceSendsInvoiceByMail(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
            'App\DataFixtures\PriceFixtures',
            'App\DataFixtures\BookingFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $booking = $this->getBooking($bookingUser, $date);

        $uri     = '/booking/' . $booking->getId() . '/payment';
        $crawler = $client->request('GET', $uri);
        $form    = $crawler->filter('form')->form();
        $form['paymentMethod'] = 'invoice';
        $client->submit($form);

        $this->assertEmailCount(1);

        $email = $this->getMailerMessage();
        $this->assertEmailAttachmentCount($email, 1);
    }

    public function testLaterPaymentRedirectsWhenInvoiceIsMissing(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
            'App\DataFixtures\PriceFixtures',
            'App\DataFixtures\BookingFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable('2024-04-01');
        $booking = $this->getBooking($bookingUser, $date);
        $uri     = '/booking/' . $booking->getId() . '/invoice';
        $client->request('GET', $uri);

        $this->assertResponseRedirects();
    }

    public function testLaterPaymentRendersTemplateOnGet(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
            'App\DataFixtures\PriceFixtures',
            'App\DataFixtures\InvoiceFixtures',
            'App\DataFixtures\BookingFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date    = new \DateTimeImmutable('2024-04-01');
        $booking = $this->getBooking($bookingUser, $date);
        $uri     = '/booking/' . $booking->getId() . '/invoice';
        $crawler = $client->request('GET', $uri);

        $this->assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('a'));
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
