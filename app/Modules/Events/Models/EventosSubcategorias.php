<?php

namespace App\Modules\Events\Models;

use App\Modules\Shared\Models\LegacyModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Subcategoría de una categoría de eventos (la carga el organizador al aprobar).
 *
 * @property int $pk_evento_subcategoria
 * @property int $pk_evento_categoria
 * @property string $evento_subcategoria
 * @property bool $eliminado
 */
class EventosSubcategorias extends LegacyModel
{
    protected $table = 'eventos_subcategorias';

    protected $primaryKey = 'pk_evento_subcategoria';

    protected function casts(): array
    {
        return ['pk_evento_categoria' => 'integer', 'eliminado' => 'boolean'];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(EventosCategorias::class, 'pk_evento_categoria', 'pk_evento_categoria');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('eliminado', 0);
    }
}
