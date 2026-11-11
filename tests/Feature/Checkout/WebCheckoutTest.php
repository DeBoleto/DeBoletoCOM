<?php

namespace Tests\Feature\Checkout;

use App\Modules\Checkout\Events\WebPurchasePaid;
use App\Modules\Events\Models\EventoReservaBoleto;
use App\Modules\Orders\Enums\PurchaseStatus;
use App\Modules\Orders\Models\EventosCompras;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Data\ChargeStatus;
use App\Modules\Tickets\Enums\TicketStatus;
use App\Modules\Tickets\Models\EventosFuncionesBoletos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakePaymentGateway;
use Tests\Support\LegacyCheckoutSchema;
use Tests\TestCase;

class WebCheckoutTest extends TestCase
{
    use LegacyCheckoutSchema, RefreshDatabase;

    private FakePaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->createLegacyCheckoutTables();
        $this->seedSeatMapEvent();

        $this->gateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);

        Event::fake([WebPurchasePaid::class]);
    }

    public function test_purchase_starts_on_the_process_page(): void
    {
        $this->get('/checkoutprocess/concierto-de-prueba')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Checkout/Process')
                ->where('mode', 'map')
                ->where('function.id', 20)
                ->has('functions', 1)
                ->where('stage.sections.0.sectionId', 'pref')
                ->where('stage.sections.0.available', true)
                ->where('limits.maxTickets', 10));
    }

    public function test_previous_seat_map_url_redirects_to_the_process_page(): void
    {
        $this->get('/evento/concierto-de-prueba/asientos?function=20')
            ->assertStatus(301)
            ->assertRedirect('/checkoutprocess/concierto-de-prueba?function=20');
    }

    public function test_events_with_several_dates_ask_for_one_first(): void
    {
        DB::table('view_eventos_funciones')->insert([
            'pk_evento_funcion' => 21, 'pk_evento' => 10, 'fecha' => '2026-12-13 20:00:00',
            'fecha_natural' => '13 de diciembre de 2026', 'hora_natural' => '20:00', 'cerrado' => 0,
        ]);

        $this->get('/checkoutprocess/concierto-de-prueba')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Checkout/Process')
                ->where('mode', 'function')
                ->where('function', null)
                ->has('functions', 2));

        $this->get('/checkoutprocess/concierto-de-prueba?function=21')
            ->assertInertia(fn (Assert $page) => $page->where('mode', 'map')->where('function.id', 21));

        $this->get('/checkoutprocess/concierto-de-prueba?function=999')
            ->assertRedirect('/checkoutprocess/concierto-de-prueba')
            ->assertSessionHas('error');
    }

    public function test_unknown_or_offline_events_are_not_sold(): void
    {
        $this->get('/checkoutprocess/no-existe')->assertNotFound();

        DB::table('view_eventos')->update(['bool_venta_web' => 0]);
        $this->get('/checkoutprocess/concierto-de-prueba')->assertNotFound();
    }

    public function test_guests_are_asked_to_sign_in_before_continuing(): void
    {
        $this->from('/checkoutprocess/concierto-de-prueba')
            ->post('/compra/10/seleccion', $this->selection())
            ->assertRedirect('/checkoutprocess/concierto-de-prueba')
            ->assertSessionHas('auth_required', true);
    }

    public function test_one_purchase_can_combine_several_zones(): void
    {
        $this->addGeneralZone();

        $this->actingAs($this->createCustomer(), 'customer')
            ->post('/compra/10/seleccion', $this->multiZoneSelection())
            ->assertRedirect('/compra/10/pago');

        $this->assertSame(2, EventoReservaBoleto::query()->where('pk_seccion', 'Preferente')->count());

        $this->get('/compra/10/pago')
            ->assertInertia(fn (Assert $page) => $page
                ->where('quote.subtotal', 1400)
                ->where('quote.paid', 4)
                ->where('quote.lines.2.label', 'Boleto 1')
                ->where('quote.lines.2.locality', 'General'));

        $this->get('/checkoutprocess/concierto-de-prueba?function=20')
            ->assertInertia(fn (Assert $page) => $page
                ->where('cart.sections.0.sectionId', 'pref')
                ->where('cart.sections.0.seats', ['A1', 'A2'])
                ->where('cart.sections.1.sectionId', 'gen')
                ->where('cart.sections.1.quantity', 2));

        $this->post('/compra/10/pago', ['metodo' => 'transfer', 'acepto' => true]);

        $tickets = EventosFuncionesBoletos::query()->where('pk_evento_compra', EventosCompras::query()->sole()->pk_evento_compra)->get();
        $this->assertSame(['A1', 'A2', 'NA_NA0', 'NA_NA1'], $tickets->pluck('asiento')->sort()->values()->all());
        $this->assertSame([30 => 2, 31 => 2], $tickets->countBy('pk_evento_localidad')->sortKeys()->all());
    }

    public function test_general_zone_capacity_is_enforced_in_multi_zone_purchases(): void
    {
        $this->addGeneralZone(capacity: 1);

        $this->actingAs($this->createCustomer(), 'customer')
            ->post('/compra/10/seleccion', $this->multiZoneSelection())
            ->assertSessionHasErrors('cantidad');

        $this->assertSame(0, EventoReservaBoleto::query()->count());
    }

    public function test_ticket_limit_and_holds_cover_every_zone(): void
    {
        config(['checkout.max_tickets' => 3]);
        $this->addGeneralZone();
        $customer = $this->createCustomer();

        $this->actingAs($customer, 'customer')
            ->post('/compra/10/seleccion', $this->multiZoneSelection())
            ->assertSessionHasErrors('asientos');

        $this->postJson('/compra/10/asientos', ['funcion' => 20, 'seccion' => 'pref', 'asiento' => 'A1', 'accion' => 'apartar'])->assertOk();
        $this->postJson('/compra/10/asientos', ['funcion' => 20, 'seccion' => 'pref', 'asiento' => 'A2', 'accion' => 'apartar'])->assertOk();
        $this->postJson('/compra/10/asientos', ['funcion' => 20, 'seccion' => 'pref', 'asiento' => 'A3', 'accion' => 'apartar'])->assertOk();
        $this->postJson('/compra/10/asientos', ['funcion' => 20, 'seccion' => 'pref', 'asiento' => 'A4', 'accion' => 'apartar'])->assertStatus(409);

        $this->post('/compra/10/seleccion', ['funcion' => 20, 'secciones' => [
            ['localidad' => 30, 'seccion' => 'pref', 'asientos' => ['A1']],
            ['localidad' => 31, 'seccion' => 'gen', 'cantidad' => 2],
        ]])->assertRedirect('/compra/10/pago');

        $this->assertSame(['A1'], EventoReservaBoleto::query()->pluck('asiento')->all());
    }

    public function test_zone_specific_coupons_only_discount_their_zone(): void
    {
        $this->addGeneralZone();
        DB::table('eventos_codigo_promocion')->insert([
            'pk_evento' => 10, 'pk_localidad' => 31, 'codigo_promocion' => 'GENERAL50', 'tipo' => 2, 'promocion' => 50,
        ]);

        $this->actingAs($this->createCustomer(), 'customer')->post('/compra/10/seleccion', $this->multiZoneSelection());

        $this->postJson('/compra/10/cotizacion', ['cupon' => 'GENERAL50'])
            ->assertOk()
            ->assertJsonPath('quote.discount', 50);

        $this->post('/compra/10/pago', ['metodo' => 'transfer', 'acepto' => true, 'cupon' => 'GENERAL50']);

        $prices = EventosFuncionesBoletos::query()->orderBy('asiento')->pluck('precio', 'asiento')->map(fn ($price) => (float) $price)->all();
        $this->assertSame(['A1' => 500.0, 'A2' => 500.0, 'NA_NA0' => 175.0, 'NA_NA1' => 175.0], $prices);
    }

    public function test_selection_prices_tickets_on_the_server_and_holds_seats(): void
    {
        $this->actingAs($this->createCustomer(), 'customer')
            ->post('/compra/10/seleccion', $this->selection() + ['precio' => 1])
            ->assertRedirect('/compra/10/pago');

        $this->assertSame(2, EventoReservaBoleto::query()->where('pk_seccion', 'Preferente')->count());

        $this->get('/compra/10/pago')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Checkout/Payment')
                ->where('quote.subtotal', 1000)
                ->where('quote.service', 80)
                ->where('quote.payment', 50.11)
                ->where('quote.delivery', 15)
                ->where('quote.total', 1145.11));
    }

    public function test_seats_held_by_another_session_cannot_be_taken(): void
    {
        EventoReservaBoleto::query()->insert([
            'pk_usuario' => 'otra-sesion', 'pk_evento' => 10, 'pk_evento_funcion' => 20,
            'pk_seccion' => 'Preferente', 'asiento' => 'A1', 'creacion_fecha' => Carbon::now('America/Mexico_City'),
        ]);

        $this->getJson('/compra/10/funciones/20/secciones/pref')
            ->assertOk()
            ->assertJsonPath('statuses.A1', 'proceso')
            ->assertJsonPath('price', 500);

        $this->postJson('/compra/10/asientos', ['funcion' => 20, 'seccion' => 'pref', 'asiento' => 'A3', 'accion' => 'apartar'])
            ->assertOk()
            ->assertJsonPath('status', 'local');
        $this->assertSame(1, EventoReservaBoleto::query()->count());

        $this->actingAs($this->createCustomer(), 'customer');

        $this->postJson('/compra/10/asientos', ['funcion' => 20, 'seccion' => 'pref', 'asiento' => 'A1', 'accion' => 'apartar'])
            ->assertStatus(409);

        $this->post('/compra/10/seleccion', $this->selection())->assertSessionHas('error');
    }

    public function test_events_without_seat_map_sell_by_quantity_with_parking(): void
    {
        DB::table('view_eventos')->update(['sin_estructura' => 1]);
        DB::table('eventos')->update(['estacionamiento' => 5, 'estacionamiento_precio' => 100, 'estacionamiento_comision' => 10]);
        DB::table('eventos_localidades')->update(['capacidad' => 3]);

        $this->get('/checkoutprocess/concierto-de-prueba')
            ->assertInertia(fn (Assert $page) => $page
                ->where('mode', 'quantities')
                ->where('localities.0.remaining', 3)
                ->where('parking.price', 100));

        $this->actingAs($this->createCustomer(), 'customer');

        $this->post('/compra/10/seleccion', ['funcion' => 20, 'cantidades' => [30 => 4]])->assertSessionHasErrors('cantidades');

        $this->post('/compra/10/seleccion', ['funcion' => 20, 'cantidades' => [30 => 2], 'estacionamiento' => true])
            ->assertRedirect('/compra/10/pago');

        $this->get('/compra/10/pago')
            ->assertInertia(fn (Assert $page) => $page
                ->where('quote.paid', 2)
                ->where('quote.parking.label', 'Estacionamiento'));

        $this->post('/compra/10/pago', ['metodo' => 'transfer', 'acepto' => true]);

        $purchase = EventosCompras::query()->sole();
        $this->assertSame(1, $purchase->estacionamientos()->count());
        $this->assertSame(2, EventosFuncionesBoletos::query()->where('pk_evento_compra', $purchase->pk_evento_compra)->count());
    }

    public function test_card_payment_reserves_seats_until_the_bank_confirms(): void
    {
        $customer = $this->createCustomer();
        $this->actingAs($customer, 'customer')->post('/compra/10/seleccion', $this->selection());

        $this->post('/compra/10/pago', $this->cardPayment(), ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', 'https://bank.test/3ds');

        $purchase = EventosCompras::query()->sole();
        $this->assertSame(PurchaseStatus::AwaitingPayment, $purchase->status());
        $this->assertSame(1145.11, $this->gateway->requests[0]->amount);
        $this->assertSame($purchase->codigo, $this->gateway->requests[0]->orderId);
        $this->assertSame([TicketStatus::Pending->value], $this->ticketStatuses($purchase));

        $this->gateway->settle($purchase->pk_openpay, ChargeStatus::Completed);

        $this->get('/compra/confirmacion?id='.$purchase->pk_openpay)->assertRedirect('/compra/'.$purchase->codigo);
        $this->get('/compra/confirmacion?id='.$purchase->pk_openpay)->assertRedirect('/compra/'.$purchase->codigo);

        $purchase->refresh();
        $this->assertSame(PurchaseStatus::Paid, $purchase->status());
        $this->assertSame([TicketStatus::Issued->value], $this->ticketStatuses($purchase));
        $this->assertSame('411111XXXXXX1111', $purchase->getAttribute('tarjeta_numero'));
        $this->assertSame(0, EventoReservaBoleto::query()->count());
        Event::assertDispatchedTimes(WebPurchasePaid::class, 1);

        $this->get('/compra/'.$purchase->codigo)
            ->assertInertia(fn (Assert $page) => $page->component('Checkout/Complete')->where('order.status', 'paid'));
    }

    public function test_declined_payment_releases_the_seats(): void
    {
        $this->actingAs($this->createCustomer(), 'customer')->post('/compra/10/seleccion', $this->selection());
        $this->post('/compra/10/pago', $this->cardPayment(), ['X-Inertia' => 'true']);

        $purchase = EventosCompras::query()->sole();
        $this->gateway->settle($purchase->pk_openpay, ChargeStatus::Failed);

        $this->get('/compra/confirmacion?id='.$purchase->pk_openpay)
            ->assertRedirect('/compra/10/pago')
            ->assertSessionHas('error');

        $this->assertSame(PurchaseStatus::Rejected, $purchase->fresh()->status());
        $this->assertSame(0, EventosFuncionesBoletos::query()->count());
        Event::assertNotDispatched(WebPurchasePaid::class);
    }

    public function test_charge_for_a_different_amount_does_not_issue_tickets(): void
    {
        $this->actingAs($this->createCustomer(), 'customer')->post('/compra/10/seleccion', $this->selection());

        $this->gateway->amountOverride = 1.0;
        $this->post('/compra/10/pago', $this->cardPayment(), ['X-Inertia' => 'true']);

        $purchase = EventosCompras::query()->sole();
        $this->gateway->settle($purchase->pk_openpay, ChargeStatus::Completed);
        $this->get('/compra/confirmacion?id='.$purchase->pk_openpay);

        $this->assertSame(PurchaseStatus::AwaitingPayment, $purchase->fresh()->status());
        Event::assertNotDispatched(WebPurchasePaid::class);
    }

    public function test_transfer_shows_spei_instructions_and_keeps_tickets_pending(): void
    {
        $this->actingAs($this->createCustomer(), 'customer')->post('/compra/10/seleccion', $this->selection());

        $response = $this->post('/compra/10/pago', ['metodo' => 'transfer', 'acepto' => true]);

        $purchase = EventosCompras::query()->sole();
        $response->assertRedirect('/compra/'.$purchase->codigo);
        $this->assertSame(PurchaseStatus::AwaitingTransfer, $purchase->status());
        $this->assertSame([TicketStatus::Pending->value], $this->ticketStatuses($purchase));

        $this->get('/compra/'.$purchase->codigo)
            ->assertInertia(fn (Assert $page) => $page
                ->where('order.status', 'awaiting_transfer')
                ->where('order.transfer.clabe', '646180111812345678')
                ->where('order.downloadUrl', null));
    }

    public function test_coupon_and_options_are_quoted_on_the_server(): void
    {
        DB::table('eventos')->update(['defender' => 1]);
        DB::table('eventos_codigo_promocion')->insert([
            'pk_evento' => 10, 'codigo_promocion' => 'PROMO10', 'tipo' => 1, 'promocion' => 10,
        ]);

        $this->actingAs($this->createCustomer(), 'customer')->post('/compra/10/seleccion', $this->selection());

        $this->postJson('/compra/10/cotizacion', ['cupon' => 'NOEXISTE'])->assertStatus(422)->assertJsonValidationErrors('cupon');

        $this->postJson('/compra/10/cotizacion', ['cupon' => 'PROMO10', 'defender' => true])
            ->assertOk()
            ->assertJsonPath('quote.discount', 108)
            ->assertJsonPath('quote.defender', 86.4)
            ->assertJsonPath('quote.coupon.code', 'PROMO10')
            ->assertJsonPath('quote.total', 1123.51);
    }

    public function test_purchases_are_private_to_their_owner(): void
    {
        $this->actingAs($this->createCustomer(), 'customer')->post('/compra/10/seleccion', $this->selection());
        $this->post('/compra/10/pago', ['metodo' => 'transfer', 'acepto' => true]);
        $purchase = EventosCompras::query()->sole();

        $other = $this->createCustomer(['correo' => 'otro@gmail.com', 'telefono' => '9937777777']);

        $this->actingAs($other, 'customer')->get('/compra/'.$purchase->codigo)->assertNotFound();
        $this->actingAs($other, 'customer')->get('/compra/'.$purchase->codigo.'/boletos.pdf')->assertNotFound();
    }

    public function test_webhook_requires_credentials_and_confirms_with_the_gateway(): void
    {
        config(['services.openpay.webhook_user' => 'openpay', 'services.openpay.webhook_password' => 'clave']);

        $this->actingAs($this->createCustomer(), 'customer')->post('/compra/10/seleccion', $this->selection());
        $this->post('/compra/10/pago', $this->cardPayment(), ['X-Inertia' => 'true']);
        $purchase = EventosCompras::query()->sole();

        $payload = ['type' => 'charge.succeeded', 'transaction' => ['id' => $purchase->pk_openpay, 'status' => 'completed']];

        $this->postJson('/webhooks/openpay', $payload)->assertUnauthorized();
        $this->postJson('/webhooks/openpay', $payload, ['PHP_AUTH_USER' => 'openpay', 'PHP_AUTH_PW' => 'mal'])->assertUnauthorized();

        // El contenido dice "completed", pero la API aún no: no se emiten boletos.
        $this->postJson('/webhooks/openpay', $payload, $this->webhookAuth())->assertOk();
        $this->assertSame(PurchaseStatus::AwaitingPayment, $purchase->fresh()->status());

        $this->gateway->settle($purchase->pk_openpay, ChargeStatus::Completed);
        $this->postJson('/webhooks/openpay', $payload, $this->webhookAuth())->assertOk();
        $this->assertSame(PurchaseStatus::Paid, $purchase->fresh()->status());
    }

    public function test_reconcile_command_releases_abandoned_payments(): void
    {
        $this->actingAs($this->createCustomer(), 'customer')->post('/compra/10/seleccion', $this->selection());
        $this->post('/compra/10/pago', $this->cardPayment(), ['X-Inertia' => 'true']);
        $purchase = EventosCompras::query()->sole();

        $this->artisan('checkout:reconcile')->assertSuccessful();
        $this->assertSame(PurchaseStatus::AwaitingPayment, $purchase->fresh()->status());

        DB::table('eventos_compras')->update(['creacion_fecha' => Carbon::now('America/Mexico_City')->subHour()]);

        $this->artisan('checkout:reconcile')->assertSuccessful();
        $this->assertSame(PurchaseStatus::Rejected, $purchase->fresh()->status());
        $this->assertSame(0, EventosFuncionesBoletos::query()->count());
    }

    private function selection(): array
    {
        return ['funcion' => 20, 'localidad' => 30, 'seccion' => 'pref', 'asientos' => ['A1', 'A2']];
    }

    private function multiZoneSelection(): array
    {
        return ['funcion' => 20, 'secciones' => [
            ['localidad' => 30, 'seccion' => 'pref', 'asientos' => ['A1', 'A2']],
            ['localidad' => 31, 'seccion' => 'gen', 'cantidad' => 2],
        ]];
    }

    private function addGeneralZone(int $capacity = 50): void
    {
        DB::table('eventos_localidades')->insert([
            'pk_evento_localidad' => 31, 'pk_evento' => 10, 'evento_localidad' => 'General',
            'precio' => 200, 'comision' => 10, 'comisionWeb' => 20,
        ]);

        $general = <<<XML
        <section name="General" section_id="gen" locality_id="31" color="#3b82f6" capacity="{$capacity}">
            <area color="#3b82f6" name="General">
                <vertex x="0" y="200"/><vertex x="100" y="200"/><vertex x="100" y="300"/>
            </area>
        </section>
    </sections>
XML;

        DB::table('view_eventos')->update(['escenario_xml' => str_replace('</sections>', $general, $this->stageXml())]);
    }

    private function cardPayment(): array
    {
        return ['metodo' => 'card', 'token_id' => 'tok123', 'device_session_id' => 'dev-1', 'acepto' => true];
    }

    private function webhookAuth(): array
    {
        return ['PHP_AUTH_USER' => 'openpay', 'PHP_AUTH_PW' => 'clave'];
    }

    /**
     * @return list<int>
     */
    private function ticketStatuses(EventosCompras $purchase): array
    {
        return EventosFuncionesBoletos::query()->where('pk_evento_compra', $purchase->pk_evento_compra)
            ->pluck('pk_evento_funcion_boleto_status')->map(fn ($status) => (int) $status)->unique()->values()->all();
    }
}
