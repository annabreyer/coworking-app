<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\BasicFixtures;
use App\DataFixtures\HalfDayPriceFixtures;
use App\DataFixtures\PriceFixtures;
use App\Entity\BookingType;
use App\Entity\Price;
use App\Repository\BookingTypeRepository;
use App\Repository\PriceRepository;
use App\Repository\VoucherTypeRepository;
use Doctrine\ORM\EntityManagerInterface;

class HomeControllerTest extends BaseTestController
{
    private const VOUCHER_TEXT = 'Eine Mehrfachkarte besteht aus Gutschein-Codes';

    public function testIndexRendersWithoutAnyPrices(): void
    {
        $client = $this->setupTestClient('2024-03-01', [BasicFixtures::class]);

        $client->request('GET', '/');

        static::assertResponseIsSuccessful();
        static::assertSelectorTextContains('h2', 'Preise');
        static::assertSelectorTextNotContains('body', 'Einzelbuchung');
        static::assertSelectorTextNotContains('body', self::VOUCHER_TEXT);
    }

    public function testIndexRendersWithoutActiveVoucherPrices(): void
    {
        $client = $this->setupTestClient('2024-03-01', [PriceFixtures::class]);
        $this->setPriceInactive('10er-Ticket');

        $client->request('GET', '/');

        static::assertResponseIsSuccessful();
        static::assertSelectorTextContains('h4', 'Einzelbuchung Ganztag: 15 €');
        static::assertSelectorTextNotContains('body', '10er-Ticket');
        static::assertSelectorTextNotContains('body', self::VOUCHER_TEXT);
    }

    public function testIndexRendersWithOneActiveVoucherPrice(): void
    {
        $client = $this->setupTestClient('2024-03-01', [PriceFixtures::class]);

        $crawler = $client->request('GET', '/');

        static::assertResponseIsSuccessful();
        static::assertSelectorTextContains('body', '10er-Ticket : 135 €');
        static::assertSelectorTextContains('body', self::VOUCHER_TEXT);
        static::assertCount(2, $crawler->filter('h4'));
    }

    public function testIndexRendersWithTwoActiveVoucherPrices(): void
    {
        $client = $this->setupTestClient('2024-03-01', [PriceFixtures::class]);

        $voucherType = $this->getRepository(VoucherTypeRepository::class)->findOneBy(['units' => 10]);
        $halfDayPrice = new Price();
        $halfDayPrice->setVoucherType($voucherType)
                     ->setAmount(8000)
                     ->setIsActive(true)
                     ->setBookingType($this->getRepository(BookingTypeRepository::class)->findOneBy(['name' => BookingType::NAME_HALF_DAY]))
                     ->setName('10er-Ticket halbe Tage');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($halfDayPrice);
        $entityManager->flush();

        $crawler = $client->request('GET', '/');

        static::assertResponseIsSuccessful();
        static::assertSelectorTextContains('body', '10er-Ticket : 135 €');
        static::assertSelectorTextContains('body', '10er-Ticket halbe Tage : 80 €');
        static::assertSelectorTextContains('body', self::VOUCHER_TEXT);
        static::assertCount(3, $crawler->filter('h4'));
    }

    public function testIndexRendersTheUnitaryPriceOfEachBookingType(): void
    {
        $this->setupTestClient('2024-03-01', [HalfDayPriceFixtures::class])->request('GET', '/');

        static::assertResponseIsSuccessful();
        static::assertSelectorTextContains('body', 'Einzelbuchung Ganztag: 15 €');
        static::assertSelectorTextContains('body', 'Einzelbuchung Halbtag: 12 €');
    }

    public function testIndexRendersWithoutActiveUnitaryPrice(): void
    {
        $client = $this->setupTestClient('2024-03-01', [PriceFixtures::class]);
        $this->setPriceInactive('Einzelticket');

        $client->request('GET', '/');

        static::assertResponseIsSuccessful();
        static::assertSelectorTextNotContains('body', 'Einzelbuchung');
        static::assertSelectorTextContains('h4', '10er-Ticket : 135 €');
    }

    public function testIndexShowsHowToBookAndAuthButtonsWhenLoggedOut(): void
    {
        $client = $this->setupTestClient('2024-03-01', [PriceFixtures::class]);

        $crawler = $client->request('GET', '/');

        static::assertResponseIsSuccessful();
        static::assertCount(1, $crawler->filter('#how-to-book'));
        static::assertCount(3, $crawler->filter('#how-to-book ol li'));
        static::assertCount(1, $crawler->filter('header a[href="/register"]'));
        static::assertCount(1, $crawler->filter('header a[href="/login"]'));
    }

    public function testIndexHidesHowToBookWhenLoggedIn(): void
    {
        $client = $this->setupTestClientAndLogin('2024-03-01', [BasicFixtures::class, PriceFixtures::class], 'user.one@annabreyer.dev');

        $crawler = $client->request('GET', '/');

        static::assertResponseIsSuccessful();
        static::assertCount(0, $crawler->filter('#how-to-book'));
        static::assertCount(0, $crawler->filter('header a[href="/register"]'));
    }

    private function setPriceInactive(string $name): void
    {
        $price = $this->getEntity(PriceRepository::class, ['name' => $name]);
        $price->setIsActive(false);

        static::getContainer()->get(EntityManagerInterface::class)->flush();
    }
}
