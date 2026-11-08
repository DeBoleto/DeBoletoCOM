<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Modelo de solo lectura sobre una vista SQL (view_*) del esquema legacy.
 */
abstract class ReadOnlyViewModel extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $guarded = ['*'];

    public function save(array $options = []): bool
    {
        throw new LogicException(static::class.' es una vista de solo lectura.');
    }

    public function delete(): ?bool
    {
        throw new LogicException(static::class.' es una vista de solo lectura.');
    }
}
