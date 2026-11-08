<?php

namespace App\Modules\Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Precio promocional alterno de una localidad.
 *
 * @property int $id
 * @property int $pk_evento_localidad
 * @property float $precio
 */
class PromocionEventoLocalidades extends Model
{
    public $timestamps = false;

    protected $table = 'promocion_evento_localidades';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['pk_evento_localidad' => 'integer', 'precio' => 'float'];
    }

    public function localidad(): BelongsTo
    {
        return $this->belongsTo(EventosLocalidades::class, 'pk_evento_localidad', 'pk_evento_localidad');
    }
}
