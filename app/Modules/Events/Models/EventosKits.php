<?php

namespace App\Modules\Events\Models;

use App\Modules\Shared\Models\LegacyModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Entrega del kit de una carrera: cuándo y dónde (una fila por evento).
 *
 * @property int $pk_evento_kit
 * @property int $pk_evento
 * @property Carbon|null $entrega_fecha
 * @property string $entrega_lugar
 * @property bool $eliminado
 */
class EventosKits extends LegacyModel
{
    protected $table = 'eventos_kits';

    protected $primaryKey = 'pk_evento_kit';

    protected function casts(): array
    {
        return [
            'pk_evento' => 'integer',
            'entrega_fecha' => 'datetime',
            'eliminado' => 'boolean',
        ];
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Eventos::class, 'pk_evento', 'pk_evento');
    }

    public function elementos(): HasMany
    {
        return $this->hasMany(EventosKitsElementos::class, 'pk_evento_kit', 'pk_evento_kit')
            ->where('eliminado', 0);
    }

    public function kitsElementos(): BelongsToMany
    {
        return $this->belongsToMany(KitsElementos::class, 'eventos_kits_elementos', 'pk_evento_kit', 'pk_kit_elemento')
            ->wherePivot('eliminado', 0)
            ->orderBy('kits_elementos.orden');
    }
}
