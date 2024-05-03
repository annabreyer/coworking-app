<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\DataFixtures\BasicFixtures;
use App\DataFixtures\PriceFixtures;
use App\Repository\BusinessDayRepository;
use App\Repository\UserActionsRepository;
use App\Repository\UserRepository;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

class UserActionSubscriberTest extends WebTestCase
{
    use ClockSensitiveTrait;

    public function testOnKernelRequestDoesNothingForGetMethod(): void
    {
        $client = static::createClient();
        static::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
            BasicFixtures::class,
        ]);

        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($user);
        $client->request('GET', '/user/edit');

        $userAction = static::getContainer()->get(UserActionsRepository::class)->findOneBy([
            'requestUri' => '/user/edit',
            'user'       => $user]);

        static::assertNull($userAction);
    }

    public function testOnKernelRequestDoesNothingForUnauthenticatedUser(): void
    {
        $client   = static::createClient();
        $testdata = ['testdata' => 'testdata'];
        $client->request('POST', '/user/edit', $testdata);

        $user       = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $userAction = static::getContainer()->get(UserActionsRepository::class)->findOneBy([
            'requestUri' => '/user/edit',
            'user'       => $user]);
        static::assertNull($userAction);
    }

    public function testOnKernelRequestDoesNothingForAdminAndSuperAdmin(): void
    {
        $client = static::createClient();
        static::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
            BasicFixtures::class,
        ]);

        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($user);
        $client->request('POST', '/user/edit', ['testdata' => 'testdata']);

        $userAction = static::getContainer()->get(UserActionsRepository::class)->findOneBy([
            'requestUri' => '/user/edit',
            'user'       => $user]);

        static::assertNull($userAction);
    }

    public function testLoggedInUserPostActionIsSaved(): void
    {
        $client = static::createClient();
        static::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
            BasicFixtures::class,
        ]);

        $user     = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $testdata = ['testdata' => 'testdata'];
        $client->loginUser($user);
        $client->request('POST', '/user/edit', $testdata);

        $userAction = static::getContainer()->get(UserActionsRepository::class)->findOneBy(['user' => $user]);
        static::assertNotNull($userAction);
        static::assertSame('/user/edit', $userAction->getRequestUri());
        static::assertSame($user, $userAction->getUser());
        static::assertSame($testdata, $userAction->getData());
        static::assertSame('POST', $userAction->getMethod());
    }

    public function testLoggedInUserPutActionIsSaved(): void
    {
        $client = static::createClient();
        static::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
            BasicFixtures::class,
        ]);

        $user     = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $testdata = ['testdata' => 'testdata'];

        $client->loginUser($user);
        $client->request('PUT', '/user/edit', $testdata);

        $userAction = static::getContainer()->get(UserActionsRepository::class)->findOneBy(['user' => $user]);
        static::assertNotNull($userAction);
        static::assertSame('/user/edit', $userAction->getRequestUri());
        static::assertSame($user, $userAction->getUser());
        static::assertSame($testdata, $userAction->getData());
        static::assertSame('PUT', $userAction->getMethod());
    }

    public function testLoggedInUserPatchActionIsSaved(): void
    {
        $client = static::createClient();
        static::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
            BasicFixtures::class,
        ]);

        $user     = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $testdata = ['testdata' => 'testdata'];

        $client->loginUser($user);
        $client->request('PATCH', '/user/edit', $testdata);

        $userAction = static::getContainer()->get(UserActionsRepository::class)->findOneBy(['user' => $user]);
        static::assertNotNull($userAction);
        static::assertSame('/user/edit', $userAction->getRequestUri());
        static::assertSame($user, $userAction->getUser());
        static::assertSame($testdata, $userAction->getData());
        static::assertSame('PATCH', $userAction->getMethod());
    }

    public function testLoggedInUserDeleteActionIsSaved(): void
    {
        $client = static::createClient();
        static::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
            BasicFixtures::class,
        ]);

        $user     = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $testdata = ['testdata' => 'testdata'];

        $client->loginUser($user);
        $client->request('DELETE', '/user/edit', $testdata);

        $userAction = static::getContainer()->get(UserActionsRepository::class)->findOneBy(['user' => $user]);
        static::assertNotNull($userAction);
        static::assertSame('/user/edit', $userAction->getRequestUri());
        static::assertSame($user, $userAction->getUser());
        static::assertSame($testdata, $userAction->getData());
        static::assertSame('DELETE', $userAction->getMethod());
    }

    public function testExcludedUriIsExcluded()
    {
        static::mockTime(new \DateTimeImmutable('2024-03-01'));
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $databaseTool->loadFixtures([
            BasicFixtures::class,
            PriceFixtures::class,
        ]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser       = $userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($testUser);

        $date        = new \DateTimeImmutable('2024-05-10');
        $businessDay = static::getContainer()->get(BusinessDayRepository::class)->findOneBy(['date' => $date]);

        $crawler = $client->request('GET', '/booking');
        $form    = $crawler->filter('#form-date')->form();
        $form->setValues(['date' => '2024-05-10']);
        $client->submit($form);

        static::assertResponseRedirects('/booking/' . $businessDay->getId() . '/room');
        $userAction = static::getContainer()->get(UserActionsRepository::class)->findOneBy(['user' => $testUser]);
        static::assertNull($userAction);
    }
}
