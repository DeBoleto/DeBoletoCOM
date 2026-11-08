<?php

namespace App\Modules\BoxOffice\Services\Delivery;

use App\Modules\BoxOffice\Services\Printing\TicketPrintService;
use App\Modules\BoxOffice\Support\SpanishDate;
use App\Modules\Events\Models\Views\EventoFuncionVista;
use App\Modules\Events\Models\Views\EventoVista;
use App\Modules\Orders\Models\ComprasWhatsapp;
use App\Modules\Orders\Models\EventosCompras;
use App\Modules\Shared\Models\LegacyModel;
use App\Modules\Users\Models\Usuarios;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Envía el enlace del boleto digital (o del registro de bonos) por WhatsApp
 * mediante UltraMSG.
 */
class WhatsAppTicketSender
{
    public function __construct(private readonly TicketPrintService $printing) {}

    /**
     * @throws RuntimeException cuando el proveedor no está configurado o rechaza el envío.
     */
    public function send(EventosCompras $purchase, string $phone, Usuarios $seller): void
    {
        $config = config('services.ultramsg');

        if (empty($config['instance']) || empty($config['token'])) {
            throw new RuntimeException('El envío por WhatsApp no está configurado.');
        }

        $event = EventoVista::query()->findOrFail($purchase->pk_evento);
        $function = EventoFuncionVista::query()->findOrFail($purchase->pk_evento_funcion);
        $isBond = (int) $event->bono === 1;

        try {
            $response = Http::timeout(30)
                ->asJson()
                ->post(rtrim($config['base_url'], '/')."/{$config['instance']}/messages/image", [
                    'token' => $config['token'],
                    'to' => $config['country_code'].$phone,
                    'image' => $isBond ? $config['bond_image'] : $config['ticket_image'],
                    'caption' => $this->caption($event, $function, $purchase, $isBond),
                ]);
        } catch (ConnectionException $exception) {
            $this->log($purchase, $phone, null);
            throw new RuntimeException('No se pudo conectar con el servicio de WhatsApp.', previous: $exception);
        }

        $messageId = $response->json('id');

        if ($response->failed() || $messageId === null) {
            Log::warning('UltraMSG rechazó el envío', ['compra' => $purchase->pk_evento_compra, 'status' => $response->status()]);
            $this->log($purchase, $phone, null);

            throw new RuntimeException('El servicio de WhatsApp rechazó el envío.');
        }

        $this->log($purchase, $phone, (string) $messageId);
        $this->printing->markDelivered($purchase->boletos(), $seller, printed: true);
    }

    private function caption(EventoVista $event, EventoFuncionVista $function, EventosCompras $purchase, bool $isBond): string
    {
        $date = SpanishDate::long($function->fecha);

        if ($isBond) {
            return "Hola, por favor da clic en el siguiente enlace para finalizar el registro de tu(s) bono(s):\n\n"
                ."{$event->evento}\n\n{$date}\n\n "
                .config('boxoffice.bond_registration_url').$purchase->codigo
                ."\n\nPor favor completa el siguiente formulario con tus datos para generar tu bono personalizado, "
                ."la cual te dará acceso a los próximos juegos.\n\nGracias por tu compra. 🔥";
        }

        return "Hola, por favor da clic en el siguiente enlace para ver tu boleto 📲 digital:\n\n"
            ."{$event->evento}\n\n{$date}\n\n "
            .config('boxoffice.digital_ticket_url').$purchase->codigo
            ."\n\nPara el ingresar al evento no se aceptaran capturas ni impresiones,  solo se dara ingreso "
            ."mostrando el boleto digital con el código QR dinamico\n\nGracias por tu compra. 🔥";
    }

    private function log(EventosCompras $purchase, string $phone, ?string $messageId): void
    {
        ComprasWhatsapp::query()->create([
            'pk_evento_compra' => $purchase->codigo,
            'sid_twilio' => $messageId ?? '0',
            'telefono' => $phone,
            'fecha' => Carbon::now(LegacyModel::legacyTimezone()),
            'enviado' => $messageId === null ? 0 : 1,
        ]);
    }
}
