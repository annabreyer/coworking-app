<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\Repository\UserActionsRepository;
use App\Repository\UserRepository;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UserActionSubscriberTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testOnKernelRequestDoesNothingForGetMethod(): void
    {
        $client = static::createClient();
        static::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
            'App\DataFixtures\AppFixtures',
        ]);

        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $client->loginUser($user);
        $client->request('GET', '/user/edit');

        $userAction = static::getContainer()->get(UserActionsRepository::class)->findOneBy([
            'requestUri' => '/user/edit',
            'user'       => $user]);

        self::assertNull($userAction);
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
        self::assertNull($userAction);
    }

    public function testOnKernelRequestDoesNothingForAdminAndSuperAdmin(): void
    {
        $client = static::createClient();
        static::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
            'App\DataFixtures\AppFixtures',
        ]);

        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@annabreyer.dev']);
        $client->loginUser($user);
        $client->request('POST', '/user/edit', ['testdata' => 'testdata']);

        $userAction = static::getContainer()->get(UserActionsRepository::class)->findOneBy([
            'requestUri' => '/user/edit',
            'user'       => $user]);

        self::assertNull($userAction);
    }

    public function testLoggedInUserPostActionIsSaved(): void
    {
        $client = static::createClient();
        static::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
            'App\DataFixtures\AppFixtures',
        ]);

        $user     = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $testdata = ['testdata' => 'testdata'];
        $client->loginUser($user);
        $client->request('POST', '/user/edit', $testdata);

        $userAction = static::getContainer()->get(UserActionsRepository::class)->findOneBy(['user' => $user]);
        self::assertNotNull($userAction);
        self::assertSame('/user/edit', $userAction->getRequestUri());
        self::assertSame($user, $userAction->getUser());
        self::assertSame($testdata, $userAction->getData());
    }

    public function testLoggedInUserPutActionIsSaved(): void
    {
        $client = static::createClient();
        static::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
            'App\DataFixtures\AppFixtures',
        ]);

        $user     = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $testdata = ['testdata' => 'testdata'];

        $client->loginUser($user);
        $client->request('PUT', '/user/edit', $testdata);

        $userAction = static::getContainer()->get(UserActionsRepository::class)->findOneBy(['user' => $user]);
        self::assertNotNull($userAction);
        self::assertSame('/user/edit', $userAction->getRequestUri());
        self::assertSame($user, $userAction->getUser());
        self::assertSame($testdata, $userAction->getData());
    }

    public function testLoggedInUserPatchActionIsSaved(): void
    {
        $client = static::createClient();
        static::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
            'App\DataFixtures\AppFixtures',
        ]);

        $user     = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $testdata = ['testdata' => 'testdata'];

        $client->loginUser($user);
        $client->request('PATCH', '/user/edit', $testdata);

        $userAction = static::getContainer()->get(UserActionsRepository::class)->findOneBy(['user' => $user]);
        self::assertNotNull($userAction);
        self::assertSame('/user/edit', $userAction->getRequestUri());
        self::assertSame($user, $userAction->getUser());
        self::assertSame($testdata, $userAction->getData());
    }

    public function testLoggedInUserDeleteActionIsSaved(): void
    {
        $client = static::createClient();
        static::getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
            'App\DataFixtures\AppFixtures',
        ]);

        $user     = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user.one@annabreyer.dev']);
        $testdata = ['testdata' => 'testdata'];

        $client->loginUser($user);
        $client->request('DELETE', '/user/edit', $testdata);

        $userAction = static::getContainer()->get(UserActionsRepository::class)->findOneBy(['user' => $user]);
        self::assertNotNull($userAction);
        self::assertSame('/user/edit', $userAction->getRequestUri());
        self::assertSame($user, $userAction->getUser());
        self::assertSame($testdata, $userAction->getData());
    }
}
