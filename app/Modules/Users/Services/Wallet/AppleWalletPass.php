<?php

namespace App\Modules\Users\Services\Wallet;

use GdImage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Pases de Apple Wallet (.pkpass) de los boletos de una compra. Con varios
 * boletos se entregan juntos en un .pkpasses (iOS 15+).
 */
class AppleWalletPass
{
    public function enabled(): bool
    {
        $config = config('wallet.apple');

        return $config['enabled']
            && filled($config['pass_type_id'])
            && filled($config['team_id'])
            && is_file((string) $config['certificate'])
            && is_file((string) $config['wwdr']);
    }

    /**
     * @param  array{event: array<string, mixed>, purchase: array<string, mixed>, tickets: list<array<string, mixed>>}  $pass
     * @return array{content: string, type: string, name: string}
     */
    public function make(array $pass): array
    {
        $images = $this->images($pass['event']['image'] ?? null);
        $packages = array_map(fn (array $ticket) => $this->package($pass, $ticket, $images), $pass['tickets']);
        $folio = $pass['purchase']['folio'];

        if (count($packages) === 1) {
            return ['content' => $packages[0], 'type' => 'application/vnd.apple.pkpass', 'name' => "deboleto-{$folio}.pkpass"];
        }

        $files = [];

        foreach ($packages as $index => $package) {
            $files['boleto-'.($index + 1).'.pkpass'] = $package;
        }

        return ['content' => $this->zip($files), 'type' => 'application/vnd.apple.pkpasses', 'name' => "deboleto-{$folio}.pkpasses"];
    }

    /**
     * @param  array<string, string>  $images
     */
    private function package(array $pass, array $ticket, array $images): string
    {
        $files = ['pass.json' => json_encode($this->definition($pass, $ticket), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)] + $images;
        $manifest = json_encode(array_map('sha1', $files), JSON_UNESCAPED_SLASHES);

        return $this->zip($files + ['manifest.json' => $manifest, 'signature' => $this->sign($manifest)]);
    }

    private function definition(array $pass, array $ticket): array
    {
        $event = $pass['event'];
        $purchase = $pass['purchase'];
        $colors = config('wallet.colors');
        $code = (string) $ticket['id'];
        $place = implode(', ', array_filter([$event['venue'] ?? null, $event['city'] ?? null]));

        return array_filter([
            'formatVersion' => 1,
            'passTypeIdentifier' => config('wallet.apple.pass_type_id'),
            'teamIdentifier' => config('wallet.apple.team_id'),
            'organizationName' => config('wallet.organization'),
            'serialNumber' => "deboleto-{$purchase['folio']}-{$ticket['kind']}-{$ticket['number']}",
            'description' => "Boleto para {$event['name']}",
            'backgroundColor' => $this->rgb($colors['background']),
            'foregroundColor' => $this->rgb($colors['foreground']),
            'labelColor' => $this->rgb($colors['label']),
            'relevantDate' => $this->start($event)?->toIso8601String(),
            'voided' => $ticket['scanned'] ? true : null,
            'eventTicket' => [
                'headerFields' => [
                    ['key' => 'folio', 'label' => 'FOLIO', 'value' => (string) $purchase['folio']],
                ],
                'primaryFields' => [
                    ['key' => 'event', 'label' => $ticket['courtesy'] ? 'CORTESÍA' : 'EVENTO', 'value' => $event['name']],
                ],
                'secondaryFields' => array_values(array_filter([
                    ['key' => 'zone', 'label' => $ticket['kind'] === 'parking' ? 'PASE' : 'ZONA', 'value' => $ticket['locality'] ?: 'General'],
                    $ticket['seat'] ? ['key' => 'seat', 'label' => 'ASIENTO', 'value' => $ticket['seat'], 'textAlignment' => 'PKTextAlignmentRight'] : null,
                ])),
                'auxiliaryFields' => array_values(array_filter([
                    ['key' => 'date', 'label' => 'FECHA', 'value' => $event['date'] ?: '—'],
                    ['key' => 'time', 'label' => 'HORA', 'value' => $event['time'] ? "{$event['time']} h" : '—'],
                    $ticket['special'] ? ['key' => 'access', 'label' => 'ACCESO', 'value' => $ticket['special'], 'textAlignment' => 'PKTextAlignmentRight'] : null,
                ])),
                'backFields' => array_values(array_filter([
                    $place !== '' ? ['key' => 'venue', 'label' => 'Lugar', 'value' => trim($place."\n".($event['address'] ?? ''))] : null,
                    $purchase['holder'] ? ['key' => 'holder', 'label' => 'Titular', 'value' => $purchase['holder']] : null,
                    ['key' => 'ticket', 'label' => 'Boleto', 'value' => $code],
                    ['key' => 'terms', 'label' => 'Acceso', 'value' => 'Presenta este código en la entrada. Cada boleto permite un solo acceso.'],
                ])),
            ],
            'barcodes' => [
                ['format' => 'PKBarcodeFormatQR', 'message' => $code, 'messageEncoding' => 'iso-8859-1', 'altText' => $code],
            ],
        ], fn ($value) => $value !== null);
    }

    /**
     * @return array<string, string>
     */
    private function images(?string $eventImage): array
    {
        $icon = (string) file_get_contents(public_path('deboletoIcono.png'));
        $logo = (string) file_get_contents(public_path('logo_blanco.png'));
        $images = [
            'icon.png' => $this->resize($icon, 29, 29, cover: false),
            'icon@2x.png' => $this->resize($icon, 58, 58, cover: false),
            'icon@3x.png' => $this->resize($icon, 87, 87, cover: false),
            'logo.png' => $this->resize($logo, 160, 50, cover: false),
            'logo@2x.png' => $this->resize($logo, 320, 100, cover: false),
            'logo@3x.png' => $this->resize($logo, 480, 150, cover: false),
        ];

        $strip = $eventImage ? $this->download($eventImage) : null;

        if ($strip !== null) {
            $images += [
                'strip.png' => $this->resize($strip, 375, 98, cover: true),
                'strip@2x.png' => $this->resize($strip, 750, 196, cover: true),
                'strip@3x.png' => $this->resize($strip, 1125, 294, cover: true),
            ];
        }

        return $images;
    }

    private function download(string $url): ?string
    {
        try {
            $response = Http::timeout(10)->get($url);

            return $response->successful() ? $response->body() : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Ajusta la imagen al tamaño pedido: recortada para llenarlo (`cover`) o
     * completa y centrada sobre fondo transparente.
     */
    private function resize(string $content, int $width, int $height, bool $cover): string
    {
        $source = @imagecreatefromstring($content);

        if (! $source instanceof GdImage) {
            throw new RuntimeException('No se pudo leer una imagen del pase de Apple Wallet.');
        }

        [$sourceWidth, $sourceHeight] = [imagesx($source), imagesy($source)];
        $scale = $cover
            ? max($width / $sourceWidth, $height / $sourceHeight)
            : min($width / $sourceWidth, $height / $sourceHeight);
        [$drawWidth, $drawHeight] = [(int) round($sourceWidth * $scale), (int) round($sourceHeight * $scale)];

        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $source, intdiv($width - $drawWidth, 2), intdiv($height - $drawHeight, 2), 0, 0, $drawWidth, $drawHeight, $sourceWidth, $sourceHeight);

        ob_start();
        imagepng($canvas);

        return (string) ob_get_clean();
    }

    private function sign(string $manifest): string
    {
        $config = config('wallet.apple');

        if (! openssl_pkcs12_read((string) file_get_contents($config['certificate']), $certificates, (string) $config['certificate_password'])) {
            throw new RuntimeException('No se pudo leer el certificado de Apple Wallet: '.openssl_error_string());
        }

        $input = tempnam(sys_get_temp_dir(), 'pkpass');
        $output = tempnam(sys_get_temp_dir(), 'pkpass');
        file_put_contents($input, $manifest);

        try {
            if (! openssl_cms_sign($input, $output, $certificates['cert'], $certificates['pkey'], null, OPENSSL_CMS_DETACHED | OPENSSL_CMS_BINARY, OPENSSL_ENCODING_DER, $config['wwdr'])) {
                throw new RuntimeException('No se pudo firmar el pase de Apple Wallet: '.openssl_error_string());
            }

            return (string) file_get_contents($output);
        } finally {
            @unlink($input);
            @unlink($output);
        }
    }

    /**
     * @param  array<string, string>  $files
     */
    private function zip(array $files): string
    {
        $path = tempnam(sys_get_temp_dir(), 'wallet');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);

        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }

        $zip->close();

        try {
            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    private function rgb(string $hex): string
    {
        [$red, $green, $blue] = sscanf(ltrim($hex, '#'), '%02x%02x%02x');

        return "rgb({$red}, {$green}, {$blue})";
    }

    private function start(array $event): ?Carbon
    {
        try {
            return filled($event['iso'] ?? null) ? Carbon::parse($event['iso'], config('app.timezone')) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
