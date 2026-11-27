<?php

namespace App\Support\Seo;

use Illuminate\Support\Carbon;

/**
 * Estructuras schema.org (JSON-LD) de las páginas públicas.
 */
final class SchemaOrg
{
    public const TIMEZONE = 'America/Mexico_City';

    public static function organization(): array
    {
        return [
            '@type' => 'Organization',
            'name' => config('app.name'),
            'url' => url('/'),
            'logo' => asset('deboletoIcono.png'),
        ];
    }

    public static function website(): array
    {
        return [
            '@type' => 'WebSite',
            'name' => config('app.name'),
            'url' => url('/'),
            'inLanguage' => 'es-MX',
        ];
    }

    /**
     * Un Event por función (Google pide uno por fecha), a partir de los props
     * que arma la ruta event.detail.
     *
     * @return list<array>
     */
    public static function events(array $event, string $url, ?string $image, string $city = '', string $state = ''): array
    {
        $offers = self::offers($event, $url);
        $location = self::place((string) ($event['venueName'] ?? ''), $city, $state, $event['latitude'] ?? null, $event['longitude'] ?? null);
        $description = SeoMeta::clean((string) ($event['desc'] ?? ''), 500);

        return collect($event['functions'] ?? [])
            ->map(fn ($function) => self::date($function['date'] ?? null))
            ->filter()
            ->unique()
            ->take(20)
            ->map(fn ($startDate) => array_filter([
                '@type' => 'Event',
                'name' => $event['name'] ?? '',
                'description' => $description,
                'startDate' => $startDate,
                'eventStatus' => 'https://schema.org/EventScheduled',
                'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
                'image' => $image ? [SeoMeta::absolute($image)] : null,
                'url' => $url,
                'location' => $location,
                'offers' => $offers,
                'organizer' => ['@type' => 'Organization', 'name' => config('app.name'), 'url' => url('/')],
            ], fn ($value) => $value !== null && $value !== '' && $value !== []))
            ->values()
            ->all();
    }

    public static function place(string $name, string $city = '', string $state = '', $latitude = null, $longitude = null, string $address = ''): ?array
    {
        if ($name === '') {
            return null;
        }

        $place = [
            '@type' => 'Place',
            'name' => $name,
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $address,
                'addressLocality' => $city,
                'addressRegion' => $state,
                'addressCountry' => 'MX',
            ], fn ($value) => $value !== ''),
        ];

        if (is_numeric($latitude) && is_numeric($longitude) && ((float) $latitude !== 0.0 || (float) $longitude !== 0.0)) {
            $place['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude,
            ];
        }

        return $place;
    }

    private static function offers(array $event, string $url): ?array
    {
        if (empty($event['ventaWeb'])) {
            return null;
        }

        $prices = collect($event['zones'] ?? [])
            ->map(function ($zone) {
                $discount = (float) ($zone['discountPrice'] ?? 0);

                return $discount > 0 ? $discount : (float) ($zone['originalPrice'] ?? 0);
            })
            ->filter(fn ($price) => $price > 0);

        if ($prices->isEmpty()) {
            return null;
        }

        return [
            '@type' => 'AggregateOffer',
            'priceCurrency' => 'MXN',
            'lowPrice' => round($prices->min(), 2),
            'highPrice' => round($prices->max(), 2),
            'offerCount' => $prices->count(),
            'availability' => 'https://schema.org/InStock',
            'url' => $url,
        ];
    }

    private static function date($value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return rescue(fn () => Carbon::parse($value, self::TIMEZONE)->toIso8601String(), null, false);
    }
}
