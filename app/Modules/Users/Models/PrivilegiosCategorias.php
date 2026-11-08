<?php

namespace App\Modules\Users\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Agrupador del menú del back-office.
 *
 * @property int $pk_privilegio_categoria
 * @property string $privilegio_categoria
 * @property int $menu_orden
 * @property string|null $menu_icono
 */
class PrivilegiosCategorias extends Model
{
    public $timestamps = false;

    protected $table = 'privilegios_categorias';

    protected $primaryKey = 'pk_privilegio_categoria';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['menu_orden' => 'integer'];
    }

    public function privilegios(): HasMany
    {
        return $this->hasMany(Privilegios::class, 'pk_privilegio_categoria', 'pk_privilegio_categoria');
    }
}
