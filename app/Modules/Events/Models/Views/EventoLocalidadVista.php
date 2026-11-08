<?php

namespace App\Modules\Events\Models\Views;

use App\Modules\Shared\Models\ReadOnlyViewModel;

/**
 * Vista `view_eventos_localidades`.
 *
 * @property int $pk_evento_localidad
 * @property int $pk_evento
 * @property string $evento_localidad
 * @property float $precio
 * @property float $comision
 * @property float $comisionWeb
 */
class EventoLocalidadVista extends ReadOnlyViewModel
{
    protected $table = 'view_eventos_localidades';

    protected $primaryKey = 'pk_evento_localidad';

    protected function casts(): array
    {
        return [
            'pk_evento_localidad' => 'integer',
            'pk_evento' => 'integer',
            'precio' => 'float',
            'comision' => 'float',
            'comisionWeb' => 'float',
        ];
    }
}
