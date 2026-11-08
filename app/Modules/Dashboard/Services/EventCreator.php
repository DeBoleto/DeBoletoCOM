<?php

namespace App\Modules\Dashboard\Services;

use App\Modules\Events\Models\Eventos;
use App\Modules\Events\Models\EventosFunciones;
use App\Modules\Events\Models\EventosLocalidades;
use App\Modules\Events\Models\EventosPeriodosVentas;
use App\Modules\Events\Models\UsuariosEventos;
use App\Modules\Venues\Models\EscenariosFormatos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Alta de un evento con sus funciones, periodo de venta, localidades (tomadas
 * del XML del formato del recinto) y asignaciones por defecto.
 */
class EventCreator
{
    /**
     * @param  array{pk_usuario: int, pk_escenario: int, pk_escenario_formato: int, evento: string, descripcion: ?string, comentarios: ?string}  $data
     * @param  list<string>  $functions  Fechas y horas "Y-m-d H:i".
     * @param  array<int, float>  $prices  locality_id del XML => precio.
     */
    public function create(array $data, array $functions, array $prices, UploadedFile $image): Eventos
    {
        $format = EscenariosFormatos::query()->activos()->where('pk_escenario', $data['pk_escenario'])->findOrFail($data['pk_escenario_formato']);
        $localities = $format->localities();
        $defaults = config('boxoffice.new_event');
        $tz = config('boxoffice.timezone');
        $dates = collect($functions)->map(fn (string $date) => Carbon::createFromFormat('Y-m-d H:i', $date, $tz))->sort()->values();
        $imageName = $this->storeImage($image);

        return DB::transaction(function () use ($data, $dates, $prices, $localities, $defaults, $imageName) {
            $event = new Eventos;
            $event->forceFill([
                'pk_usuario' => $data['pk_usuario'],
                'pk_escenario' => $data['pk_escenario'],
                'pk_escenario_formato' => $data['pk_escenario_formato'],
                'pk_evento_status' => $defaults['status'],
                'pk_evento_comision_tipo' => $defaults['commission_type'],
                'pk_boleto_formato_tipo' => $defaults['ticket_format'],
                'pk_evento_categoria' => $defaults['category'],
                'comision' => $defaults['commission'],
                'evento' => $data['evento'],
                'imagen' => $imageName,
                'descripcion' => $data['descripcion'] ?? '',
                'comentarios' => $data['comentarios'] ?? '',
                'inicio_fecha' => $dates->first(),
                'fin_fecha' => $dates->last(),
            ])->save();

            foreach ($dates as $date) {
                (new EventosFunciones)->forceFill(['pk_evento' => $event->pk_evento, 'fecha' => $date])->save();
            }

            $period = new EventosPeriodosVentas;
            $period->forceFill(['pk_evento' => $event->pk_evento, 'periodo_venta' => 'normal'])->save();

            foreach ($localities as $localityId => $name) {
                (new EventosLocalidades)->forceFill([
                    'pk_evento' => $event->pk_evento,
                    'pk_evento_periodo_venta' => $period->pk_evento_periodo_venta,
                    'evento_localidad' => $name,
                    'precio' => (float) ($prices[$localityId] ?? 0),
                ])->save();
            }

            DB::table('eventos_totales')->insert([
                'pk_evento' => $event->pk_evento,
                'total_boletos' => 0,
                'total_boletos_vendidos' => 0,
                'total_venta' => 0,
                'total_comision_aplicacion' => 0,
                'total_comision_pago_linea' => 0,
                'total_utilidad' => 0,
            ]);

            foreach (array_unique([...$defaults['assigned_users'], $data['pk_usuario']]) as $userId) {
                UsuariosEventos::query()->firstOrCreate(
                    ['pk_usuario' => $userId, 'pk_evento' => $event->pk_evento],
                    ['eliminado' => 0],
                );
            }

            return $event;
        });
    }

    public function storeImage(UploadedFile $image, ?string $directory = null): string
    {
        $name = now()->format('Ymd').Str::random(24).'.'.strtolower($image->extension());
        Storage::disk(config('boxoffice.images_disk'))->putFileAs($directory ?? config('boxoffice.event_images_dir'), $image, $name);

        return $name;
    }
}
