<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\BasicFixtures;
use App\DataFixtures\PriceFixtures;
use App\Entity\Price;
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
        static::assertSelectorTextNotContains('body', 'Einzelbuchungen:');
        static::assertSelectorTextNotContains('body', self::VOUCHER_TEXT);
    }

    public function testIndexRendersWithoutActiveVoucherPrices(): void
    {
        $client = $this->setupTestClient('2024-03-01', [PriceFixtures::class]);
        $this->setPriceInactive('10er-Ticket');

        $client->request('GET', '/');

        static::assertResponseIsSuccessful();
        static::assertSelectorTextContains('h4', 'Einzelbuchungen: 15 €');
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

    public function testIndexRendersWithoutActiveUnitaryPrice(): void
    {
        $client = $this->setupTestClient('2024-03-01', [PriceFixtures::class]);
        $this->setPriceInactive('Einzelticket');

        $client->request('GET', '/');

        static::assertResponseIsSuccessful();
        static::assertSelectorTextNotContains('body', 'Einzelbuchungen:');
        static::assertSelectorTextContains('h4', '10er-Ticket : 135 €');
    }

    private function setPriceInactive(string $name): void
    {
        $price = $this->getEntity(PriceRepository::class, ['name' => $name]);
        $price->setIsActive(false);

        static::getContainer()->get(EntityManagerInterface::class)->flush();
    }
}
