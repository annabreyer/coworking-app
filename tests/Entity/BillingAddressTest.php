<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\BillingAddress;
use App\Entity\Invoice;
use PHPUnit\Framework\TestCase;

class BillingAddressTest extends TestCase
{
    public function testNewBillingAddressIsEmptyAndNotComplete(): void
    {
        $billingAddress = new BillingAddress();

        self::assertTrue($billingAddress->isEmpty());
        self::assertFalse($billingAddress->isComplete());
    }

    public function testBillingAddressWithBlankFieldsIsEmpty(): void
    {
        $billingAddress = new BillingAddress();
        $billingAddress->setName('  ')->setCity('');

        self::assertTrue($billingAddress->isEmpty());
    }

    public function testPartialBillingAddressIsNeitherEmptyNorComplete(): void
    {
        $billingAddress = new BillingAddress();
        $billingAddress->setName('ACME GmbH')->setStreet('Hauptstraße 1');

        self::assertFalse($billingAddress->isEmpty());
        self::assertFalse($billingAddress->isComplete());
    }

    public function testFullBillingAddressIsComplete(): void
    {
        self::assertTrue($this->getCompleteBillingAddress()->isComplete());
    }

    public function testInvoiceKeepsACopyOfTheBillingAddress(): void
    {
        $billingAddress = $this->getCompleteBillingAddress();
        $invoice        = new Invoice();
        $invoice->setBillingAddress($billingAddress);

        $billingAddress->setName('Other GmbH');

        self::assertSame('ACME GmbH', $invoice->getBillingAddress()->getName());
    }

    private function getCompleteBillingAddress(): BillingAddress
    {
        $billingAddress = new BillingAddress();

        return $billingAddress->setName('ACME GmbH')
                              ->setStreet('Hauptstraße 1')
                              ->setPostCode('55262')
                              ->setCity('Hahnheim')
        ;
    }
}
