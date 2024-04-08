<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Repository\BookingRepository;
use App\Repository\BusinessDayRepository;
use App\Repository\RoomRepository;
use App\Repository\UserRepository;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;

class BookingControllerTest extends WebTestCase
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

    public function testStepDateRendersTemplateOnGetRequest(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/booking');
        $this->assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('h1'));
    }

    public function testStepDateTemplateContainsDatepicker(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/booking');
        $this->assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('input[type="date"]'));
    }

    public function testStepDateTemplateDatepickerIsSetOnCurrentDay()
    {
        static::mockTime(new \DateTimeImmutable('2024-05-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/booking');
        $this->assertResponseIsSuccessful();
        $datepicker = $crawler->filter('input[type="date"]');
        self::assertCount(1, $datepicker);
        self::assertSame('2024-05-01', $datepicker->attr('value'));
        self::assertSame('2024-05-01', $datepicker->attr('min'));
    }

    public function testStepDateTemplateDatepickerMaxMatchesDatabase()
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/booking');
        $this->assertResponseIsSuccessful();
        $datepicker = $crawler->filter('input[type="date"]');
        self::assertCount(1, $datepicker);
        self::assertSame('2024-05-31', $datepicker->attr('max'));
    }

    public function testStepDatePostErrorWithInvalidCsrfToken(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $client->request('POST', '/booking', ['token' => 'invalid']);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Invalid CSRF Token');
    }

    public function testStepDatePostErrorWithNoDateSelected(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/booking');
        $token   = $crawler->filter('input[name="token_date"]')->attr('value');

        $client->request('POST', '/booking', ['token' => $token]);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'No date selected');
    }

    public function testStepDatePostErrorWithWrongDateFormat(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/booking');
        $token   = $crawler->filter('input[name="token_date"]')->attr('value');

        $client->request('POST', '/booking', ['token' => $token, 'date' => 'March first last year']);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Invalid date format');
    }

    public function testStepDatePostErrorWithDateInThePast(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/booking');
        $token   = $crawler->filter('input[name="token_date"]')->attr('value');

        $client->request('POST', '/booking', ['token' => $token, 'date' => '2024-02-01']);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Date must be in the future');
    }

    public function testStepDatePostErrorWithNoBusinessDay(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/booking');
        $token   = $crawler->filter('input[name="token_date"]')->attr('value');

        $client->request('POST', '/booking', ['token' => $token, 'date' => '2024-12-01']);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Requested Date is not a business da');
    }

    public function testStepDatePostErrorWithClosedBusinessDay(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/booking');
        $token   = $crawler->filter('input[name="token_date"]')->attr('value');

        // 2024-05-12 is a Sunday
        $client->request('POST', '/booking', ['token' => $token, 'date' => '2024-05-12']);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Requested Date is not a business da');
    }

    public function testStepDatePostSuccessfullAndRedirect(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/booking');
        $token   = $crawler->filter('input[name="token_date"]')->attr('value');

        $date        = new \DateTimeImmutable('2024-05-10');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);

        $client->request('POST', '/booking', ['token' => $token, 'date' => '2024-05-10']);
        $this->assertResponseRedirects('/booking/' . $businessDay->getId() . '/room');
    }

    public function testStepRoomRendersTemplateOnGetRequest(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);
        $date        = new \DateTimeImmutable('2024-05-10');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);

        $crawler = $client->request('GET', '/booking/' . $businessDay->getId() . '/room');
        $this->assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('h1'));
    }

    public function testStepRoomTemplateContainsDatePickerAndSelect(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);
        $date        = new \DateTimeImmutable('2024-05-10');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);

        $crawler = $client->request('GET', '/booking/' . $businessDay->getId() . '/room');
        $this->assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('form'));
        self::assertCount(1, $crawler->filter('input[type="date"]'));
        self::assertCount(1, $crawler->filter('select'));
    }

    public function testStepRoomTemplateSelectOptionIsDisabledIfFullyBooked(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);

        $crawler = $client->request('GET', '/booking/' . $businessDay->getId() . '/room');
        $this->assertResponseIsSuccessful();
        $selectOption = $crawler->filter('option');
        self::assertSame('', $selectOption->attr('disabled'));
    }

    public function testStepRoomPostErrorWithInvalidCsrfToken(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);

        $client->request('POST', '/booking/' . $businessDay->getId() . '/room', ['token' => 'invalid']);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Invalid CSRF Token');
    }

    public function testStepRoomPostErrorWithEmptyRoom(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);

        $uri     = '/booking/' . $businessDay->getId() . '/room';
        $crawler = $client->request('GET', $uri);
        $token   = $crawler->filter('input[name="token"]')->attr('value');

        $client->request('POST', $uri, ['token' => $token, 'room' => '']);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'No room selected');
    }

    public function testStepRoomPostErrorWithWrongValueForRoom(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);

        $uri     = '/booking/' . $businessDay->getId() . '/room';
        $crawler = $client->request('GET', $uri);
        $token   = $crawler->filter('input[name="token"]')->attr('value');

        $client->request('POST', $uri, ['token' => $token, 'room' => 'test']);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'No room selected');
    }

    public function testStepRoomPostErrorWithNotExistingRoom(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);

        $uri     = '/booking/' . $businessDay->getId() . '/room';
        $crawler = $client->request('GET', $uri);
        $token   = $crawler->filter('input[name="token"]')->attr('value');

        $client->request('POST', $uri, ['token' => $token, 'room' => 99999]);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Unknown room selected');
    }

    public function testStepRoomPostErrorWithNoCapacity(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);
        $room        = static::getContainer()->get(RoomRepository::class)->findOneBy(['capacity' => 0]);

        $uri     = '/booking/' . $businessDay->getId() . '/room';
        $crawler = $client->request('GET', $uri);
        $token   = $crawler->filter('input[name="token"]')->attr('value');

        $client->request('POST', $uri, ['token' => $token, 'room' => $room->getID()]);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Room is already fully booked');
    }

    public function testStepRoomPostErrorFullyBooked(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);
        $room        = static::getContainer()->get(RoomRepository::class)->findOneBy(['name' => 'Room 1']);

        $uri     = '/booking/' . $businessDay->getId() . '/room';
        $crawler = $client->request('GET', $uri);
        $token   = $crawler->filter('input[name="token"]')->attr('value');

        $client->request('POST', $uri, ['token' => $token, 'room' => $room->getID()]);
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Room is already fully booked');
    }

    public function testStepRoomPostSuccessfullAndRedirect(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);
        $room        = static::getContainer()->get(RoomRepository::class)->findOneBy(['name' => 'Room 3']);

        $uri     = '/booking/' . $businessDay->getId() . '/room';
        $crawler = $client->request('GET', $uri);
        $token   = $crawler->filter('input[name="token"]')->attr('value');

        $client->request('POST', $uri, ['token' => $token, 'room' => $room->getID()]);

        $booking = static::getContainer()->get(BookingRepository::class)
                         ->findOneBy([
                             'room'        => $room,
                             'businessDay' => $businessDay,
                             'user'        => $testUser,
                         ])
        ;

        self::assertNotNull($booking);
        $this->assertResponseRedirects('/booking/' . $booking->getId() . '/payment');
    }

    public function testStepPaymentChecksIfBookingUserIsConnectedUser(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $bookingUser = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);

        $date        = new \DateTimeImmutable('2024-04-01');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);
        $room        = static::getContainer()->get(RoomRepository::class)->findOneBy(['name' => 'Room 3']);
        $booking     = static::getContainer()->get(BookingRepository::class)
                             ->findOneBy([
                                 'room'        => $room,
                                 'businessDay' => $businessDay,
                                 'user'        => $bookingUser,
                             ])
        ;

        $uri = '/booking/' . $booking->getId() . '/payment';
        $client->request('GET', $uri);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testStepPaymentRendersTemplateOnGet(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $bookingUser    = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($bookingUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);
        $room        = static::getContainer()->get(RoomRepository::class)->findOneBy(['name' => 'Room 3']);
        $booking     = static::getContainer()->get(BookingRepository::class)
                             ->findOneBy([
                                 'room'        => $room,
                                 'businessDay' => $businessDay,
                                 'user'        => $bookingUser,
                             ])
        ;

        $uri     = '/booking/' . $booking->getId() . '/payment';
        $crawler = $client->request('GET', $uri);

        $this->assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('li'));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->databaseTool = null;
    }
}
