<?php

namespace App\Console\Commands;

use App\Modules\Checkout\Mail\PurchaseTicketsMail;
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
        {--plain : Solo manda un correo de texto sencillo, para probar la conexión}
        {--queue : Lo manda por la cola, como la compra web}';

    protected $description = 'Envía el correo de boletos (enlace a los boletos digitales) de una compra a un correo de prueba';

    public function handle(): int
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

        $mail = new PurchaseTicketsMail((string) $purchase->evento?->evento, $purchase->codigo, route('ticket.shared', $purchase->codigo));

        $this->line("Enlace de los boletos: {$mail->ticketsUrl}");

        if ($this->option('queue')) {
            return $this->attempt(
                fn () => Mail::to($email)->queue($mail),
                "Envío en la cola para {$email}. El worker (Horizon o queue:work) lo manda; si no llega, revisa su log y failed_jobs.",
            );
        }

        return $this->attempt(fn () => Mail::to($email)->send($mail), "Correo de la compra {$purchase->pk_evento_compra} enviado a {$email}.");
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
