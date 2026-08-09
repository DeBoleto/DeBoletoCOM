<?php

namespace App\Support;

class VenueNameNormalizer
{
    public static function normalize(string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', $name));
    }

    public static function slugify(string $name): string
    {
        $text = mb_strtolower(self::normalize($name));
        $text = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü'],
            ['a', 'e', 'i', 'o', 'u', 'n', 'u'],
            $text
        );
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);

        return trim($text, '-');
    }
}
