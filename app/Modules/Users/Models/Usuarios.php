<?php

namespace App\Modules\Users\Models;

use App\Modules\Events\Models\Eventos;
use App\Modules\Events\Models\UsuariosEventos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * Usuario del back-office (taquilla / dashboard). Tabla legacy `usuarios`.
 *
 * @property int $pk_usuario
 * @property int $pk_usuario_tipo
 * @property string $nombre
 * @property string|null $apellido_paterno
 * @property string|null $apellido_materno
 * @property string $correo
 * @property string|null $telefono
 * @property int|null $pk_formato_boleto
 * @property bool $eliminado
 */
class Usuarios extends Authenticatable
{
    use Notifiable;

    public const CREATED_AT = 'creacion_fecha';

    public const UPDATED_AT = 'modificacion_fecha';

    /** Tipos de usuario con permisos de empresario (ven solo sus eventos). */
    public const MAX_PROMOTER_TYPE = 3;

    /** Tipo de usuario sin acceso a resúmenes de venta. */
    public const SELLER_TYPE = 4;

    protected $table = 'usuarios';

    protected $primaryKey = 'pk_usuario';

    protected $fillable = [
        'pk_usuario_tipo', 'nombre', 'apellido_paterno', 'apellido_materno',
        'correo', 'password', 'telefono', 'nacimiento_fecha', 'req_update',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'pk_usuario_tipo' => 'integer',
            'pk_formato_boleto' => 'integer',
            'eliminado' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function freshTimestamp(): Carbon
    {
        return Carbon::now(config('boxoffice.timezone', 'America/Mexico_City'));
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    public function getEmailForPasswordReset(): string
    {
        return $this->correo;
    }

    public function routeNotificationForMail(): string
    {
        return $this->correo;
    }

    public function usuarioTipo(): BelongsTo
    {
        return $this->belongsTo(UsuariosTipos::class, 'pk_usuario_tipo', 'pk_usuario_tipo');
    }

    public function privilegios(): BelongsToMany
    {
        return $this->belongsToMany(Privilegios::class, 'usuarios_privilegios', 'pk_usuario', 'pk_privilegio');
    }

    public function asignacionesEventos(): HasMany
    {
        return $this->hasMany(UsuariosEventos::class, 'pk_usuario', 'pk_usuario')->where('eliminado', 0);
    }

    public function eventos(): BelongsToMany
    {
        return $this->belongsToMany(Eventos::class, 'usuarios_eventos', 'pk_usuario', 'pk_evento')
            ->wherePivot('eliminado', 0);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('eliminado', 0);
    }

    public function hasPrivilege(int $privilegeId): bool
    {
        if ($this->relationLoaded('privilegios')) {
            return $this->privilegios->contains('pk_privilegio', $privilegeId);
        }

        return $this->privilegios()->where('privilegios.pk_privilegio', $privilegeId)->exists();
    }

    public function isPromoter(): bool
    {
        return $this->pk_usuario_tipo <= self::MAX_PROMOTER_TYPE;
    }

    public function isSeller(): bool
    {
        return $this->pk_usuario_tipo === self::SELLER_TYPE;
    }

    public function nombreCompleto(): string
    {
        return trim(implode(' ', array_filter([$this->nombre, $this->apellido_paterno, $this->apellido_materno])));
    }
}
