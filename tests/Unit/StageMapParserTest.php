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

    public function test_legacy_map_has_no_main_stage(): void
    {
        $stage = $this->stage();

        $this->assertNull($stage['mainStage']);
        $this->assertSame('horizontal', $stage['sections'][0]['rows'][0]['orientation']);
    }

    public function test_reads_main_stage_and_vertical_rows(): void
    {
        $stage = StageMapParser::parseXml(<<<'XML'
            <stage version="2">
                <image size="2000" height="1400" />
                <main_stage x="700" y="60" width="600" height="120" label="TARIMA" />
                <sections hasManyChildren="1">
                    <section name="VIP" section_id="VIP" locality_id="1" color="#544657" num="10" numx="5">
                        <rows>
                            <row y="3" fila="P" orientation="vertical"><seat seat_id="P1" x="3" /></row>
                            <row y="4" fila="P" orientation="vertical"><seat seat_id="P2" x="3" /></row>
                        </rows>
                    </section>
                </sections>
            </stage>
            XML);

        $this->assertSame(
            ['x' => 700, 'y' => 60, 'width' => 600, 'height' => 120, 'label' => 'TARIMA'],
            $stage['mainStage'],
        );
        $this->assertSame(['vertical', 'vertical'], array_column($stage['sections'][0]['rows'], 'orientation'));
        $this->assertSame(['x' => 5, 'y' => 10], $stage['sections'][0]['offset']);
    }

    public function test_reads_venue_objects(): void
    {
        $stage = StageMapParser::parseXml(
            '<stage><objects hasManyChildren="1">'
            .'<object kind="barra" shape="rect" x="40" y="60" width="200" height="60" rotation="15" label="BARRA" color="#8d5a2b" />'
            .'<object kind="columna" shape="ellipse" x="10" y="10" width="40" height="40" />'
            .'<object kind="otro" x="0" y="0" width="0" height="10" />'
            .'</objects><sections /></stage>'
        );

        $this->assertCount(2, $stage['objects']);
        $this->assertSame(
            ['kind' => 'barra', 'shape' => 'rect', 'x' => 40, 'y' => 60, 'width' => 200, 'height' => 60,
                'rotation' => 15.0, 'label' => 'BARRA', 'color' => '#8d5a2b'],
            $stage['objects'][0],
        );
        $this->assertSame('ellipse', $stage['objects'][1]['shape']);
        $this->assertSame([], StageMapParser::parseXml('<stage><sections /></stage>')['objects']);
    }

    public function test_reads_web_visibility(): void
    {
        $stage = StageMapParser::parseXml(
            '<stage><sections>'
            .'<section name="A" section_id="A" locality_id="1" capacity="10" web="true" />'
            .'<section name="B" section_id="B" locality_id="1" capacity="10" web="false" />'
            .'<section name="C" section_id="C" locality_id="1" capacity="10" />'
            .'</sections></stage>',
        );

        $this->assertSame([true, false, true], array_column($stage['sections'], 'web'));
    }

    public function test_reads_section_rotation(): void
    {
        $stage = StageMapParser::parseXml(
            '<stage><sections>'
            .'<section name="A" section_id="A" locality_id="1" rotation="-22.5"><rows><row y="3"><seat seat_id="A1" x="3" /></row></rows></section>'
            .'<section name="B" section_id="B" locality_id="1" capacity="10" />'
            .'</sections></stage>',
        );

        $this->assertSame([-22.5, 0.0], array_column($stage['sections'], 'rotation'));
    }

    public function test_main_stage_without_size_is_ignored(): void
    {
        $stage = StageMapParser::parseXml(
            '<stage><main_stage x="10" y="10" width="0" height="50" /><sections><section name="A" section_id="A" locality_id="1" capacity="10" /></sections></stage>',
        );

        $this->assertNull($stage['mainStage']);
    }

    public function test_reads_exact_positions_of_shaped_rows_and_labels(): void
    {
        $stage = StageMapParser::parseXml(
            '<stage><sections><section name="FAN" section_id="FAN" locality_id="1">'
            .'<labels><label name="A" x="1" y="3" px="180.4" py="300.6" /><label name="A" x="7" y="3" /></labels>'
            .'<rows><row y="3" fila="A" orientation="horizontal" shape="v">'
            .'<seat seat_id="A1" x="3" px="210.5" py="310.25" /><seat seat_id="A2" x="4" px="abc" py="12" /><seat seat_id="A3" x="5" />'
            .'</row></rows></section></sections></stage>',
        );

        [$first, $invalid, $plain] = $stage['sections'][0]['rows'][0]['seats'];
        $this->assertSame([210.5, 310.3], [$first['px'], $first['py']]);
        $this->assertArrayNotHasKey('px', $invalid);
        $this->assertArrayNotHasKey('px', $plain);
        $this->assertSame(3, $plain['x']);

        [$exactLabel, $gridLabel] = $stage['sections'][0]['labels'];
        $this->assertSame([180.4, 300.6], [$exactLabel['px'], $exactLabel['py']]);
        $this->assertArrayNotHasKey('px', $gridLabel);
    }

    public function test_reads_the_seat_fit_of_a_section(): void
    {
        $stage = StageMapParser::parseXml(
            '<stage><sections>'
            .'<section name="VIP" section_id="VIP" locality_id="1" seat_scale="1.25" fit_x="-40.5" fit_y="12">'
            .'<rows><row y="3" fila="A" orientation="horizontal"><seat seat_id="A1" x="3" /></row></rows></section>'
            .'<section name="ORO" section_id="ORO" locality_id="2" seat_scale="abc" fit_x="1" fit_y="2">'
            .'<rows><row y="3" fila="A" orientation="horizontal"><seat seat_id="A1" x="3" /></row></rows></section>'
            .'</sections></stage>',
        );

        $this->assertSame(['scale' => 1.25, 'x' => -40.5, 'y' => 12.0], $stage['sections'][0]['fit']);
        $this->assertNull($stage['sections'][1]['fit']);
    }
}
