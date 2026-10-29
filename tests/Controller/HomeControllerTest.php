<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\BasicFixtures;
use App\DataFixtures\PriceFixtures;
use App\Repository\UserRepository;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HomeControllerTest extends WebTestCase
{
    public function testIndexRendersPricesOnGetRequest(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BasicFixtures::class, PriceFixtures::class]);

        $client->request('GET', '/');
        static::assertResponseIsSuccessful();
        static::assertSelectorTextContains('main', '10er-Ticket : 135 €');
        static::assertSelectorTextContains('main', '5er-Ticket : 70 €');
    }

    public function testIndexShowsHowToBookAndAuthButtonsWhenLoggedOut(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BasicFixtures::class, PriceFixtures::class]);

        $crawler = $client->request('GET', '/');
        static::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('#how-to-book'));
        self::assertCount(3, $crawler->filter('#how-to-book ol li'));
        self::assertCount(1, $crawler->filter('header a[href="/register"]'));
        self::assertCount(1, $crawler->filter('header a[href="/login"]'));
    }

    public function testIndexHidesHowToBookWhenLoggedIn(): void
    {
        $client       = static::createClient();
        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $databaseTool->loadFixtures([BasicFixtures::class, PriceFixtures::class]);

        $userRepository = static::getContainer()->get(UserRepository::class);
        $client->loginUser($userRepository->findOneBy(['email' => 'user.one@annabreyer.dev']));

        $crawler = $client->request('GET', '/');
        static::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('#how-to-book'));
        self::assertCount(0, $crawler->filter('header a[href="/register"]'));
    }
}
