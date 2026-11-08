<?php

namespace App\Modules\Venues\Models;

use App\Modules\Shared\Models\LegacyModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $pk_estado
 * @property string $estado
 */
class Estados extends LegacyModel
{
    protected $table = 'estados';

    protected $primaryKey = 'pk_estado';

    protected function casts(): array
    {
        return ['eliminado' => 'boolean'];
    }

    public function ciudades(): HasMany
    {
        return $this->hasMany(Ciudades::class, 'pk_estado', 'pk_estado');
    }
}
