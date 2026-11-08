<?php

namespace App\Modules\Users\Models;

use App\Modules\Shared\Models\LegacyModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $pk_usuario_tipo
 * @property string $usuario_tipo
 * @property string|null $descripcion
 */
class UsuariosTipos extends LegacyModel
{
    protected $table = 'usuarios_tipos';

    protected $primaryKey = 'pk_usuario_tipo';

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuarios::class, 'pk_usuario_tipo', 'pk_usuario_tipo');
    }
}
