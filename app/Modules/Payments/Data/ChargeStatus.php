<?php

namespace App\Modules\Payments\Data;

enum ChargeStatus: string
{
    case InProgress = 'in_progress';
    case Pending = 'charge_pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case ChargedBack = 'chargeback_accepted';

    public static function fromGateway(?string $status): self
    {
        return self::tryFrom((string) $status) ?? self::InProgress;
    }

    public function isFinalFailure(): bool
    {
        return in_array($this, [self::Failed, self::Cancelled], true);
    }
}
