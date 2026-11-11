<?php

namespace Tests\Feature\BoxOffice;

use App\Modules\BoxOffice\Services\Checkout\CheckoutStore;
use App\Modules\BoxOffice\Services\Checkout\TicketLine;
use App\Modules\Tickets\Models\EventosFuncionesBoletos;
use App\Modules\Users\Models\Usuarios;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\LegacyCheckoutSchema;
use Tests\TestCase;

class MultiZoneSaleTest extends TestCase
{
    use LegacyCheckoutSchema, RefreshDatabase;

    private Usuarios $seller;

    protected function setUp(): void
    {
        parent::setUp();

        config(['boxoffice.enforce_menu_privileges' => false]);

        $this->withoutVite();
        $this->createLegacyCheckoutTables();
        $this->seedSeatMapEvent();
        $this->addGeneralZone();

        $this->seller = $this->createCustomer(['pk_usuario_tipo' => 1, 'correo' => 'taquilla@deboleto.mx']);
    }

    public function test_a_sale_can_mix_seats_and_general_admission_from_several_zones(): void
    {
        $this->actingAs($this->seller, 'boxoffice')
            ->post('/boxoffice/tickets/checkout/10/seleccion', $this->selection())
            ->assertSessionHasNoErrors()
            ->assertRedirect('/boxoffice/tickets/checkout/10/resumen');

        $state = app(CheckoutStore::class)->get(10);

        $this->assertNull($state->localityId);
        $this->assertSame([30 => null, 31 => 50], $state->capacities);
        $this->assertSame(['A1', 'A2', 'NA_NA0', 'NA_NA1'], array_map(fn (TicketLine $line) => $line->seat, $state->lines));
        $this->assertSame([500.0, 500.0, 200.0, 200.0], array_map(fn (TicketLine $line) => (float) $line->price, $state->lines));

        $this->actingAs($this->seller, 'boxoffice')
            ->post('/boxoffice/tickets/checkout/10/confirmar', ['acepto' => true, 'token' => (string) Str::uuid()])
            ->assertRedirect('/boxoffice/tickets/checkout/10/venta-registrada');

        $issued = EventosFuncionesBoletos::query()->orderBy('pk_evento_localidad')->orderBy('asiento')->get(['pk_evento_localidad', 'asiento']);

        $this->assertSame([30, 30, 31, 31], $issued->pluck('pk_evento_localidad')->map(fn ($id) => (int) $id)->all());
        $this->assertSame(['A1', 'A2'], $issued->where('pk_evento_localidad', 30)->pluck('asiento')->values()->all());
    }

    public function test_a_zone_cannot_be_repeated_in_the_same_sale(): void
    {
        $selection = $this->selection();
        $selection['secciones'][1] = ['localidad' => 30, 'seccion' => 'pref', 'asientos' => ['A3']];

        $this->actingAs($this->seller, 'boxoffice')
            ->post('/boxoffice/tickets/checkout/10/seleccion', $selection)
            ->assertSessionHasErrors('secciones');
    }

    public function test_every_zone_needs_tickets(): void
    {
        $selection = $this->selection();
        $selection['secciones'][1]['cantidad'] = null;

        $this->actingAs($this->seller, 'boxoffice')
            ->post('/boxoffice/tickets/checkout/10/seleccion', $selection)
            ->assertSessionHasErrors('asientos');
    }

    public function test_the_ticket_limit_counts_every_zone(): void
    {
        config(['boxoffice.max_tickets_per_sale' => 3]);

        $this->actingAs($this->seller, 'boxoffice')
            ->post('/boxoffice/tickets/checkout/10/seleccion', $this->selection())
            ->assertSessionHasErrors('asientos');

        $this->assertNull(app(CheckoutStore::class)->get(10));
    }

    public function test_a_single_zone_sale_keeps_its_original_shape(): void
    {
        $this->actingAs($this->seller, 'boxoffice')
            ->post('/boxoffice/tickets/checkout/10/seleccion', [
                'funcion' => 20, 'tipo_pago' => 1, 'localidad' => 30, 'seccion' => 'pref', 'asientos' => ['A1'],
            ])
            ->assertSessionHasNoErrors();

        $state = app(CheckoutStore::class)->get(10);

        $this->assertSame(30, $state->localityId);
        $this->assertSame('pref', $state->sectionId);
        $this->assertSame([], $state->capacities);
    }

    private function selection(): array
    {
        return ['funcion' => 20, 'tipo_pago' => 1, 'secciones' => [
            ['localidad' => 30, 'seccion' => 'pref', 'asientos' => ['A1', 'A2']],
            ['localidad' => 31, 'seccion' => 'gen', 'cantidad' => 2],
        ]];
    }

    private function addGeneralZone(): void
    {
        DB::table('eventos_localidades')->insert([
            'pk_evento_localidad' => 31, 'pk_evento' => 10, 'evento_localidad' => 'General',
            'precio' => 200, 'comision' => 10, 'comisionWeb' => 20,
        ]);

        $general = <<<'XML'
        <section name="General" section_id="gen" locality_id="31" color="#3b82f6" capacity="50">
            <area color="#3b82f6" name="General">
                <vertex x="0" y="200"/><vertex x="100" y="200"/><vertex x="100" y="300"/>
            </area>
        </section>
    </sections>
XML;

        DB::table('view_eventos')->update(['escenario_xml' => str_replace('</sections>', $general, $this->stageXml())]);
    }
}
