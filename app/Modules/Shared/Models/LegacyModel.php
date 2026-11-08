<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Base para tablas del esquema legacy (deboletox).
 *
 * - Las fechas de auditoría se guardan en la zona horaria del sistema legacy,
 *   no en la de la aplicación, porque ambos sistemas comparten la base de datos.
 * - Las columnas de autor (creacion_pk_usuario / modificacion_pk_usuario) se
 *   llenan con el usuario autenticado del guard de taquilla.
 */
abstract class LegacyModel extends Model
{
    public const CREATED_AT = 'creacion_fecha';

    public const UPDATED_AT = 'modificacion_fecha';

    public const CREATED_BY = 'creacion_pk_usuario';

    public const UPDATED_BY = 'modificacion_pk_usuario';

    protected static function booted(): void
    {
        static::creating(function (LegacyModel $model) {
            $userId = static::currentUserId();

            if ($userId === null) {
                return;
            }

            if ($model::CREATED_BY !== null && $model->getAttribute($model::CREATED_BY) === null) {
                $model->setAttribute($model::CREATED_BY, $userId);
            }

            if ($model::UPDATED_BY !== null && $model->getAttribute($model::UPDATED_BY) === null) {
                $model->setAttribute($model::UPDATED_BY, $userId);
            }
        });

        static::updating(function (LegacyModel $model) {
            $userId = static::currentUserId();

            if ($userId !== null && $model::UPDATED_BY !== null) {
                $model->setAttribute($model::UPDATED_BY, $userId);
            }
        });
    }

    public function freshTimestamp(): Carbon
    {
        return Carbon::now(static::legacyTimezone());
    }

    public static function legacyTimezone(): string
    {
        return config('boxoffice.timezone', 'America/Mexico_City');
    }

    protected static function currentUserId(): ?int
    {
        $id = Auth::guard('boxoffice')->id() ?? Auth::id();

        return $id === null ? null : (int) $id;
    }
}
