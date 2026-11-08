<?php

namespace App\Modules\Users\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivote usuarios <-> privilegios.
 *
 * @property int $pk_usuario
 * @property int $pk_privilegio
 */
class UsuariosPrivilegios extends Pivot
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'usuarios_privilegios';

    protected $guarded = ['*'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuarios::class, 'pk_usuario', 'pk_usuario');
    }

    public function privilegio(): BelongsTo
    {
        return $this->belongsTo(Privilegios::class, 'pk_privilegio', 'pk_privilegio');
    }
}
