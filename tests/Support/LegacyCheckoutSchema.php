<?php

namespace Tests\Support;

use App\Modules\Users\Models\Usuarios;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas legacy mínimas para probar la compra web en SQLite. El esquema real
 * vive en MySQL sin migraciones; aquí solo se recrean las columnas que usa el flujo.
 */
trait LegacyCheckoutSchema
{
    protected function createLegacyCheckoutTables(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->increments('pk_usuario');
            $table->integer('pk_usuario_tipo')->default(5);
            $table->string('nombre')->nullable();
            $table->string('apellido_paterno')->nullable();
            $table->string('apellido_materno')->nullable();
            $table->string('correo')->nullable();
            $table->string('password')->nullable();
            $table->string('telefono')->nullable();
            $table->string('pk_customer')->nullable();
            $table->integer('pk_formato_boleto')->nullable();
            $table->integer('tipe_login')->nullable();
            $table->boolean('eliminado')->default(0);
            $table->dateTime('creacion_fecha')->nullable();
            $table->dateTime('modificacion_fecha')->nullable();
        });

        foreach (['eventos', 'view_eventos'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->integer('pk_evento')->primary();
                $table->string('evento');
                $table->string('url')->nullable();
                $table->string('imagen')->nullable();
                $table->integer('pk_escenario')->nullable();
                $table->integer('pk_evento_status')->default(2);
                $table->integer('pk_evento_categoria')->nullable();
                $table->integer('pk_evento_tipo')->nullable();
                $table->integer('pk_evento_comision_tipo')->default(2);
                $table->integer('pk_boleto_formato_tipo')->nullable();
                $table->integer('promocion')->default(0);
                $table->float('promocion_porcentaje')->default(0);
                $table->boolean('sin_estructura')->default(0);
                $table->integer('bono')->default(0);
                $table->integer('msi')->default(0);
                $table->integer('defender')->default(0);
                $table->integer('estacionamiento')->default(0);
                $table->float('estacionamiento_precio')->default(0);
                $table->float('estacionamiento_comision')->default(0);
                $table->dateTime('inicio_fecha')->nullable();
                $table->boolean('eliminado')->default(0);
                $table->dateTime('creacion_fecha')->nullable();
                $table->dateTime('modificacion_fecha')->nullable();
                $table->integer('creacion_pk_usuario')->nullable();
                $table->integer('modificacion_pk_usuario')->nullable();

                if ($name === 'view_eventos') {
                    $table->integer('bool_venta_web')->default(1);
                    $table->string('escenario')->nullable();
                    $table->string('direccion')->nullable();
                    $table->string('direccion_evento')->nullable();
                    $table->string('ciudad')->nullable();
                    $table->string('estado')->nullable();
                    $table->text('escenario_xml')->nullable();
                    $table->text('comentarios')->nullable();
                }
            });
        }

        Schema::create('view_eventos_funciones', function (Blueprint $table) {
            $table->integer('pk_evento_funcion')->primary();
            $table->integer('pk_evento');
            $table->dateTime('fecha')->nullable();
            $table->string('fecha_natural')->nullable();
            $table->string('hora_natural')->nullable();
            $table->integer('cerrado')->default(0);
        });

        Schema::create('eventos_localidades', function (Blueprint $table) {
            $table->integer('pk_evento_localidad')->primary();
            $table->integer('pk_evento');
            $table->string('evento_localidad');
            $table->float('precio')->default(0);
            $table->float('comision')->default(0);
            $table->float('comisionWeb')->default(0);
            $table->integer('capacidad')->nullable();
            $table->integer('promocion')->default(0);
            $table->float('promocion_porcentaje')->default(0);
            $table->integer('cerrada')->default(0);
            $table->integer('reglas')->default(0);
            $table->text('reglas_text')->nullable();
            $table->boolean('eliminado')->default(0);
            $table->dateTime('creacion_fecha')->nullable();
            $table->dateTime('modificacion_fecha')->nullable();
            $table->integer('creacion_pk_usuario')->nullable();
            $table->integer('modificacion_pk_usuario')->nullable();
        });

        Schema::create('eventos_localidades_especiales', function (Blueprint $table) {
            $table->increments('pk_evento_localidad_especiales');
            $table->integer('pk_evento_localidad');
            $table->string('evento_localidad');
            $table->float('precio')->default(0);
            $table->float('comision')->default(0);
            $table->float('comisionWeb')->default(0);
            $table->dateTime('creacion_fecha')->nullable();
            $table->dateTime('modificacion_fecha')->nullable();
            $table->integer('creacion_pk_usuario')->nullable();
            $table->integer('modificacion_pk_usuario')->nullable();
        });

        Schema::create('eventos_localidades_asientos', function (Blueprint $table) {
            $table->increments('pk_evento_localidad_asiento');
            $table->integer('pk_evento_localidad');
            $table->integer('cantidad_asientos');
            $table->integer('es_numero')->default(1);
        });

        Schema::create('eventos_localidades_promos', function (Blueprint $table) {
            $table->increments('pk_localidad_promo');
            $table->integer('pk_evento_localidad');
            $table->float('promocion');
            $table->integer('num_boleto');
        });

        Schema::create('eventos_periodos_ventas', function (Blueprint $table) {
            $table->increments('pk_evento_periodo_venta');
            $table->integer('pk_evento');
            $table->dateTime('creacion_fecha')->nullable();
            $table->dateTime('modificacion_fecha')->nullable();
            $table->integer('creacion_pk_usuario')->nullable();
            $table->integer('modificacion_pk_usuario')->nullable();
        });

        Schema::create('eventos_compras_tipos', function (Blueprint $table) {
            $table->integer('pk_evento_compra_tipo')->primary();
            $table->string('evento_compra_tipo')->nullable();
            $table->float('comision_porcentaje')->default(0);
        });

        Schema::create('eventos_compras', function (Blueprint $table) {
            $table->increments('pk_evento_compra');
            $table->integer('pk_usuario')->nullable();
            $table->integer('pk_evento')->nullable();
            $table->integer('pk_evento_funcion')->nullable();
            $table->integer('pk_evento_compra_tipo')->nullable();
            $table->string('codigo')->nullable();
            $table->string('pk_openpay')->nullable();
            $table->integer('pk_evento_codigo')->nullable();
            $table->integer('estatus')->default(0);
            $table->float('total_compra')->default(0);
            $table->float('total_comision_aplicacion')->default(0);
            $table->float('total_comision_tarjeta')->default(0);
            $table->float('total_comision_pago_linea')->default(0);
            $table->float('total_utilidad')->default(0);
            $table->float('total_envio')->nullable();
            $table->float('total_descuento')->nullable();
            $table->float('total_defender')->nullable();
            $table->string('comentario')->nullable();
            $table->string('tarjeta_banco')->nullable();
            $table->string('tarjeta_propietario')->nullable();
            $table->string('tarjeta_numero')->nullable();
            $table->string('tarjeta_mes_expiracion')->nullable();
            $table->string('tarjeta_ano_expiracion')->nullable();
            $table->dateTime('creacion_fecha')->nullable();
            $table->integer('creacion_pk_usuario')->nullable();
        });

        Schema::create('eventos_funciones_boletos', function (Blueprint $table) {
            $table->increments('pk_evento_funcion_boleto');
            $table->integer('pk_evento');
            $table->integer('pk_evento_funcion');
            $table->integer('pk_evento_localidad');
            $table->integer('pk_evento_localidad_especiales')->nullable();
            $table->integer('pk_evento_periodo_venta')->nullable();
            $table->integer('pk_evento_funcion_boleto_status');
            $table->integer('pk_evento_compra')->nullable();
            $table->string('asiento');
            $table->float('precio')->default(0);
            $table->float('comision_deboleto')->default(0);
            $table->float('comision_servicio')->default(0);
            $table->boolean('cortesia')->default(0);
            $table->boolean('impresion')->default(0);
            $table->boolean('ocultar')->default(0);
            $table->string('codigo')->nullable();
            $table->integer('adquisicion_pk_usuario')->nullable();
            $table->dateTime('adquisicion_fecha')->nullable();
            $table->dateTime('creacion_fecha')->nullable();
            $table->dateTime('modificacion_fecha')->nullable();
            $table->integer('creacion_pk_usuario')->nullable();
            $table->integer('modificacion_pk_usuario')->nullable();
        });

        Schema::create('eventos_reserva_boleto', function (Blueprint $table) {
            $table->increments('pk_evento_reserva_boleto');
            $table->string('pk_usuario');
            $table->integer('pk_evento');
            $table->integer('pk_evento_funcion');
            $table->string('pk_seccion');
            $table->string('asiento');
            $table->dateTime('creacion_fecha')->nullable();
        });

        Schema::create('eventos_compras_logs', function (Blueprint $table) {
            $table->increments('pk_evento_compra_log');
            $table->integer('pk_usuario')->nullable();
            $table->string('pk_openpay')->nullable();
            $table->text('json_compra')->nullable();
            $table->text('json_openpay')->nullable();
            $table->text('json_respuesta')->nullable();
            $table->integer('msi')->nullable();
            $table->integer('type')->nullable();
            $table->integer('promo_id')->nullable();
            $table->float('promo_total')->nullable();
            $table->dateTime('creacion_fecha')->nullable();
        });

        Schema::create('eventos_codigo_promocion', function (Blueprint $table) {
            $table->increments('pk_evento_codigo_promocion');
            $table->integer('pk_usuario')->nullable();
            $table->integer('pk_evento')->nullable();
            $table->integer('pk_localidad')->nullable();
            $table->string('codigo_promocion');
            $table->integer('tipo');
            $table->float('promocion');
            $table->integer('boletos')->nullable();
            $table->date('fecha_caducidad')->nullable();
            $table->dateTime('fecha_registro')->nullable();
        });

        Schema::create('eventos_estacionamiento_boleto', function (Blueprint $table) {
            $table->increments('pk_eventos_estacionamiento_boleto');
            $table->integer('pk_evento_compra');
            $table->integer('pk_evento');
            $table->string('lugar');
            $table->float('precio');
            $table->integer('asistencia')->nullable();
        });

        Schema::create('eventos_pixel', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('pk_evento');
            $table->string('pixel');
            $table->string('api');
        });

        Schema::create('contracargo_compra', function (Blueprint $table) {
            $table->increments('pk_contracargo_compra');
            $table->integer('pk_evento_compra');
            $table->string('pk_openpay');
            $table->integer('estatus')->default(0);
            $table->text('json_respuesta')->nullable();
            $table->dateTime('creacion_fecha')->nullable();
        });
    }

    /**
     * Evento publicado con venta web, una función y una localidad numerada.
     *
     * @param  array<string, mixed>  $event
     */
    protected function seedSeatMapEvent(array $event = [], array $locality = []): void
    {
        $base = [
            'pk_evento' => 10,
            'evento' => 'Concierto de prueba',
            'url' => 'concierto-de-prueba',
            'pk_evento_comision_tipo' => 2,
            'pk_evento_status' => 2,
        ] + $event;

        DB::table('eventos')->insert($base);
        DB::table('view_eventos')->insert($base + [
            'bool_venta_web' => 1,
            'escenario' => 'Foro',
            'ciudad' => 'Villahermosa',
            'estado' => 'Tabasco',
            'escenario_xml' => $this->stageXml(),
        ]);

        DB::table('view_eventos_funciones')->insert([
            'pk_evento_funcion' => 20, 'pk_evento' => 10, 'fecha' => '2026-12-12 20:00:00',
            'fecha_natural' => '12 de diciembre de 2026', 'hora_natural' => '20:00', 'cerrado' => 0,
        ]);

        DB::table('eventos_localidades')->insert([
            'pk_evento_localidad' => 30, 'pk_evento' => 10, 'evento_localidad' => 'Preferente',
            'precio' => 500, 'comision' => 20, 'comisionWeb' => 40,
        ] + $locality);

        DB::table('eventos_periodos_ventas')->insert(['pk_evento' => 10]);
        DB::table('eventos_compras_tipos')->insert([
            ['pk_evento_compra_tipo' => 1, 'comision_porcentaje' => 0],
            ['pk_evento_compra_tipo' => 2, 'comision_porcentaje' => 3],
            ['pk_evento_compra_tipo' => 3, 'comision_porcentaje' => 4],
            ['pk_evento_compra_tipo' => 4, 'comision_porcentaje' => 0],
        ]);
    }

    protected function createCustomer(array $attributes = []): Usuarios
    {
        $customer = new Usuarios;
        $customer->forceFill($attributes + [
            'pk_usuario_tipo' => 5,
            'nombre' => 'Ana',
            'apellido_paterno' => 'López',
            'correo' => 'ana@gmail.com',
            'telefono' => '9931234567',
            'password' => 'secreto-seguro',
            'eliminado' => 0,
        ])->save();

        return $customer;
    }

    protected function stageXml(): string
    {
        return <<<'XML'
<stage>
    <image size="1000"/>
    <sections>
        <section name="Preferente" section_id="pref" locality_id="30" color="#4ecba0">
            <area color="#4ecba0" name="Preferente">
                <vertex x="0" y="0"/><vertex x="100" y="0"/><vertex x="100" y="100"/>
            </area>
            <rows>
                <row y="10" fila="A">
                    <seat seat_id="A1" x="10"/>
                    <seat seat_id="A2" x="20"/>
                    <seat seat_id="A3" x="30"/>
                    <seat seat_id="A4" x="40"/>
                </row>
            </rows>
        </section>
    </sections>
</stage>
XML;
    }
}
