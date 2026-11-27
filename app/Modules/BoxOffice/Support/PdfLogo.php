<?php

namespace App\Modules\BoxOffice\Support;

/**
 * Logo de DeBoleto (public/images/logo.svg) como data URI para DomPDF. El SVG
 * exportado de Illustrator trae DOCTYPE con entidades, metadata y colores por
 * clase CSS; se limpia y se pasan los colores a atributos fill.
 */
class PdfLogo
{
    private static ?string $dataUri = null;

    public static function dataUri(): string
    {
        return self::$dataUri ??= 'data:image/svg+xml;base64,'.base64_encode(self::svg());
    }

    public static function svg(): string
    {
        $svg = (string) file_get_contents(public_path('images/logo.svg'));

        preg_match_all('/\.(st\d+)\s*\{\s*fill:\s*([^;}]+);?\s*\}/', $svg, $rules, PREG_SET_ORDER);

        foreach ($rules as [, $class, $fill]) {
            $svg = str_replace('class="'.$class.'"', 'fill="'.trim($fill).'"', $svg);
        }

        $svg = preg_replace([
            '/<\?xml.*?\?>/s',
            '/<!--.*?-->/s',
            '/<!DOCTYPE.*?\]>/s',
            '/<style.*?<\/style>/s',
            '/<metadata.*?<\/metadata>/s',
        ], '', $svg);

        $viewBox = preg_match('/viewBox="([^"]+)"/', (string) $svg, $match) ? $match[1] : '0 0 140 26.8';
        [, , $width, $height] = array_pad(preg_split('/[\s,]+/', trim($viewBox)), 4, '0');

        return trim((string) preg_replace(
            '/<svg\b[^>]*>/s',
            '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="'.$viewBox.'">',
            (string) $svg,
            1,
        ));
    }
}
