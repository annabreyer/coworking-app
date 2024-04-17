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
            'App\DataFixtures\PriceFixtures',
            'App\DataFixtures\BookingFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
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
            'App\DataFixtures\PriceFixtures',
            'App\DataFixtures\BookingFixtures',
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
            'App\DataFixtures\PriceFixtures',
            'App\DataFixtures\BookingFixtures',
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
            'App\DataFixtures\PriceFixtures',
            'App\DataFixtures\BookingFixtures',
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

    public function testStepDateFormSubmitErrorWithInvalidCsrfToken(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
            'App\DataFixtures\PriceFixtures',
            'App\DataFixtures\BookingFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/booking');

        $form = $crawler->filter('form')->form();
        $form->getPhpValues();
        $form->setValues(['token_date' => 'invalid']);
        $client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Invalid CSRF Token');
    }

    public function testStepDateFormSubmitErrorWithNoDateSelected(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
            'App\DataFixtures\PriceFixtures',
            'App\DataFixtures\BookingFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/booking');

        $form = $crawler->filter('form')->form();
        $form->getPhpValues();
        $form->setValues(['date' => '']);
        $client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'No date selected');
    }

    public function testStepDateFormSubmitErrorWithWrongDateFormat(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
            'App\DataFixtures\PriceFixtures',
            'App\DataFixtures\BookingFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/booking');
        $form    = $crawler->filter('#form-date')->form();
        $form->getPhpValues();
        $form->setValues(['date' => 'March first last year']);
        $client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Invalid date format');
    }

    public function testStepDateFormSubmitErrorWithDateInThePast(): void
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
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/booking');
        $form    = $crawler->filter('#form-date')->form();
        $form->getPhpValues();
        $form->setValues(['date' => '2024-02-01']);
        $client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Date must be in the future');
    }

    public function testStepDateFormSubmitErrorWithNoBusinessDay(): void
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
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/booking');
        $form    = $crawler->filter('#form-date')->form();
        $form->getPhpValues();
        $form->setValues(['date' => '2024-12-01']);
        $client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Requested Date is not a business da');
    }

    public function testStepDateFormSubmitErrorWithClosedBusinessDay(): void
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
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/booking');
        $form    = $crawler->filter('#form-date')->form();
        $form->getPhpValues();
        // 2024-05-12 is a Sunday
        $form->setValues(['date' => '2024-05-12']);
        $client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Requested Date is not a business da');
    }

    public function testStepDateFormSubmitSuccessfullAndRedirect(): void
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
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $date        = new \DateTimeImmutable('2024-05-10');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);

        $crawler = $client->request('GET', '/booking');
        $form    = $crawler->filter('#form-date')->form();
        $form->setValues(['date' => '2024-05-10']);
        $client->submit($form);

        $this->assertResponseRedirects('/booking/' . $businessDay->getId() . '/room');
    }

    public function testStepRoomRendersTemplateOnGetRequest(): void
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
            'App\DataFixtures\PriceFixtures',
            'App\DataFixtures\BookingFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);
        $date        = new \DateTimeImmutable('2024-05-10');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);

        $crawler = $client->request('GET', '/booking/' . $businessDay->getId() . '/room');
        $this->assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('#form-date'));
        self::assertCount(1, $crawler->filter('#form-room'));
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
            'App\DataFixtures\PriceFixtures',
            'App\DataFixtures\BookingFixtures',
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

    public function testStepRoomFormSubmitErrorWithInvalidCsrfToken(): void
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
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);

        $crawler = $client->request('GET', '/booking/' . $businessDay->getId() . '/room');
        $form    = $crawler->filter('#form-room')->form();
        $form->setValues(['token' => 'invalid']);
        $client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Invalid CSRF Token');
    }

    public function testStepRoomFormSubmitErrorWithEmptyRoom(): void
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
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);

        $uri     = '/booking/' . $businessDay->getId() . '/room';
        $crawler = $client->request('GET', $uri);
        $form    = $crawler->filter('#form-room')->form();
        $form->disableValidation();
        $client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'No room selected');
    }

    public function testStepRoomFormSubmitErrorWithWrongValueForRoom(): void
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
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);

        $uri     = '/booking/' . $businessDay->getId() . '/room';
        $crawler = $client->request('GET', $uri);
        $form    = $crawler->filter('#form-room')->form();
        $form->disableValidation();
        $form->setValues(['room' => 'invalid']);
        $client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Unknown room selected');
    }

    public function testStepRoomFormSubmitErrorWithNotExistingRoom(): void
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
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);

        $uri = '/booking/' . $businessDay->getId() . '/room';

        $crawler = $client->request('GET', $uri);
        $form    = $crawler->filter('#form-room')->form();
        $form->disableValidation();
        $form->setValues(['room' => 99999]);
        $client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Unknown room selected');
    }

    public function testStepRoomFormSubmitErrorWithNoCapacity(): void
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
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);
        $room        = static::getContainer()->get(RoomRepository::class)->findOneBy(['capacity' => 0]);

        $uri     = '/booking/' . $businessDay->getId() . '/room';
        $crawler = $client->request('GET', $uri);
        $form    = $crawler->filter('#form-room')->form();

        /*
        This code does not work. Select can only be done by Room Name, but then the backend code does not work,
        because it expects the Room ID.
        $form['room']->disableValidation();
        $form['room']->select($room->getName());
        $form['room']->setValue(strval($room->getID()));
        $client->submit($form);

        so instead we do a POST request with the room ID
        */

        $token = $form->get('token')->getValue();
        $client->request('POST', $uri, [
            'room'  => $room->getID(),
            'token' => $token,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Room is already fully booked');
    }

    public function testStepRoomFormSubmitErrorFullyBooked(): void
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
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);
        $room        = static::getContainer()->get(RoomRepository::class)->findOneBy(['name' => 'Room 1']);

        $uri     = '/booking/' . $businessDay->getId() . '/room';
        $crawler = $client->request('GET', $uri);
        $form    = $crawler->filter('#form-room')->form();

        /*
        This code does not work. Select can only be done by Room Name, but then the backend code does not work,
        because it expects the Room ID.
        $form['room']->disableValidation();
        $form['room']->select($room->getName());
        $form['room']->setValue(strval($room->getID()));
        $client->submit($form);

        so instead we do a POST request with the room ID
        */

        $token = $form->get('token')->getValue();
        $client->request('POST', $uri, [
            'room'  => $room->getID(),
            'token' => $token,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSelectorTextContains('div.alert', 'Room is already fully booked');
    }

    public function testStepRoomFormSubmitSuccessfullAndRedirect(): void
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
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);
        $room        = static::getContainer()->get(RoomRepository::class)->findOneBy(['name' => 'Room 3']);

        $crawler = $client->request('GET', '/booking/' . $businessDay->getId() . '/room');
        $form    = $crawler->filter('#form-room')->form();
        $form->setValues(['room' => $room->getID()]);
        $client->submit($form);

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

    public function testStepRoomFormSubmitSuccessfullNotifiesAdmin(): void
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
        $testUser       = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($testUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);
        $room        = static::getContainer()->get(RoomRepository::class)->findOneBy(['name' => 'Room 3']);

        $crawler = $client->request('GET', '/booking/' . $businessDay->getId() . '/room');
        $form    = $crawler->filter('#form-room')->form();
        $form->setValues(['room' => $room->getID()]);
        $client->submit($form);

        $booking = static::getContainer()->get(BookingRepository::class)
                         ->findOneBy([
                             'room'        => $room,
                             'businessDay' => $businessDay,
                             'user'        => $testUser,
                         ])
        ;

        self::assertNotNull($booking);
        $this->assertResponseRedirects();
        $this->assertEmailCount(1);

        $email = $this->getMailerMessage();
        $this->assertEmailSubjectContains($email, $businessDay->getDate()->format('d/m/Y'));
    }

    public function testCancelBookingChecksUser()
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

        $uri = '/booking/' . $booking->getId() . '/cancel';
        $client->request('POST', $uri, ['bookingId' => $booking->getId()]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCancelBookingChecksBookingIdInUrlAndPostMatch()
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
        $testUser       = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($testUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);
        $room        = static::getContainer()->get(RoomRepository::class)->findOneBy(['name' => 'Room 3']);
        $booking     = static::getContainer()->get(BookingRepository::class)
                             ->findOneBy([
                                 'room'        => $room,
                                 'businessDay' => $businessDay,
                                 'user'        => $testUser,
                             ])
        ;

        $uri = '/booking/' . $booking->getId() . '/cancel';
        $client->request('POST', $uri, ['bookingId' => 99999]);

        $this->assertResponseRedirects();

        $notCancelledBooking = static::getContainer()->get(BookingRepository::class)->find($booking->getId());
        self::assertNotNull($notCancelledBooking);

        $session = $client->getRequest()->getSession();
        self::assertContains('Booking can not be cancelled.', $session->getFlashBag()->get('error'));
    }

    public function testCancelBookingChecksBookingIsInTheFuture()
    {
        static::mockTime(new \DateTimeImmutable('2024-04-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
            'App\DataFixtures\PriceFixtures',
            'App\DataFixtures\BookingFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($testUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);
        $room        = static::getContainer()->get(RoomRepository::class)->findOneBy(['name' => 'Room 3']);
        $booking     = static::getContainer()->get(BookingRepository::class)
                             ->findOneBy([
                                 'room'        => $room,
                                 'businessDay' => $businessDay,
                                 'user'        => $testUser,
                             ])
        ;

        $bookingId = $booking->getId();
        $uri = '/booking/' . $bookingId . '/cancel';
        $client->request('POST', $uri, ['bookingId' => $bookingId]);

        $this->assertResponseRedirects();

        $notCancelledBooking = static::getContainer()->get(BookingRepository::class)->find($booking->getId());
        self::assertNotNull($notCancelledBooking);

        $limit           = static::getContainer()->getParameter('time_limit_cancel_booking_days');
        $session         = $client->getRequest()->getSession();
        $expectedMessage = sprintf('Bookings can only be cancelled %s before their date.', $limit);
        $errors = $session->getFlashBag()->get('error');
        self::assertContains($expectedMessage, $errors);
    }

    public function testCancelBookingSuccessfullDeletesBookingFromDatabase(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $bookingRepository = static::getContainer()->get(BookingRepository::class);

        $databaseTool->loadFixtures([
            'App\DataFixtures\AppFixtures',
            'App\DataFixtures\PriceFixtures',
            'App\DataFixtures\BookingFixtures',
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($testUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);
        $room        = static::getContainer()->get(RoomRepository::class)->findOneBy(['name' => 'Room 3']);
        $booking     = $bookingRepository->findOneBy([
                                 'room'        => $room,
                                 'businessDay' => $businessDay,
                                 'user'        => $testUser,
                             ])
        ;
        $bookingId = $booking->getId();

        $uri = '/booking/' . $booking->getId() . '/cancel';
        $client->request('POST', $uri, ['bookingId' => $bookingId]);

        $deletedBooking = $bookingRepository->find($bookingId);
        $this->assertNull($deletedBooking);
    }

    public function testCancelBookingSuccessfullRedirectsToUserBookings(): void
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
        $testUser       = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($testUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);
        $room        = static::getContainer()->get(RoomRepository::class)->findOneBy(['name' => 'Room 3']);
        $booking     = static::getContainer()->get(BookingRepository::class)
                             ->findOneBy([
                                 'room'        => $room,
                                 'businessDay' => $businessDay,
                                 'user'        => $testUser,
                             ])
        ;

        $uri = '/booking/' . $booking->getId() . '/cancel';
        $client->request('POST', $uri, ['bookingId' => $booking->getId()]);

        $this->assertResponseRedirects('/user/bookings');
    }

    public function testCancelBookingSuccessfullNotifiesAdmin(): void
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
        $testUser       = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($testUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);
        $room        = static::getContainer()->get(RoomRepository::class)->findOneBy(['name' => 'Room 3']);
        $booking     = static::getContainer()->get(BookingRepository::class)
                             ->findOneBy([
                                 'room'        => $room,
                                 'businessDay' => $businessDay,
                                 'user'        => $testUser,
                             ])
        ;

        $bookingDate = $booking->getBusinessDay()->getDate();
        $uri         = '/booking/' . $booking->getId() . '/cancel';
        $client->request('POST', $uri, ['bookingId' => $booking->getId()]);

        $this->assertResponseRedirects();
        self::assertNull($booking->getId());

        $this->assertEmailCount(1);

        $email = $this->getMailerMessage();
        $this->assertEmailTextBodyContains($email, (string) $bookingDate->format('d/m/Y'));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->databaseTool = null;
    }
}
