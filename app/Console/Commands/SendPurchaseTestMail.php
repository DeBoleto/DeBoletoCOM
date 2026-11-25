<?php

namespace App\Console\Commands;

use App\Modules\BoxOffice\Mail\DigitalTicketsMail;
use App\Modules\BoxOffice\Services\Printing\TicketPrintService;
use App\Modules\Orders\Models\EventosCompras;
use Illuminate\Console\Command;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Throwable;

class SendPurchaseTestMail extends Command
{
    protected $signature = 'mail:test-purchase
        {folio : Folio de la compra (pk_evento_compra)}
        {email : Correo que recibirá la prueba}
        {--plain : Solo manda un correo de texto, sin PDF, para probar la conexión}
        {--queue : Lo manda por la cola, como la compra web}';

    protected $description = 'Envía el correo de boletos de una compra a un correo de prueba';

    public function handle(TicketPrintService $printing): int
    {
        $email = (string) $this->argument('email');

        if (Validator::make(['email' => $email], ['email' => ['required', 'email']])->fails()) {
            $this->error("El correo {$email} no es válido.");

            return self::FAILURE;
        }

        $this->table(['Configuración', 'Valor'], [
            ['Mailer', config('mail.default')],
            ['Servidor', config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port')],
            ['Cifrado', config('mail.mailers.smtp.scheme') ?? config('mail.mailers.smtp.encryption') ?? '—'],
            ['Usuario', config('mail.mailers.smtp.username') ?: '—'],
            ['Remitente', config('mail.from.address').' ('.config('mail.from.name').')'],
            ['Cola', config('queue.default')],
        ]);

        if (! config('mail.from.address')) {
            $this->warn('MAIL_FROM_ADDRESS está vacío: muchos servidores rechazan el correo sin remitente.');
        }

        if ($this->option('plain')) {
            return $this->attempt(fn () => Mail::raw(
                'Correo de prueba de DeBoleto enviado el '.now()->format('d/m/Y H:i:s').'.',
                fn (Message $message) => $message->to($email)->subject('Prueba de correo DeBoleto'),
            ), "Correo de prueba enviado a {$email}.");
        }

        $purchase = EventosCompras::query()->with('evento')->find((int) $this->argument('folio'));

        if ($purchase === null) {
            $this->error("No existe la compra con folio {$this->argument('folio')}.");

            return self::FAILURE;
        }

        $this->info("Compra {$purchase->pk_evento_compra}: {$purchase->evento?->evento}.");

        if ($this->option('queue')) {
            $folio = $purchase->pk_evento_compra;

            return $this->attempt(
                fn () => dispatch(function () use ($folio, $email) {
                    $purchase = EventosCompras::query()->with('evento')->findOrFail($folio);
                    $pdf = app(TicketPrintService::class)->digitalDocument($purchase)->output();

                    Mail::to($email)->send(new DigitalTicketsMail((string) $purchase->evento?->evento, $folio, $pdf));
                }),
                "Envío en la cola para {$email}. El worker (Horizon o queue:work) genera el PDF y lo manda; si no llega, revisa su log y failed_jobs.",
            );
        }

        $this->line('Generando el PDF de boletos…');

        try {
            $pdf = $printing->digitalDocument($purchase)->output();
        } catch (Throwable $exception) {
            $this->error('No se pudo generar el PDF: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->line('PDF generado ('.number_format(strlen($pdf) / 1024, 1).' KB).');

        $mail = new DigitalTicketsMail((string) $purchase->evento?->evento, $purchase->pk_evento_compra, $pdf);

        return $this->attempt(fn () => Mail::to($email)->send($mail), "Boletos de la compra {$purchase->pk_evento_compra} enviados a {$email}.");
    }

    private function attempt(callable $send, string $success): int
    {
        try {
            $send();
        } catch (Throwable $exception) {
            $this->error('El servidor de correo rechazó el envío: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info($success);

        return self::SUCCESS;
    }
}
