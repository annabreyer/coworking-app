<?php

declare(strict_types=1);

namespace App\Dto\Invoice;

final class InvoiceLineItem
{
    public function __construct(
        public readonly int $position,
        public readonly string $description,
        public readonly string $amount,
        public readonly ?string $codes = null,
    ) {
    }

    /**
     * @return array{position: int, description: string, amount: string, codes: string|null}
     */
    public function toArray(): array
    {
        return [
            'position'    => $this->position,
            'description' => $this->description,
            'amount'      => $this->amount,
            'codes'       => $this->codes,
        ];
    }
}
