<?php

namespace App\Modules\BoxOffice\Queries;

use App\Modules\Orders\Enums\PaymentType;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Agregados por evento para los reportes generales y detallados.
 * Una sola consulta sustituye las decenas de COUNT() por localidad de legacy.
 */
class EventReportQuery
{
    /**
     * Boletos agrupados por función, localidad, precio, estatus y cortesía.
     *
     * @param  bool  $defenderOnly  Solo compras con total_defender > 0 (reportes "TD").
     */
    public function ticketAggregates(int $eventId, bool $defenderOnly = false): Collection
    {
        return DB::table('eventos_funciones_boletos as b')
            ->join('eventos_compras as c', 'c.pk_evento_compra', '=', 'b.pk_evento_compra')
            ->where('b.pk_evento', $eventId)
            ->where('b.ocultar', 0)
            ->when($defenderOnly, fn (Builder $query) => $query->where('c.total_defender', '>', 0))
            ->groupBy('b.pk_evento_funcion', 'b.pk_evento_localidad', 'b.precio', 'b.pk_evento_funcion_boleto_status', 'b.cortesia')
            ->select(['b.pk_evento_funcion', 'b.pk_evento_localidad', 'b.precio', 'b.pk_evento_funcion_boleto_status', 'b.cortesia'])
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(b.precio) as amount')
            ->selectRaw('SUM(b.comision_deboleto) as service_fee')
            ->selectRaw('SUM(b.comision_servicio) as payment_fee')
            ->selectRaw('SUM(CASE WHEN c.pk_evento_compra_tipo = ? THEN 1 ELSE 0 END) as web_tickets', [PaymentType::Web->value])
            ->selectRaw('COUNT(DISTINCT CASE WHEN c.pk_evento_compra_tipo = ? THEN c.pk_evento_compra END) as web_purchases', [PaymentType::Web->value])
            ->get()
            ->map(function ($row) {
                foreach (['pk_evento_funcion', 'pk_evento_localidad', 'pk_evento_funcion_boleto_status', 'cortesia', 'total', 'web_tickets', 'web_purchases'] as $key) {
                    $row->{$key} = (int) $row->{$key};
                }
                foreach (['precio', 'amount', 'service_fee', 'payment_fee'] as $key) {
                    $row->{$key} = (float) $row->{$key};
                }

                return $row;
            });
    }

    /**
     * Pagos registrados al organizador.
     */
    public function payments(int $eventId): Collection
    {
        return DB::table('gastos')
            ->where('id_evento', $eventId)
            ->orderBy('fecha')
            ->get(['descripcion', 'monto', 'motivo', 'fecha', 'comentarios'])
            ->map(fn ($row) => [
                'description' => $row->descripcion,
                'amount' => (float) $row->monto,
                'reason' => match ((int) $row->motivo) {
                    1 => 'Pago a organizador por transferencia',
                    2 => 'Pago a organizador en efectivo',
                    3 => 'Orden de pago',
                    default => 'Pago en boletos',
                },
                'date' => $row->fecha ? Carbon::parse($row->fecha)->format('d/m/Y') : null,
                'comments' => $row->comentarios,
            ]);
    }

    /**
     * Compras web con envío por WhatsApp, por función y localidad.
     */
    public function whatsappSales(int $eventId): Collection
    {
        return DB::table('eventos_funciones_boletos as b')
            ->join('eventos_localidades as l', 'l.pk_evento_localidad', '=', 'b.pk_evento_localidad')
            ->join('eventos_compras as c', 'c.pk_evento_compra', '=', 'b.pk_evento_compra')
            ->where('c.pk_evento_compra_tipo', PaymentType::Web->value)
            ->where('b.pk_evento', $eventId)
            ->groupBy('b.pk_evento_funcion', 'l.evento_localidad')
            ->select(['b.pk_evento_funcion', 'l.evento_localidad'])
            ->selectRaw('COUNT(DISTINCT b.pk_evento_compra) as purchases')
            ->selectRaw('COUNT(b.pk_evento_funcion_boleto) as tickets')
            ->get();
    }

    /**
     * Boletos de estacionamiento vendidos por localidad del boleto principal.
     */
    public function parking(int $eventId): Collection
    {
        return DB::table('eventos_estacionamiento_boleto as e')
            ->join('eventos_compras as c', 'c.pk_evento_compra', '=', 'e.pk_evento_compra')
            ->join('eventos_funciones_boletos as b', 'b.pk_evento_compra', '=', 'c.pk_evento_compra')
            ->join('eventos_localidades as l', 'l.pk_evento_localidad', '=', 'b.pk_evento_localidad')
            ->where('e.pk_evento', $eventId)
            ->groupBy('l.pk_evento_localidad', 'l.evento_localidad')
            ->orderBy('l.pk_evento_localidad')
            ->select('l.evento_localidad')
            ->selectRaw('COUNT(DISTINCT e.pk_eventos_estacionamiento_boleto) as total')
            ->get();
    }

    public function parkingTotal(int $eventId): int
    {
        return DB::table('eventos_estacionamiento_boleto')->where('pk_evento', $eventId)->count();
    }

    /**
     * Ventas de un día por forma de pago, una fila por compra y asiento.
     */
    public function dailySales(int $eventId, Carbon $day, bool $percentageCommission): Collection
    {
        $commission = $percentageCommission
            ? '((b.precio * l.comision) / 100) * COUNT(l.pk_evento_localidad)'
            : 'l.comision * COUNT(l.pk_evento_localidad)';

        return DB::table('eventos_compras as c')
            ->join('eventos_funciones_boletos as b', 'b.pk_evento_compra', '=', 'c.pk_evento_compra')
            ->join('eventos_localidades as l', 'l.pk_evento_localidad', '=', 'b.pk_evento_localidad')
            ->where('c.pk_evento', $eventId)
            ->where('c.pk_evento_compra_tipo', '!=', PaymentType::Courtesy->value)
            ->where('c.creacion_fecha', '>=', $day->copy()->startOfDay()->toDateTimeString())
            ->where('c.creacion_fecha', '<', $day->copy()->addDay()->startOfDay()->toDateTimeString())
            ->groupBy('c.pk_evento_compra', 'c.pk_evento_compra_tipo', 'c.creacion_fecha', 'b.asiento', 'b.pk_evento_localidad', 'l.evento_localidad', 'l.comision', 'b.precio')
            ->orderBy('c.creacion_fecha')
            ->select(['c.pk_evento_compra', 'c.pk_evento_compra_tipo', 'c.creacion_fecha', 'b.asiento', 'b.pk_evento_localidad', 'l.evento_localidad'])
            ->selectRaw('COUNT(l.pk_evento_localidad) as tickets')
            ->selectRaw('b.precio * COUNT(l.pk_evento_localidad) as amount')
            ->selectRaw("{$commission} as commission")
            ->get();
    }

    /**
     * Accesos registrados por localidad en una función.
     */
    public function entries(int $eventId, int $functionId): Collection
    {
        return DB::table('eventos_contador_boletos as e')
            ->join('eventos_funciones_boletos as b', 'b.pk_evento_funcion_boleto', '=', 'e.pk_evento_funcion_boleto')
            ->join('eventos_localidades as l', 'l.pk_evento_localidad', '=', 'b.pk_evento_localidad')
            ->where('e.pk_evento', $eventId)
            ->where('e.pk_evento_funcion', $functionId)
            ->groupBy('l.evento_localidad')
            ->select('l.evento_localidad as locality')
            ->selectRaw('COUNT(e.pk_evento_funcion_boleto) as total')
            ->get();
    }

    /**
     * Boletos pagados y no disponibles (sin cortesías) por localidad, para aforo.
     *
     * @return Collection<int, int> pk_evento_localidad => boletos
     */
    public function occupancy(int $eventId): Collection
    {
        return DB::table('eventos_funciones_boletos')
            ->where('pk_evento', $eventId)
            ->whereIn('pk_evento_funcion_boleto_status', [2, 3])
            ->where('cortesia', 0)
            ->groupBy('pk_evento_localidad')
            ->selectRaw('pk_evento_localidad, COUNT(*) as total')
            ->pluck('total', 'pk_evento_localidad')
            ->map(fn ($total) => (int) $total);
    }

    /**
     * Registro de participantes de carreras con los boletos aún sin registrar.
     */
    public function raceParticipants(int $eventId, ?Carbon $from, ?Carbon $to): array
    {
        $registered = DB::table('evento_boleto_carrera as r')
            ->join('eventos_compras as c', 'c.pk_evento_compra', '=', 'r.pk_evento_compra')
            ->join('eventos_funciones_boletos as b', 'b.pk_evento_funcion_boleto', '=', 'r.pk_evento_funcion_boleto')
            ->join('eventos_localidades as l', 'l.pk_evento_localidad', '=', 'b.pk_evento_localidad')
            ->where('c.pk_evento', $eventId)
            ->when($from, fn (Builder $query) => $query->where('r.creacion_fecha', '>=', $from->copy()->startOfDay()->toDateTimeString()))
            ->when($to, fn (Builder $query) => $query->where('r.creacion_fecha', '<', $to->copy()->addDay()->startOfDay()->toDateTimeString()))
            ->select([
                'r.pk_evento_funcion_boleto', 'l.pk_evento_localidad', 'l.evento_localidad', 'r.num_participante',
                'r.nombre', 'r.correo', 'r.telefono', 'r.creacion_fecha', 'r.fecha_nacimiento', 'r.talla',
                'r.tipo_sangre', 'r.tipo_extra', 'r.tipo_extrab', 'r.genero', 'r.discapacidad',
                'r.nombre_emergencia', 'r.telefono_emergencia',
            ])
            ->distinct()
            ->get();

        $pending = DB::table('eventos_funciones_boletos as b')
            ->join('eventos_localidades as l', 'l.pk_evento_localidad', '=', 'b.pk_evento_localidad')
            ->where('b.pk_evento', $eventId)
            ->whereNotExists(fn (Builder $query) => $query->from('evento_boleto_carrera as r')
                ->whereColumn('r.pk_evento_funcion_boleto', 'b.pk_evento_funcion_boleto'))
            ->orderBy('b.pk_evento_funcion_boleto')
            ->get(['b.pk_evento_funcion_boleto', 'l.evento_localidad']);

        return ['registered' => $registered, 'pending' => $pending];
    }
}
