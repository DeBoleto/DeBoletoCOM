<?php

namespace App\Modules\Events\Models\Views;

use App\Modules\Shared\Models\ReadOnlyViewModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * Vista `view_usuarios_eventos`: eventos asignados a cada usuario, con datos
 * de recinto para listados.
 *
 * @property int $pk_usuario
 * @property int $pk_evento
 * @property string $evento
 * @property string|null $imagen
 * @property string $escenario
 * @property string $ciudad
 * @property string $estado
 * @property string $inicio_fecha
 * @property string $inicio_fecha_natural
 * @property string $fin_fecha
 */
class UsuarioEventoVista extends ReadOnlyViewModel
{
    protected $table = 'view_usuarios_eventos';

    protected $primaryKey = 'pk_evento';

    protected function casts(): array
    {
        return ['pk_usuario' => 'integer', 'pk_evento' => 'integer', 'pk_evento_status' => 'integer'];
    }

    public function scopeDelUsuario(Builder $query, int $userId): Builder
    {
        return $query->where('pk_usuario', $userId);
    }
}
