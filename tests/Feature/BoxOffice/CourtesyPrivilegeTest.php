<?php

namespace Tests\Feature\BoxOffice;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\LegacyCheckoutSchema;
use Tests\TestCase;

class CourtesyPrivilegeTest extends TestCase
{
    use LegacyCheckoutSchema, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['boxoffice.enforce_menu_privileges' => false]);

        $this->withoutVite();
        $this->createLegacyCheckoutTables();

        Schema::create('privilegios', function (Blueprint $table) {
            $table->integer('pk_privilegio')->primary();
            $table->integer('pk_privilegio_categoria')->nullable();
            $table->integer('menu')->default(0);
            $table->integer('activo')->default(1);
            $table->integer('menu_orden')->default(0);
        });

        Schema::create('privilegios_categorias', function (Blueprint $table) {
            $table->integer('pk_privilegio_categoria')->primary();
            $table->integer('menu_orden')->default(0);
        });

        Schema::create('usuarios_privilegios', function (Blueprint $table) {
            $table->integer('pk_usuario');
            $table->integer('pk_privilegio');
        });

        Schema::create('view_usuarios_eventos', function (Blueprint $table) {
            foreach (['pk_usuario', 'pk_evento', 'pk_evento_status'] as $column) {
                $table->integer($column)->nullable();
            }
            foreach (['evento', 'imagen', 'escenario', 'ciudad', 'estado', 'inicio_fecha_natural', 'inicio_fecha', 'fin_fecha'] as $column) {
                $table->string($column)->nullable();
            }
        });
    }

    public function test_courtesies_are_forbidden_without_the_privilege(): void
    {
        $seller = $this->createCustomer(['pk_usuario_tipo' => 2, 'correo' => 'taquilla@deboleto.mx']);

        $this->actingAs($seller, 'boxoffice')
            ->get('/boxoffice/tickets-courtesy-create')
            ->assertForbidden();
    }

    public function test_courtesies_open_with_the_privilege(): void
    {
        $seller = $this->createCustomer(['pk_usuario_tipo' => 2, 'correo' => 'taquilla@deboleto.mx']);

        DB::table('privilegios')->insert(['pk_privilegio' => config('boxoffice.privileges.courtesies')]);
        DB::table('usuarios_privilegios')->insert(['pk_usuario' => $seller->pk_usuario, 'pk_privilegio' => config('boxoffice.privileges.courtesies')]);

        $this->actingAs($seller, 'boxoffice')
            ->get('/boxoffice/tickets-courtesy-create')
            ->assertOk();
    }
}
