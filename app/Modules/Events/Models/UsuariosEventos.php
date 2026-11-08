<?php

namespace App\Modules\Events\Models;

use App\Modules\Users\Models\Usuarios;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asignación de un evento a un usuario del back-office.
 *
 * @property int $pk_usuario_evento
 * @property int $pk_usuario
 * @property int $pk_evento
 * @property bool $eliminado
 */
class UsuariosEventos extends Model
{
    public $timestamps = false;

    protected $table = 'usuarios_eventos';

    protected $primaryKey = 'pk_usuario_evento';

    protected $fillable = ['pk_usuario', 'pk_evento', 'eliminado'];

    protected function casts(): array
    {
        return [
            'pk_usuario' => 'integer',
            'pk_evento' => 'integer',
            'eliminado' => 'boolean',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'pk_usuario', 'pk_usuario');
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Eventos::class, 'pk_evento', 'pk_evento');
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('eliminado', 0);
    }
}
