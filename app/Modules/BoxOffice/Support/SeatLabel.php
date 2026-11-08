<?php

namespace App\Modules\BoxOffice\Support;

/**
 * Interpreta el texto de asiento guardado en el boleto.
 *  - "NA_NA…"  admisión general
 *  - "M12-3"   mesa 12, asiento 3
 *  - "B14"     fila B, asiento 14
 */
class SeatLabel
{
    /**
     * @return list<array{label: string, value: string}>
     */
    public static function parts(string $seat): array
    {
        if (str_starts_with($seat, 'NA_NA')) {
            return [];
        }

        if (str_contains($seat, '-')) {
            [$table, $chair] = explode('-', $seat, 2);

            return [['label' => 'Mesa', 'value' => $table], ['label' => 'Asiento', 'value' => $chair]];
        }

        return [['label' => 'Fila', 'value' => substr($seat, 0, 1)], ['label' => 'Asiento', 'value' => substr($seat, 1)]];
    }

    /**
     * Parte del asiento antes del primer dígito ("AB12" → "AB").
     */
    public static function row(string $seat): string
    {
        return preg_match('/\d/', $seat, $matches, PREG_OFFSET_CAPTURE) ? substr($seat, 0, $matches[0][1]) : $seat;
    }

    /**
     * Parte del asiento desde el primer dígito ("AB12" → "12").
     */
    public static function number(string $seat): string
    {
        return preg_match('/\d/', $seat, $matches, PREG_OFFSET_CAPTURE) ? substr($seat, $matches[0][1]) : '';
    }
}
