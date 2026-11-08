<?php

namespace App\Modules\Venues\Models;

use App\Modules\Shared\Models\LegacyModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $pk_ciudad
 * @property int $pk_estado
 * @property string $ciudad
 */
class Ciudades extends LegacyModel
{
    protected $table = 'ciudades';

    protected $primaryKey = 'pk_ciudad';

    protected function casts(): array
    {
        return ['pk_estado' => 'integer', 'eliminado' => 'boolean'];
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estados::class, 'pk_estado', 'pk_estado');
    }

    public function escenarios(): HasMany
    {
        return $this->hasMany(Escenarios::class, 'pk_ciudad', 'pk_ciudad');
    }
}
