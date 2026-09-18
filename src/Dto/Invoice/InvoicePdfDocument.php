<?php

declare(strict_types=1);

namespace App\Dto\Invoice;

final class InvoicePdfDocument
{
    /**
     * @var InvoiceLineItem[]
     */
    private array $items = [];

    private bool $showTotal = false;

    private ?string $totalAmount = null;

    private ?string $paymentMessage = null;

    public function __construct(
        public readonly string $headerLogo,
        public readonly string $vielenDank,
        public readonly string $invoiceNumber,
        public readonly string $invoiceDate,
        public readonly string $clientNumber,
        public readonly string $clientName,
        public readonly string $invoiceType,
        public readonly string $introText,
        public readonly ?string $clientStreet,
        public readonly ?string $clientPostCodeAndCity,
        public readonly ?string $clientEmail,
    ) {
    }

    public function addItem(InvoiceLineItem $item): void
    {
        $this->items[] = $item;
    }

    /**
     * @param InvoiceLineItem[] $items
     */
    public function addItems(array $items): void
    {
        foreach ($items as $item) {
            $this->addItem($item);
        }
    }

    public function setTotal(string $totalAmount): void
    {
        $this->showTotal   = true;
        $this->totalAmount = $totalAmount;
    }

    public function setPaymentMessage(?string $paymentMessage): void
    {
        $this->paymentMessage = $paymentMessage;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'headerLogo'            => $this->headerLogo,
            'vielenDank'            => $this->vielenDank,
            'invoiceNumber'         => $this->invoiceNumber,
            'invoiceDate'           => $this->invoiceDate,
            'clientNumber'          => $this->clientNumber,
            'clientName'            => $this->clientName,
            'invoiceType'           => $this->invoiceType,
            'introText'             => $this->introText,
            'clientStreet'          => $this->clientStreet,
            'clientPostCodeAndCity' => $this->clientPostCodeAndCity,
            'clientEmail'           => $this->clientEmail,
            'items'                 => array_map(static fn (InvoiceLineItem $item) => $item->toArray(), $this->items),
            'showTotal'             => $this->showTotal,
            'totalAmount'           => $this->totalAmount,
            'paymentMessage'        => $this->paymentMessage,
        ];
    }
}
