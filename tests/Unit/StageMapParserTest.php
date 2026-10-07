<?php

namespace Tests\Unit;

use App\Support\StageMapParser;
use PHPUnit\Framework\TestCase;

class StageMapParserTest extends TestCase
{
    private function stage(): array
    {
        return StageMapParser::parse(dirname(__DIR__, 2).'/PosadaConSabor.xml');
    }

    public function test_parses_canvas_and_sections(): void
    {
        $stage = $this->stage();

        $this->assertSame(2000, $stage['canvas']);
        $this->assertCount(5, $stage['sections']);

        $keys = array_column($stage['sections'], 'key');
        $this->assertSame(['0', '1', '2', '3', '4'], $keys);

        $fan = $stage['sections'][0];
        $this->assertSame('ZONA FAN', $fan['name']);
        $this->assertSame('10111', $fan['localityId']);
        $this->assertSame('#016839', $fan['areaColor']);
        $this->assertCount(4, $fan['polygon']);
        $this->assertSame(486, $fan['polygon'][0]['x']);
        $this->assertSame(757, $fan['polygon'][0]['y']);
        $this->assertNotNull($fan['stage']);

        $oro = $stage['sections'][1];
        $this->assertSame('ORO', $oro['name']);
        $this->assertSame('10111', $oro['localityId']);
        $this->assertSame([], $oro['rows']);
        $this->assertSame(492, $oro['polygon'][0]['x']);
        $this->assertSame(266, $oro['polygon'][0]['y']);

        $this->assertSame('13426', $stage['sections'][2]['localityId']);
        $this->assertSame('BALCON 1ER IZQ', $stage['sections'][2]['name']);
        $this->assertSame('BALCON 1ER IZQ', $stage['sections'][4]['name']);
        $this->assertNotSame($stage['sections'][2]['key'], $stage['sections'][4]['key']);
    }

    public function test_zona_fan_seats_skip_missing_e13(): void
    {
        $fan = $this->stage()['sections'][0];
        $ids = [];

        foreach ($fan['rows'] as $row) {
            foreach ($row['seats'] as $seat) {
                $ids[] = $seat['id'];
            }
        }

        $this->assertSame(['A', 'B', 'C', 'D', 'E'], array_column($fan['rows'], 'fila'));
        $this->assertCount(99, $ids);
        $this->assertContains('A1', $ids);
        $this->assertContains('E12', $ids);
        $this->assertContains('E14', $ids);
        $this->assertNotContains('E13', $ids);
        $this->assertSame(4, $fan['rows'][0]['seats'][0]['x']);
        $this->assertSame(4, $fan['rows'][0]['seats'][0]['y']);
    }

    public function test_demo_statuses_cycle_by_index(): void
    {
        $seats = $this->stage()['sections'][0]['rows'][0]['seats'];

        $this->assertSame('vendido', $seats[0]['status']);
        $this->assertSame('no_disponible', $seats[1]['status']);
        $this->assertSame('en_proceso', $seats[2]['status']);
        $this->assertSame('disponible', $seats[3]['status']);
        $this->assertSame('vendido', $seats[11]['status']);
    }

    public function test_matches_price_by_locality_id(): void
    {
        $stage = StageMapParser::parse(dirname(__DIR__, 2).'/PosadaConSabor.xml', [
            [
                'id' => 10111,
                'name' => 'Zona Fan',
                'originalPrice' => 500,
                'discountPrice' => 450,
                'hasPromotion' => true,
            ],
        ]);

        $fanPrice = $stage['sections'][0]['price'];
        $this->assertSame('Zona Fan', $fanPrice['name']);
        $this->assertSame(500.0, $fanPrice['originalPrice']);
        $this->assertSame(450.0, $fanPrice['discountPrice']);
        $this->assertTrue($fanPrice['hasPromotion']);
        $this->assertSame(450.0, $stage['sections'][1]['price']['discountPrice']);
        $this->assertNull($stage['sections'][2]['price']);
        $this->assertNull($this->stage()['sections'][0]['price']);
    }
}
