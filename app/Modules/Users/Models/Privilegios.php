<?php

namespace App\Modules\Users\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Privilegio del back-office. Los que tienen `menu = 1` forman el menú lateral.
 *
 * @property int $pk_privilegio
 * @property int $pk_privilegio_categoria
 * @property string $privilegio
 * @property string $etiqueta
 * @property bool $menu
 * @property int $menu_orden
 * @property string|null $menu_url
 * @property bool $activo
 */
class Privilegios extends Model
{
    public $timestamps = false;

    protected $table = 'privilegios';

    protected $primaryKey = 'pk_privilegio';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'pk_privilegio_categoria' => 'integer',
            'menu' => 'boolean',
            'menu_orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(PrivilegiosCategorias::class, 'pk_privilegio_categoria', 'pk_privilegio_categoria');
    }

    public function scopeMenu(Builder $query): Builder
    {
        return $query->where('menu', 1)->where('activo', 1);
    }
}
