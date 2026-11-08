<?php

namespace App\Modules\Events\Models\Views;

use App\Modules\Shared\Models\ReadOnlyViewModel;

/**
 * Vista `view_eventos_funciones`: función con fecha y hora ya formateadas.
 *
 * @property int $pk_evento_funcion
 * @property int $pk_evento
 * @property string $fecha
 * @property string $fecha_natural
 * @property string $hora_natural
 */
class EventoFuncionVista extends ReadOnlyViewModel
{
    protected $table = 'view_eventos_funciones';

    protected $primaryKey = 'pk_evento_funcion';

    protected function casts(): array
    {
        return ['pk_evento_funcion' => 'integer', 'pk_evento' => 'integer'];
    }
}
