<?php

namespace App\Modules\Events\Models\Views;

use App\Modules\Shared\Models\ReadOnlyViewModel;

/**
 * Vista `view_eventos`: evento con recinto, ciudad, estado, XML del mapa y
 * textos de boleto ya resueltos.
 *
 * @property int $pk_evento
 * @property string $evento
 * @property string|null $imagen
 * @property string|null $imagen_boleto
 * @property string|null $titulo_boleto
 * @property string|null $subtitulo_boleto
 * @property string $escenario
 * @property string|null $direccion
 * @property string|null $direccion_evento
 * @property string $ciudad
 * @property string $estado
 * @property string|null $escenario_xml
 * @property string|null $comentarios
 * @property int $pk_evento_status
 * @property int|null $pk_evento_categoria
 * @property int|null $pk_evento_tipo
 * @property int|null $pk_boleto_formato_tipo
 * @property int $pk_evento_comision_tipo
 * @property int $promocion
 * @property bool $sin_estructura
 * @property int $bono
 */
class EventoVista extends ReadOnlyViewModel
{
    protected $table = 'view_eventos';

    protected $primaryKey = 'pk_evento';

    protected $hidden = ['escenario_xml'];

    protected function casts(): array
    {
        return [
            'pk_evento' => 'integer',
            'pk_escenario' => 'integer',
            'pk_evento_status' => 'integer',
            'pk_evento_categoria' => 'integer',
            'pk_evento_tipo' => 'integer',
            'pk_boleto_formato_tipo' => 'integer',
            'pk_evento_comision_tipo' => 'integer',
            'promocion' => 'integer',
            'sin_estructura' => 'boolean',
            'bono' => 'integer',
        ];
    }

    public function sellsWithoutSeatMap(): bool
    {
        return $this->pk_evento_categoria === 2 || $this->sin_estructura;
    }

    public function direccionVisible(): string
    {
        return (string) ($this->direccion_evento ?: $this->escenario);
    }
}
