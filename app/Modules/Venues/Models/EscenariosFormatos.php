<?php

namespace App\Modules\Venues\Models;

use App\Modules\Shared\Models\LegacyModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuración de mapa (XML) de un recinto: secciones, localidades y asientos.
 *
 * @property int $pk_escenario_formato
 * @property int $pk_escenario
 * @property string $escenario_formato
 * @property string $escenario_xml
 */
class EscenariosFormatos extends LegacyModel
{
    protected $table = 'escenarios_formatos';

    protected $primaryKey = 'pk_escenario_formato';

    protected $hidden = ['escenario_xml'];

    protected function casts(): array
    {
        return ['pk_escenario' => 'integer', 'eliminado' => 'boolean'];
    }

    public function escenario(): BelongsTo
    {
        return $this->belongsTo(Escenarios::class, 'pk_escenario', 'pk_escenario');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('eliminado', 0);
    }

    /**
     * Localidades declaradas en el XML: [locality_id => nombre].
     *
     * @return array<int, string>
     */
    public function localities(): array
    {
        $xml = simplexml_load_string((string) $this->escenario_xml, null, LIBXML_NONET | LIBXML_NOCDATA);

        if ($xml === false || ! isset($xml->localities)) {
            return [];
        }

        $localities = [];
        foreach ($xml->localities->locality as $locality) {
            $localities[(int) $locality['locality_id']] = (string) $locality['name'];
        }

        return $localities;
    }
}
