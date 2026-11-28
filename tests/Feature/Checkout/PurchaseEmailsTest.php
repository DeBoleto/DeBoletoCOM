<?php

namespace Tests\Feature\Checkout;

use App\Modules\BoxOffice\Mail\DigitalTicketsMail;
use App\Modules\Checkout\Events\WebPurchasePaid;
use App\Modules\Checkout\Listeners\DeliverPurchaseTickets;
use App\Modules\Checkout\Mail\PurchaseTicketsMail;
use App\Modules\Checkout\Mail\TransferInstructionsMail;
use App\Modules\Orders\Models\EventosCompras;
use App\Modules\Payments\Contracts\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\Support\FakePaymentGateway;
use Tests\Support\LegacyCheckoutSchema;
use Tests\TestCase;

class PurchaseEmailsTest extends TestCase
{
    use LegacyCheckoutSchema, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->createLegacyCheckoutTables();
        $this->seedSeatMapEvent();
        $this->app->instance(PaymentGateway::class, new FakePaymentGateway);

        Event::fake([WebPurchasePaid::class]);
        Mail::fake();
    }

    public function test_paid_purchase_emails_a_link_to_the_digital_tickets_without_a_pdf(): void
    {
        $purchase = $this->transferPurchase();

        app(DeliverPurchaseTickets::class)->handle(new WebPurchasePaid($purchase->pk_evento_compra));

        $url = route('ticket.shared', $purchase->codigo);

        Mail::assertNotSent(DigitalTicketsMail::class);
        Mail::assertSent(PurchaseTicketsMail::class, function (PurchaseTicketsMail $mail) use ($url) {
            $html = $mail->render();

            return $mail->hasTo('ana@gmail.com')
                && $mail->hasSubject('Gracias por tu compra')
                && $mail->ticketsUrl === $url
                && $mail->attachments === []
                && $mail->rawAttachments === []
                && str_contains($html, e($url))
                && str_contains($html, 'Boletos digitales')
                && str_contains($html, 'QR dinámico')
                && str_contains($html, asset('logo_blanco.png'));
        });
    }

    public function test_transfer_purchase_emails_the_spei_instructions(): void
    {
        $purchase = $this->transferPurchase();

        Mail::assertSent(TransferInstructionsMail::class, function (TransferInstructionsMail $mail) use ($purchase) {
            $html = $mail->render();

            return $mail->hasTo('ana@gmail.com')
                && $mail->clabe === '646180111812345678'
                && $mail->bank === 'STP'
                && $mail->reference === $purchase->codigo
                && $mail->amount > 0
                && $mail->dueDate !== null
                && str_contains($html, '646180111812345678')
                && str_contains($html, $purchase->codigo)
                && str_contains($html, e(route('checkout.complete', $purchase->codigo)));
        });
        Mail::assertNotSent(PurchaseTicketsMail::class);
    }

    public function test_card_payments_do_not_send_spei_instructions(): void
    {
        $this->actingAs($this->createCustomer(), 'customer')->post('/compra/10/seleccion', $this->selection());
        $this->post('/compra/10/pago', [
            'metodo' => 'card',
            'token_id' => 'tok_test',
            'device_session_id' => 'device-session',
            'acepto' => true,
        ], ['X-Inertia' => 'true']);

        Mail::assertNotSent(TransferInstructionsMail::class);
    }

    private function transferPurchase(): EventosCompras
    {
        $this->actingAs($this->createCustomer(), 'customer')->post('/compra/10/seleccion', $this->selection());
        $this->post('/compra/10/pago', ['metodo' => 'transfer', 'acepto' => true]);

        return EventosCompras::query()->sole();
    }

    private function selection(): array
    {
        return ['funcion' => 20, 'localidad' => 30, 'seccion' => 'pref', 'asientos' => ['A1', 'A2']];
    }
}
