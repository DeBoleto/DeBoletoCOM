<?php

namespace App\Modules\Events\Enums;

/**
 * Catálogo eventos.pk_evento_comision_tipo.
 */
enum CommissionType: int
{
    case Percentage = 1;
    case Fixed = 2;
    case PercentageNoCardFee = 3;
    case FixedNoCardFee = 4;

    public function isPercentage(): bool
    {
        return $this === self::Percentage || $this === self::PercentageNoCardFee;
    }
}
