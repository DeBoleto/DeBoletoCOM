<?php

namespace App\Modules\BoxOffice\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Formato de fechas en español independiente del locale del servidor.
 */
class SpanishDate
{
    private const DAYS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

    private const MONTHS = [
        1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
    ];

    /**
     * "Viernes 07 de Noviembre de 2026".
     */
    public static function long(DateTimeInterface|string|null $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        $carbon = $date instanceof DateTimeInterface
            ? Carbon::instance($date)
            : Carbon::parse(substr($date, 0, 10));

        return sprintf(
            '%s %s de %s de %s',
            self::DAYS[$carbon->dayOfWeek],
            $carbon->format('d'),
            self::MONTHS[$carbon->month],
            $carbon->format('Y'),
        );
    }

    public static function month(int $month): string
    {
        return self::MONTHS[$month] ?? '';
    }
}
