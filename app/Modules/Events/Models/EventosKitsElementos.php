<?php

namespace App\Modules\Events\Models;

use App\Modules\Shared\Models\LegacyModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Elemento incluido en el kit de una carrera.
 *
 * @property int $pk_evento_kit_elemento
 * @property int $pk_evento_kit
 * @property int $pk_kit_elemento
 * @property bool $eliminado
 */
class EventosKitsElementos extends LegacyModel
{
    protected $table = 'eventos_kits_elementos';

    protected $primaryKey = 'pk_evento_kit_elemento';

    protected function casts(): array
    {
        return [
            'pk_evento_kit' => 'integer',
            'pk_kit_elemento' => 'integer',
            'eliminado' => 'boolean',
        ];
    }

    public function kit(): BelongsTo
    {
        return $this->belongsTo(EventosKits::class, 'pk_evento_kit', 'pk_evento_kit');
    }

    public function elemento(): BelongsTo
    {
        return $this->belongsTo(KitsElementos::class, 'pk_kit_elemento', 'pk_kit_elemento');
    }
}
