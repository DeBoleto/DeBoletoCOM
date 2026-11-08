<?php

namespace App\Modules\BoxOffice\Support;

class Money
{
    public static function format(float|int|string|null $amount): string
    {
        return number_format((float) $amount, 2, '.', ',');
    }

    public static function round(float $amount): float
    {
        return round($amount, 2);
    }
}
