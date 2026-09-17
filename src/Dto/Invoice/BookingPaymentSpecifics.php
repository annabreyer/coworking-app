<?php

declare(strict_types=1);

namespace App\Dto\Invoice;

final class BookingPaymentSpecifics
{
    /**
     * @param InvoiceLineItem[] $extraItems
     */
    public function __construct(
        public readonly array $extraItems,
        public readonly float $totalAmount,
        public readonly ?string $paymentMessage,
    ) {
    }
}
