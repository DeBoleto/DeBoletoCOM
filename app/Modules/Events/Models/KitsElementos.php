<?php

namespace App\Modules\Events\Models;

use App\Modules\Shared\Models\LegacyModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo de lo que puede incluir el kit de una carrera (playera, número, medalla...).
 *
 * @property int $pk_kit_elemento
 * @property string $kit_elemento
 * @property int $orden
 * @property bool $eliminado
 */
class KitsElementos extends LegacyModel
{
    protected $table = 'kits_elementos';

    protected $primaryKey = 'pk_kit_elemento';

    protected function casts(): array
    {
        return ['orden' => 'integer', 'eliminado' => 'boolean'];
    }

    public function eventosKits(): HasMany
    {
        return $this->hasMany(EventosKitsElementos::class, 'pk_kit_elemento', 'pk_kit_elemento');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('eliminado', 0)->orderBy('orden')->orderBy('kit_elemento');
    }
}
