<?php

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;

class StageMapParser
{
    public const ACCESSIBLE_SEAT = 'disability';

    public static function parse(string $path, array $zones = []): array
    {
        $xml = simplexml_load_file($path, null, LIBXML_NONET);

        if ($xml === false) {
            throw new RuntimeException('No se pudo leer el mapa del escenario.');
        }

        return self::build($xml, $zones, fn (int $index) => self::demoStatus($index));
    }

    /**
     * Interpreta el XML del mapa guardado en base de datos (escenario_xml).
     * Todos los asientos inician como disponibles; el estado real se resuelve
     * después contra los boletos emitidos.
     */
    public static function parseXml(string $xml, array $zones = []): array
    {
        $document = trim($xml) === '' ? false : simplexml_load_string($xml, null, LIBXML_NONET | LIBXML_NOCDATA);

        if ($document === false) {
            throw new RuntimeException('El mapa del escenario no es un XML válido.');
        }

        return self::build($document, $zones, fn () => 'disponible');
    }

    private static function build(SimpleXMLElement $xml, array $zones, callable $statusFor): array
    {
        $seatIndex = 0;
        $sectionIndex = 0;
        $sections = [];

        foreach ($xml->sections->section as $section) {
            $localityId = (string) $section['locality_id'];
            $rows = [];

            if (isset($section->rows)) {
                foreach ($section->rows->row as $row) {
                    $seats = [];
                    $rowY = (int) $row['y'];

                    foreach ($row->seat as $seat) {
                        $seatId = (string) $seat['seat_id'];
                        $accessible = $seatId === self::ACCESSIBLE_SEAT;

                        $seats[] = [
                            'id' => $accessible ? self::ACCESSIBLE_SEAT.'-'.$seatIndex : $seatId,
                            'x' => (int) $seat['x'],
                            'y' => $rowY,
                            'status' => $accessible ? 'no_disponible' : $statusFor($seatIndex),
                            'selectable' => ! $accessible,
                        ];
                        $seatIndex++;
                    }

                    $rows[] = [
                        'y' => $rowY,
                        'fila' => (string) $row['fila'],
                        'seats' => $seats,
                    ];
                }
            }

            $labels = [];

            if (isset($section->labels)) {
                foreach ($section->labels->label as $label) {
                    $labels[] = [
                        'name' => (string) $label['name'],
                        'x' => (int) $label['x'],
                        'y' => (int) $label['y'],
                    ];
                }
            }

            $stage = null;

            if (isset($section->stage_location)) {
                $stage = [
                    'x1' => (int) $section->stage_location['x1'],
                    'y1' => (int) $section->stage_location['y1'],
                    'x2' => (int) $section->stage_location['x2'],
                    'y2' => (int) $section->stage_location['y2'],
                ];
            }

            $polygon = [];
            $areaColor = (string) ($section['color'] ?? '#cccccc');
            $areaName = '';

            if (isset($section->area)) {
                $areaColor = (string) ($section->area['color'] ?: $areaColor);
                $areaName = (string) ($section->area['name'] ?? '');

                foreach ($section->area->vertex as $vertex) {
                    $polygon[] = [
                        'x' => (int) $vertex['x'],
                        'y' => (int) $vertex['y'],
                    ];
                }
            }

            $sections[] = [
                'key' => (string) $sectionIndex,
                'name' => (string) $section['name'],
                'sectionId' => (string) $section['section_id'],
                'localityId' => $localityId,
                'color' => (string) ($section['color'] ?? '#cccccc'),
                'areaName' => $areaName,
                'areaColor' => $areaColor,
                'polygon' => $polygon,
                'stage' => $stage,
                'labels' => $labels,
                'rows' => $rows,
                'capacity' => isset($section['capacity']) ? (int) $section['capacity'] : null,
                'price' => self::matchPrice($localityId, $zones),
            ];
            $sectionIndex++;
        }

        return [
            'canvas' => (int) ($xml->image['size'] ?? 2000),
            'sections' => $sections,
        ];
    }

    private static function matchPrice(string $localityId, array $zones): ?array
    {
        foreach ($zones as $zone) {
            if ((string) ($zone['id'] ?? '') !== $localityId) {
                continue;
            }

            return [
                'name' => (string) ($zone['name'] ?? ''),
                'originalPrice' => isset($zone['originalPrice']) ? (float) $zone['originalPrice'] : null,
                'discountPrice' => isset($zone['discountPrice']) ? (float) $zone['discountPrice'] : null,
                'hasPromotion' => (bool) ($zone['hasPromotion'] ?? false),
            ];
        }

        return null;
    }

    private static function demoStatus(int $index): string
    {
        return match ($index % 11) {
            0 => 'vendido',
            1 => 'no_disponible',
            2 => 'en_proceso',
            default => 'disponible',
        };
    }
}
