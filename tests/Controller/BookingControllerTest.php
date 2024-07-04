<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\BasicFixtures;
use App\DataFixtures\BookingFixtures;
use App\DataFixtures\PriceFixtures;
use App\Entity\Booking;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\BusinessDayRepository;
use App\Repository\RoomRepository;
use App\Repository\UserRepository;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

class BookingControllerTest extends WebTestCase
{
    use ClockSensitiveTrait;

    public function testStepDateLogsErrorAndRedirectsWhenNoBusinessDaysAreDefined(): void
    {
        // No BusinessDays exist in fixtures after 2024-06-01
        static::mockTime(new \DateTimeImmutable('2024-07-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($testUser);

        $client->request('GET', '/booking');
        static::assertResponseRedirects('/');

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
            'No BusinessDays found.',
            Level::fromName('critical')
        ));
    }

    public function testStepDateLogsErrorRedirectsWhenNoPricesAreDefined(): void
    {
        // No BusinessDays exist in fixtures after 2024-06-01
        static::mockTime(new \DateTimeImmutable('2024-04-30'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BasicFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($testUser);

        $client->request('GET', '/booking');
        static::assertResponseRedirects('/');

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
            'No active Price found.',
            Level::fromName('critical')
        ));
    }

    public function testStepDateRendersTemplateOnGetRequest(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-04-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/booking');
        static::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('h1'));
    }

    public function testStepDateTemplateContainsDatepicker(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-04-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/booking');
        static::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('input[type="date"]'));
    }

    public function testStepDateTemplateDatepickerIsSetOnCurrentDay(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-05-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/booking');
        static::assertResponseIsSuccessful();
        $datepicker = $crawler->filter('input[type="date"]');
        self::assertCount(1, $datepicker);
        self::assertSame('2024-05-01', $datepicker->attr('value'));
        self::assertSame('2024-05-01', $datepicker->attr('min'));
    }

    public function testStepDateTemplateDatepickerMaxMatchesDatabase(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-05-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/booking');
        static::assertResponseIsSuccessful();
        $datepicker = $crawler->filter('input[type="date"]');
        self::assertCount(1, $datepicker);
        self::assertSame('2024-05-31', $datepicker->attr('max'));
    }

    public function testStepDateFormSubmitErrorWithInvalidCsrfToken(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/booking');

        $form = $crawler->filter('form')->form();
        $form->getPhpValues();
        $form->setValues(['token_date' => 'invalid']);
        $client->submit($form);

        static::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        static::assertSelectorTextContains('section#flash-messages', 'Ungültiges CSRF-Token.');
    }

    public function testStepDateFormSubmitErrorWithNoDateSelected(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/booking');

        $form = $crawler->filter('form')->form();
        $form->getPhpValues();
        $form->setValues(['date' => '']);
        $client->submit($form);

        static::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        static::assertSelectorTextContains('section#flash-messages', 'Bitte ein Datum auswählen.');
    }

    public function testStepDateFormSubmitErrorWithWrongDateFormat(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/booking');
        $form    = $crawler->filter('#form-date')->form();
        $form->getPhpValues();
        $form->setValues(['date' => 'March first last year']);
        $client->submit($form);

        static::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        static::assertSelectorTextContains('section#flash-messages', 'Ungültiges Datumsformat.');
    }

    public function testStepDateFormSubmitErrorWithDateInThePast(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/booking');
        $form    = $crawler->filter('#form-date')->form();
        $form->getPhpValues();
        $form->setValues(['date' => '2024-02-01']);
        $client->submit($form);

        static::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        static::assertSelectorTextContains('section#flash-messages', 'Datum kann nicht in der Vergangenheit liegen.');
    }

    public function testStepDateFormSubmitErrorWithNoBusinessDay(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/booking');
        $form    = $crawler->filter('#form-date')->form();
        $form->getPhpValues();
        $form->setValues(['date' => '2024-12-01']);
        $client->submit($form);

        static::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        static::assertSelectorTextContains('section#flash-messages', 'Dieses Datum ist nicht möglich.');
    }

    public function testStepDateFormSubmitErrorWithClosedBusinessDay(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/booking');
        $form    = $crawler->filter('#form-date')->form();
        $form->getPhpValues();
        // 2024-05-12 is a Sunday
        $form->setValues(['date' => '2024-05-12']);
        $client->submit($form);

        static::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        static::assertSelectorTextContains('section#flash-messages', 'Dieses Datum ist nicht möglich.');
    }

    public function testStepDateFormSubmitSuccessfullAndRedirect(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $date        = new \DateTimeImmutable('2024-05-10');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);

        $crawler = $client->request('GET', '/booking');
        $form    = $crawler->filter('#form-date')->form();
        $form->setValues(['date' => '2024-05-10']);
        $client->submit($form);

        static::assertResponseRedirects('/booking/' . $businessDay->getId() . '/room');
    }

    public function testStepRoomLogsErrorRedirectsWhenNoPricesAreDefined(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-04-30'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BasicFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($testUser);

        $date        = new \DateTimeImmutable('2024-05-10');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);
        $client->request('GET', '/booking/' . $businessDay->getId() . '/room');

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
            'No active Price found.',
            Level::fromName('critical')
        ));
    }

    public function testStepRoomRedirectsIfBusinessDayIsInThePast(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-04-30'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BasicFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($testUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);
        $client->request('GET', '/booking/' . $businessDay->getId() . '/room');

        static::assertResponseRedirects('/booking');

        /** @var Session $session */
        $session = $client->getRequest()->getSession();
        self::assertContains('Dieses Datum ist nicht mehr verfügbar.', $session->getFlashBag()->get('error'));
    }

    public function testStepRoomRedirectsWithClosedBusinessDay(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        // 2024-05-12 is a Sunday
        $date        = new \DateTimeImmutable('2024-05-12');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);
        $client->request('GET', '/booking/' . $businessDay->getId() . '/room');

        static::assertResponseRedirects('/booking');
        /** @var Session $session */
        $session = $client->getRequest()->getSession();
        self::assertContains('Dieses Datum ist nicht möglich.', $session->getFlashBag()->get('error'));
    }

    public function testStepRoomRendersTemplateOnGetRequest(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);
        $date        = new \DateTimeImmutable('2024-05-10');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);

        $crawler = $client->request('GET', '/booking/' . $businessDay->getId() . '/room');
        static::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('h1'));
    }

    public function testStepRoomTemplateContainsDatePickerAndSelect(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);
        $date        = new \DateTimeImmutable('2024-05-10');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);

        $crawler = $client->request('GET', '/booking/' . $businessDay->getId() . '/room');
        static::assertResponseIsSuccessful();
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
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);

        $crawler = $client->request('GET', '/booking/' . $businessDay->getId() . '/room');
        static::assertResponseIsSuccessful();
        $selectOption = $crawler->filter('option');
        self::assertSame('', $selectOption->attr('disabled'));
    }

    public function testStepRoomFormSubmitErrorWithInvalidCsrfToken(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);

        $crawler = $client->request('GET', '/booking/' . $businessDay->getId() . '/room');
        $form    = $crawler->filter('#form-room')->form();
        $form->setValues(['token' => 'invalid']);
        $client->submit($form);

        static::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        static::assertSelectorTextContains('section#flash-messages', 'Ungültiges CSRF-Token.');
    }

    public function testStepRoomFormSubmitErrorWithEmptyRoom(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

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

        static::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        static::assertSelectorTextContains('section#flash-messages', 'Bitte ein Zimmer auswählen.');
    }

    public function testStepRoomFormSubmitErrorWithWrongValueForRoom(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

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

        static::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        static::assertSelectorTextContains('section#flash-messages', 'Unbekanntes Zimmer ausgewählt.');
    }

    public function testStepRoomFormSubmitErrorWithNotExistingRoom(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);

        $uri     = '/booking/' . $businessDay->getId() . '/room';
        $crawler = $client->request('GET', $uri);
        $form    = $crawler->filter('#form-room')->form();
        $form->disableValidation();
        $form->setValues(['room' => 99999]);
        $client->submit($form);

        static::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        static::assertSelectorTextContains('section#flash-messages', 'Unbekanntes Zimmer ausgewählt.');
    }

    public function testStepRoomFormSubmitErrorWithNoCapacity(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

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

        static::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        static::assertSelectorTextContains('section#flash-messages', 'Dieses Zimmer ist ausgebucht.');
    }

    public function testStepRoomFormSubmitErrorFullyBooked(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);
        $room        = static::getContainer()->get(RoomRepository::class)->findOneBy(['name' => BasicFixtures::FULLY_BOOKED_ROOM]);

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

        static::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        static::assertSelectorTextContains('section#flash-messages', 'Dieses Zimmer ist ausgebucht.');
    }

    public function testStepRoomFormSubmitSuccessfullAndRedirect(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $date        = new \DateTimeImmutable('2024-04-01');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);
        $room        = static::getContainer()->get(RoomRepository::class)->findOneBy(['name' => BasicFixtures::ROOM_FOR_BOOKINGS]);

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
        static::assertResponseRedirects('/booking/' . $booking->getUuid() . '/payment');
    }

    public function testStepRoomFormSubmitSuccessfullNotifiesAdmin(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($testUser);

        $date        = new \DateTimeImmutable('2024-03-14');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);
        $room        = static::getContainer()->get(RoomRepository::class)->findOneBy(['name' => BasicFixtures::ROOM_FOR_BOOKINGS]);

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
        static::assertResponseRedirects();
        static::assertEmailCount(1);

        $email = $this->getMailerMessage();
        static::assertEmailSubjectContains($email, $businessDay->getDate()->format('d/m/Y'));
    }

    public function testCancelBookingLogsErrorWhenBookingIsNotFoundAndRedirects(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $uri = '/booking/77403q-959-850496-yt98r3ty34980/cancel';
        $client->request('POST', $uri, ['bookingId' => 9999]);

        static::assertResponseRedirects('/user/bookings');
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
            '77403q-959-850496-yt98r3ty34980',
            Level::fromName('error')
        ));
    }

    public function testCancelBookingChecksUser(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($testUser);

        $bookingUser = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $booking     = $this->getBookingToBeCancelled($bookingUser);

        $uri = '/booking/' . $booking->getUuid() . '/cancel';
        $client->request('POST', $uri, ['bookingId' => $booking->getId()]);

        static::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCancelBookingChecksBookingIdInUrlAndPostMatch(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($testUser);

        $booking = $this->getBookingToBeCancelled($testUser);

        $uri = '/booking/' . $booking->getUuid() . '/cancel';
        $client->request('POST', $uri, ['bookingId' => 99999]);

        static::assertResponseRedirects();

        $notCancelledBooking = static::getContainer()->get(BookingRepository::class)->find($booking->getId());
        self::assertNotNull($notCancelledBooking);

        /** @var Session $session */
        $session = $client->getRequest()->getSession();
        self::assertContains('Diese Buchung kann nicht storniert werden.', $session->getFlashBag()->get('error'));
    }

    public function testCancelBookingChecksBookingIsInTheFuture(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-04-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($testUser);

        $booking = $this->getBookingToBeCancelled($testUser);

        $uri = '/booking/' . $booking->getUuid() . '/cancel';
        $client->request('POST', $uri, ['bookingId' => $booking->getId()]);

        static::assertResponseRedirects();

        $notCancelledBooking = static::getContainer()->get(BookingRepository::class)->find($booking->getId());
        self::assertNotNull($notCancelledBooking);

        $limit = static::getContainer()->getParameter('time_limit_cancel_booking_days');
        /** @var Session $session */
        $session         = $client->getRequest()->getSession();
        $expectedMessage = sprintf('Buchungen können nur %d Tag(e) vorher storniert werden.', $limit);
        $errors          = $session->getFlashBag()->get('error');
        self::assertContains($expectedMessage, $errors);
    }

    public function testCancelBookingSuccessfullDeletesBookingFromDatabase(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client            = static::createClient();
        $databaseTool      = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $bookingRepository = static::getContainer()->get(BookingRepository::class);
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($testUser);

        $booking   = $this->getBookingToBeCancelled($testUser);
        $bookingId = $booking->getId();

        $uri = '/booking/' . $booking->getUuid() . '/cancel';
        $client->request('POST', $uri, ['bookingId' => $bookingId]);

        $deletedBooking = $bookingRepository->find($bookingId);
        self::assertNull($deletedBooking);
    }

    public function testCancelBookingSuccessfullRedirectsToUserBookings(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($testUser);

        $booking = $this->getBookingToBeCancelled($testUser);
        $uri     = '/booking/' . $booking->getUuid() . '/cancel';
        $client->request('POST', $uri, ['bookingId' => $booking->getId()]);

        static::assertResponseRedirects('/user/bookings');
    }

    public function testCancelBookingSuccessfullNotifiesAdmin(): void
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BookingFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($testUser);

        $booking     = $this->getBookingToBeCancelled($testUser);
        $bookingDate = $booking->getBusinessDay()->getDate();
        $uri         = '/booking/' . $booking->getUuid() . '/cancel';
        $client->request('POST', $uri, ['bookingId' => $booking->getId()]);

        static::assertResponseRedirects();
        self::assertNull($booking->getId());
        static::assertEmailCount(1);

        $email = $this->getMailerMessage();
        static::assertEmailTextBodyContains($email, (string) $bookingDate->format('d/m/Y'));
    }

    private function getBookingToBeCancelled(User $user): Booking
    {
        $date        = new \DateTimeImmutable(BookingFixtures::BOOKING_TO_BE_CANCELLED_DATE);
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);
        $room        = static::getContainer()->get(RoomRepository::class)->findOneBy(['name' => BasicFixtures::ROOM_FOR_BOOKINGS]);

        return static::getContainer()->get(BookingRepository::class)
                             ->findOneBy([
                                 'room'        => $room,
                                 'businessDay' => $businessDay,
                                 'user'        => $user,
                             ])
        ;
    }
}
