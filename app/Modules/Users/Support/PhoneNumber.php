<?php

namespace App\Modules\Users\Support;

final class PhoneNumber
{
    /**
     * Número en formato E.164 a partir del número nacional y la lada del país.
     */
    public static function e164(string $phone, string $countryCode = '52'): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        $country = preg_replace('/\D/', '', $countryCode) ?: '52';

        if (! str_starts_with($digits, $country) || strlen($digits) <= 10) {
            $digits = $country.ltrim($digits, '0');
        }

        return '+'.$digits;
    }
}
