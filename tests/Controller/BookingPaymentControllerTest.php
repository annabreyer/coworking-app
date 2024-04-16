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
