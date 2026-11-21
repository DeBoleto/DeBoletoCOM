<?php

namespace App\Modules\BoxOffice\Services\Customers;

use App\Modules\Orders\Models\EventosCompras;
use App\Modules\Users\Models\Usuarios;
use Illuminate\Support\Facades\DB;

/**
 * Asigna una venta de taquilla al cliente: lo busca por teléfono, luego por
 * correo y, si no existe, lo registra. Los boletos conservan al vendedor en
 * adquisicion_pk_usuario para los cortes de caja.
 */
class PurchaseCustomer
{
    /**
     * @param  array{nombre: string, apellido: string, telefono: string, correo: string}  $data
     * @return array{customer: Usuarios, created: bool}
     */
    public function assign(EventosCompras $purchase, array $data): array
    {
        return DB::transaction(function () use ($purchase, $data) {
            $customer = $this->find($data['telefono'], $data['correo']);
            $created = $customer === null;

            if ($created) {
                $customer = new Usuarios;
                $customer->forceFill([
                    'nombre' => $data['nombre'],
                    'apellido_paterno' => $data['apellido'],
                    'telefono' => $data['telefono'],
                    'correo' => $data['correo'],
                    'pk_usuario_tipo' => (int) config('checkout.customer_type'),
                    'tipe_login' => 2,
                ])->save();
            } else {
                $this->completeContact($customer, $data);
            }

            $purchase->forceFill(['pk_usuario' => $customer->pk_usuario])->save();

            return ['customer' => $customer, 'created' => $created];
        });
    }

    private function find(string $phone, string $email): ?Usuarios
    {
        return Usuarios::query()->activos()->conTelefono($phone)->orderBy('pk_usuario')->first()
            ?? Usuarios::query()->activos()->where('correo', $email)->orderBy('pk_usuario')->first();
    }

    /**
     * Completa el teléfono o el correo que le falten al cliente encontrado,
     * siempre que no pertenezcan a otra cuenta.
     */
    private function completeContact(Usuarios $customer, array $data): void
    {
        $missing = [];

        if (blank($customer->telefono) && ! Usuarios::query()->activos()->conTelefono($data['telefono'])->exists()) {
            $missing['telefono'] = $data['telefono'];
        }

        if (blank($customer->correo) && ! Usuarios::query()->activos()->where('correo', $data['correo'])->exists()) {
            $missing['correo'] = $data['correo'];
        }

        if ($missing !== []) {
            $customer->forceFill($missing)->save();
        }
    }
}
