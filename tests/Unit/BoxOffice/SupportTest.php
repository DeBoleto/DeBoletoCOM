<?php

namespace Tests\Unit\BoxOffice;

use App\Modules\BoxOffice\Services\MenuBuilder;
use App\Modules\BoxOffice\Support\Barcode;
use App\Modules\BoxOffice\Support\SeatLabel;
use App\Modules\BoxOffice\Support\SpanishDate;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SupportTest extends TestCase
{
    public function test_spanish_long_date(): void
    {
        $this->assertSame('Domingo 08 de Noviembre de 2026', SpanishDate::long('2026-11-08 20:30:00'));
        $this->assertSame('', SpanishDate::long(null));
    }

    public function test_seat_label_parts(): void
    {
        $this->assertSame([], SeatLabel::parts('NA_NA4'));
        $this->assertSame([['label' => 'Mesa', 'value' => 'M12'], ['label' => 'Asiento', 'value' => 'B']], SeatLabel::parts('M12-B'));
        $this->assertSame([['label' => 'Fila', 'value' => 'C'], ['label' => 'Asiento', 'value' => '14']], SeatLabel::parts('C14'));
    }

    public function test_codabar_svg_draws_bars_for_digits(): void
    {
        $svg = Barcode::codabarSvg('0000012345');

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertSame(4 * 12, substr_count($svg, '<rect'));
    }

    public function test_codabar_rejects_invalid_characters(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Barcode::codabarSvg('ABC#');
    }

    public function test_qr_data_uri_is_svg(): void
    {
        $this->assertStringStartsWith('data:image/svg+xml;base64,', Barcode::qrDataUri(123456));
    }

    public function test_menu_section_is_first_path_segment(): void
    {
        $this->assertSame('tickets', MenuBuilder::sectionOf('/tickets/'));
        $this->assertSame('ventas', MenuBuilder::sectionOf('ventas/enviar-mail'));
        $this->assertSame('reportes-por-dia', MenuBuilder::sectionOf('Reportes-Por-Dia?x=1'));
    }
}
