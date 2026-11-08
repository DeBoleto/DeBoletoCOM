<?php

namespace App\Modules\Dashboard\Enums;

/**
 * Tipos de usuario del back-office (usuarios.pk_usuario_tipo) con la
 * sección del dashboard que los administra.
 */
enum StaffType: int
{
    case Administrator = 1;
    case Monitor = 2;
    case Promoter = 3;
    case PointOfSale = 4;
    case User = 5;

    public static function fromSection(string $section): self
    {
        foreach (self::cases() as $type) {
            if ($type->section() === $section) {
                return $type;
            }
        }

        abort(404);
    }

    public function section(): string
    {
        return match ($this) {
            self::Administrator => 'administradores',
            self::Monitor => 'monitores',
            self::Promoter => 'empresarios',
            self::PointOfSale => 'puntoventa',
            self::User => 'usuarios',
        };
    }

    public function plural(): string
    {
        return match ($this) {
            self::Administrator => 'Administradores',
            self::Monitor => 'Monitores',
            self::Promoter => 'Empresarios',
            self::PointOfSale => 'Puntos de venta',
            self::User => 'Usuarios',
        };
    }

    public function singular(): string
    {
        return match ($this) {
            self::Administrator => 'administrador',
            self::Monitor => 'monitor',
            self::Promoter => 'empresario',
            self::PointOfSale => 'punto de venta',
            self::User => 'usuario',
        };
    }

    /**
     * @return list<string>
     */
    public static function sections(): array
    {
        return array_map(fn (self $type) => $type->section(), self::cases());
    }
}
