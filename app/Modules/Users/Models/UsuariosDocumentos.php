<?php

namespace App\Modules\Users\Models;

use App\Modules\Shared\Models\LegacyModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Documento adjunto a un usuario del back-office (identificación, contrato…).
 * El archivo vive en el disco local, carpeta usuarios_documentos.
 *
 * @property int $pk_usuario_documento
 * @property int $pk_usuario
 * @property string $documento
 * @property string|null $descripcion
 * @property string $fichero
 * @property bool $privado
 * @property bool $notificar
 * @property bool $vigencia
 */
class UsuariosDocumentos extends LegacyModel
{
    public const DIRECTORY = 'usuarios_documentos';

    protected $table = 'usuarios_documentos';

    protected $primaryKey = 'pk_usuario_documento';

    protected function casts(): array
    {
        return [
            'pk_usuario' => 'integer',
            'privado' => 'boolean',
            'notificar' => 'boolean',
            'vigencia' => 'boolean',
            'eliminado' => 'boolean',
            'expedicion_fecha' => 'date',
            'vencimiento_fecha' => 'date',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'pk_usuario', 'pk_usuario');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('eliminado', 0);
    }

    public function path(): string
    {
        return self::DIRECTORY.'/'.basename($this->fichero);
    }
}
