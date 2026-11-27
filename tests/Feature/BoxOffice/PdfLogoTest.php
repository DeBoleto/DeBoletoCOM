<?php

namespace Tests\Feature\BoxOffice;

use App\Modules\BoxOffice\Support\PdfLogo;
use Tests\TestCase;

class PdfLogoTest extends TestCase
{
    public function test_logo_svg_is_cleaned_for_dompdf(): void
    {
        $svg = PdfLogo::svg();

        $this->assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg" width="140" height="26.8"', $svg);
        $this->assertStringNotContainsString('class=', $svg);
        $this->assertStringNotContainsString('<!DOCTYPE', $svg);
        $this->assertStringContainsString('fill="#23AA83"', $svg);
        $this->assertNotFalse(simplexml_load_string($svg));
        $this->assertStringStartsWith('data:image/svg+xml;base64,', PdfLogo::dataUri());
    }

    public function test_report_layout_uses_the_svg_logo(): void
    {
        $layout = file_get_contents(resource_path('views/boxoffice/pdf/layout.blade.php'));

        $this->assertStringNotContainsString('logo_full.png', $layout);
        $this->assertStringContainsString('PdfLogo::dataUri()', $layout);
    }
}
