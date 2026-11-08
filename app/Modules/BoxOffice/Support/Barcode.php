<?php

namespace App\Modules\BoxOffice\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use InvalidArgumentException;

/**
 * Códigos QR y Codabar en SVG para boletos impresos y PDFs.
 * Sustituye a milon/barcode (DNS1D / DNS2D) del sistema legacy.
 */
class Barcode
{
    /**
     * Patrones Codabar estándar (barra, espacio, barra…); 1 = elemento ancho.
     */
    private const CODABAR = [
        '0' => '0000011', '1' => '0000110', '2' => '0001001', '3' => '1100000',
        '4' => '0010010', '5' => '1000010', '6' => '0100001', '7' => '0100100',
        '8' => '0110000', '9' => '1001000', '-' => '0001100', '$' => '0011000',
        ':' => '1000101', '/' => '1010001', '.' => '1010100', '+' => '0010101',
        'A' => '0011010', 'B' => '0101001', 'C' => '0001011', 'D' => '0001110',
    ];

    private const NARROW = 1;

    private const WIDE = 3;

    public static function qrSvg(string $content, int $size = 200, int $margin = 0): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, $margin), new SvgImageBackEnd));

        return $writer->writeString($content);
    }

    public static function qrDataUri(string|int $content, int $size = 200): string
    {
        return self::svgDataUri(self::qrSvg((string) $content, $size));
    }

    public static function codabarSvg(string $content, int $height = 60): string
    {
        $code = 'A'.strtoupper($content).'A';
        $x = 0;
        $bars = [];

        foreach (str_split($code) as $char) {
            $pattern = self::CODABAR[$char] ?? throw new InvalidArgumentException("Carácter no válido para Codabar: {$char}");

            foreach (str_split($pattern) as $position => $wide) {
                $width = $wide === '1' ? self::WIDE : self::NARROW;

                if ($position % 2 === 0) {
                    $bars[] = sprintf('<rect x="%d" y="0" width="%d" height="%d"/>', $x, $width, $height);
                }

                $x += $width;
            }

            $x += self::NARROW;
        }

        $width = $x - self::NARROW;

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%2$d" viewBox="0 0 %1$d %2$d" preserveAspectRatio="none" fill="#000">%3$s</svg>',
            $width,
            $height,
            implode('', $bars),
        );
    }

    public static function codabarDataUri(string|int $content, int $height = 60): string
    {
        return self::svgDataUri(self::codabarSvg((string) $content, $height));
    }

    private static function svgDataUri(string $svg): string
    {
        $svg = preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
