<?php

namespace App\Modules\BoxOffice\Queries;

use App\Modules\Events\Models\Eventos;
use App\Modules\Users\Models\Usuarios;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Consultas del inicio de taquilla.
 */
class DashboardQuery
{
    /**
     * Eventos visibles en el inicio. Empresarios ven sus eventos (10); el resto
     * de perfiles, excepto vendedores, ve los 5 más recientes.
     */
    public function events(Usuarios $user, bool $past = false): Collection
    {
        if ($user->isSeller()) {
            return collect();
        }

        $query = $this->baseEvents()
            ->when($user->isPromoter(), function (Builder $query) use ($user) {
                $query->whereExists(fn (Builder $assigned) => $assigned
                    ->from('usuarios_eventos as ue')
                    ->whereColumn('ue.pk_evento', 'e.pk_evento')
                    ->where('ue.pk_usuario', $user->pk_usuario)
                    ->where('ue.eliminado', 0));
            })
            ->when(! $user->isPromoter(), fn (Builder $query) => $query->whereExists(fn (Builder $assigned) => $assigned
                ->from('usuarios_eventos as ue')
                ->whereColumn('ue.pk_evento', 'e.pk_evento')))
            ->when($past, fn (Builder $query) => $query->where('e.fin_fecha', '<', now(config('boxoffice.timezone'))->toDateString()))
            ->when(! $past, fn (Builder $query) => $query->where(fn (Builder $active) => $active
                ->whereNull('e.fin_fecha')
                ->orWhere('e.fin_fecha', '>=', now(config('boxoffice.timezone'))->toDateString())))
            ->limit($user->isPromoter() ? 10 : 5);

        return $query->get();
    }

    /**
     * Totales vendidos (funciones abiertas) y pagos registrados por evento.
     *
     * @param  list<int>  $eventIds
     * @return array<int, array{sold: float, paid: float, available: float}>
     */
    public function balances(array $eventIds): array
    {
        if ($eventIds === []) {
            return [];
        }

        $sold = DB::table('eventos_funciones_boletos as b')
            ->join('eventos_compras as c', 'c.pk_evento_compra', '=', 'b.pk_evento_compra')
            ->join('eventos_funciones as f', 'f.pk_evento_funcion', '=', 'c.pk_evento_funcion')
            ->whereIn('c.pk_evento', $eventIds)
            ->where('b.ocultar', 0)
            ->where('f.cerrado', 0)
            ->groupBy('c.pk_evento')
            ->selectRaw('c.pk_evento as event_id, COALESCE(SUM(b.precio), 0) as total')
            ->pluck('total', 'event_id');

        $paid = DB::table('gastos')
            ->whereIn('id_evento', $eventIds)
            ->groupBy('id_evento')
            ->selectRaw('id_evento as event_id, COALESCE(SUM(monto), 0) as total')
            ->pluck('total', 'event_id');

        return collect($eventIds)->mapWithKeys(function (int $eventId) use ($sold, $paid) {
            $soldAmount = (float) ($sold[$eventId] ?? 0);
            $paidAmount = (float) ($paid[$eventId] ?? 0);

            return [$eventId => [
                'sold' => $soldAmount,
                'paid' => $paidAmount,
                'available' => $soldAmount - $paidAmount,
            ]];
        })->all();
    }

    private function baseEvents(): Builder
    {
        return DB::table('eventos as e')
            ->join('escenarios as es', 'es.pk_escenario', '=', 'e.pk_escenario')
            ->join('ciudades as c', 'c.pk_ciudad', '=', 'es.pk_ciudad')
            ->join('estados as s', 's.pk_estado', '=', 'c.pk_estado')
            ->whereIn('e.pk_evento_status', [Eventos::STATUS_DRAFT, Eventos::STATUS_PUBLISHED])
            ->where('e.pk_evento_tipo', Eventos::TYPE_EVENT)
            ->orderByDesc('e.inicio_fecha')
            ->select([
                'e.pk_evento', 'e.evento', 'e.imagen', 'e.direccion', 'e.inicio_fecha',
                'es.escenario', 'c.ciudad', 's.estado',
            ]);
    }
}
