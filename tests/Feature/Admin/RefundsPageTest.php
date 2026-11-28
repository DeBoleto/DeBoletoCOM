<?php

namespace Tests\Feature\Admin;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\LegacyCheckoutSchema;
use Tests\TestCase;

class RefundsPageTest extends TestCase
{
    use LegacyCheckoutSchema, RefreshDatabase;

    public function test_refunds_page_totals_sold_requested_and_refunded_purchases(): void
    {
        config(['boxoffice.enforce_menu_privileges' => false]);

        $this->withoutVite();
        $this->createLegacyCheckoutTables();
        $this->seedSeatMapEvent();

        Schema::create('reembolso_compra', function (Blueprint $table) {
            $table->increments('pk_reembolso_compra');
            $table->integer('pk_evento_compra');
        });

        $admin = $this->createCustomer(['pk_usuario_tipo' => 1, 'correo' => 'admin@deboleto.mx']);

        foreach ([1 => 1, 2 => 1, 3 => 3] as $purchaseId => $status) {
            DB::table('eventos_compras')->insert(['pk_evento_compra' => $purchaseId, 'pk_evento' => 10, 'pk_evento_compra_tipo' => 3, 'estatus' => $status, 'pk_usuario' => $admin->pk_usuario]);
            DB::table('eventos_funciones_boletos')->insert(['pk_evento_compra' => $purchaseId, 'pk_evento' => 10, 'pk_evento_funcion' => 20, 'pk_evento_localidad' => 30, 'pk_evento_funcion_boleto_status' => 1, 'asiento' => "A{$purchaseId}", 'precio' => 100, 'comision_deboleto' => 10]);
        }

        DB::table('reembolso_compra')->insert([['pk_evento_compra' => 2], ['pk_evento_compra' => 3]]);

        $this->actingAs($admin, 'admin')
            ->withSession(['backoffice.menu.admin' => []])
            ->get('/dashboard/eventos/10/reembolsos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Refunds/Index')
                ->where('totals.sold', 330)
                ->where('totals.requested', 220)
                ->where('totals.refunded', 110)
                ->has('purchases', 3));
    }
}
