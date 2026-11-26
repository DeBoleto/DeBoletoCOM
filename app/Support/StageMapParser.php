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
                        'orientation' => (string) $row['orientation'] === 'vertical' ? 'vertical' : 'horizontal',
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
                // web="false": la sección solo se vende en taquilla.
                'web' => strtolower(trim((string) ($section['web'] ?? ''))) !== 'false',
                'offset' => [
                    'x' => (int) ($section['numx'] ?? 0),
                    'y' => (int) ($section['num'] ?? 0),
                ],
                // Grados que se giran los asientos en el mapa completo (editor v2).
                'rotation' => (float) ($section['rotation'] ?? 0),
                'price' => self::matchPrice($localityId, $zones),
            ];
            $sectionIndex++;
        }

        return [
            'canvas' => (int) ($xml->image['size'] ?? 2000),
            'mainStage' => self::mainStage($xml),
            'objects' => self::objects($xml),
            'sections' => $sections,
        ];
    }

    /**
     * Escenario principal (<main_stage>) en px del mapa general. Los mapas
     * anteriores no lo traen y el mapa de zonas lo calcula.
     */
    private static function mainStage(SimpleXMLElement $xml): ?array
    {
        if (! isset($xml->main_stage)) {
            return null;
        }

        $width = (int) $xml->main_stage['width'];
        $height = (int) $xml->main_stage['height'];

        if ($width <= 0 || $height <= 0) {
            return null;
        }

        return [
            'x' => (int) $xml->main_stage['x'],
            'y' => (int) $xml->main_stage['y'],
            'width' => $width,
            'height' => $height,
            'label' => trim((string) $xml->main_stage['label']) ?: 'ESCENARIO',
        ];
    }

    /**
     * Objetos del recinto (<objects>: barras, baños, entradas, textos…) en px
     * del mapa general. Solo se dibujan; no se venden.
     */
    private static function objects(SimpleXMLElement $xml): array
    {
        if (! isset($xml->objects)) {
            return [];
        }

        $objects = [];

        foreach ($xml->objects->object as $object) {
            $width = (int) $object['width'];
            $height = (int) $object['height'];

            if ($width <= 0 || $height <= 0) {
                continue;
            }

            $objects[] = [
                'kind' => (string) ($object['kind'] ?? 'otro'),
                'shape' => (string) $object['shape'] === 'ellipse' ? 'ellipse' : 'rect',
                'x' => (int) $object['x'],
                'y' => (int) $object['y'],
                'width' => $width,
                'height' => $height,
                'rotation' => (float) ($object['rotation'] ?? 0),
                'label' => trim((string) $object['label']),
                'color' => (string) ($object['color'] ?? '') ?: '#7048e8',
            ];
        }

        return $objects;
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
