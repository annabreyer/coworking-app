<?php

declare(strict_types=1);

namespace App\Dto\Withdrawal;

final class WithdrawalResult
{
    public const REFUND_METHOD_NONE        = 'none';
    public const REFUND_METHOD_REVERSAL    = 'reversal';
    public const REFUND_METHOD_VOUCHER     = 'voucher';
    public const REFUND_METHOD_PAYPAL      = 'paypal';
    public const REFUND_METHOD_TRANSACTION = 'transaction';

    public const REJECTION_PERFORMED         = 'performed';
    public const REJECTION_ALREADY_CANCELLED = 'already_cancelled';
    public const REJECTION_VOUCHERS_USED     = 'vouchers_used';

    public function __construct(
        public readonly string $status,
        public readonly string $refundMethod = self::REFUND_METHOD_NONE,
        public readonly int $refundAmount = 0,
        public readonly int $valueCompensation = 0,
        public readonly ?string $rejectionReason = null,
    ) {
    }

    public function needsManualRefund(): bool
    {
        return 0 < $this->refundAmount
            && \in_array($this->refundMethod, [self::REFUND_METHOD_PAYPAL, self::REFUND_METHOD_TRANSACTION], true);
    }
}
