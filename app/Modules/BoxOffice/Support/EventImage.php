<?php

namespace App\Modules\BoxOffice\Support;

class EventImage
{
    public static function url(?string $image): ?string
    {
        if (empty($image)) {
            return null;
        }

        return rtrim(config('boxoffice.event_images_url'), '/').'/'.rawurlencode($image);
    }
}
