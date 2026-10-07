<?php

namespace App\Support;

use RuntimeException;

class StageMapParser
{
    public static function parse(string $path, array $zones = []): array
    {
        $xml = simplexml_load_file($path);

        if ($xml === false) {
            throw new RuntimeException('No se pudo leer el mapa del escenario.');
        }

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
                        $seats[] = [
                            'id' => (string) $seat['seat_id'],
                            'x' => (int) $seat['x'],
                            'y' => $rowY,
                            'status' => self::demoStatus($seatIndex),
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
