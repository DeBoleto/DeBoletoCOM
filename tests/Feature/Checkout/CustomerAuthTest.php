<?php

namespace Tests\Feature\Checkout;

use App\Modules\Users\Models\Usuarios;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Tests\Support\LegacyCheckoutSchema;
use Tests\TestCase;

class CustomerAuthTest extends TestCase
{
    use LegacyCheckoutSchema, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createLegacyCheckoutTables();
        config(['services.stytch.project_id' => 'project-test', 'services.stytch.secret' => 'secret-test']);
    }

    public function test_code_is_requested_and_unknown_phone_must_complete_registration(): void
    {
        Http::fake([
            '*/otps/whatsapp/login_or_create' => Http::response(['phone_id' => 'phone-123']),
            '*/otps/authenticate' => Http::response(['status_code' => 200]),
        ]);

        $this->from('/')->post('/login/codigo', ['lada' => '52', 'telefono' => '9930001111', 'canal' => 'whatsapp'])
            ->assertRedirect('/')
            ->assertSessionHas('customer.otp.method_id', 'phone-123');

        Http::assertSent(fn ($request) => $request['phone_number'] === '+529930001111');

        $this->from('/')->post('/login/verificar', ['codigo' => '123456'])
            ->assertRedirect('/')
            ->assertSessionHas('registration_required', true);

        $this->assertNull(Auth::guard('customer')->user());

        $this->from('/')->post('/login/completar-registro', [
            'nombre' => 'Luis', 'apellido_paterno' => 'Pérez', 'correo' => 'luis@gmail.com',
        ])->assertRedirect('/');

        $customer = Usuarios::query()->where('telefono', '9930001111')->firstOrFail();
        $this->assertSame(5, $customer->pk_usuario_tipo);
        $this->assertSame($customer->pk_usuario, Auth::guard('customer')->id());
    }

    public function test_verified_phone_cannot_take_over_an_account_by_email(): void
    {
        $victim = $this->createCustomer(['correo' => 'victima@gmail.com', 'telefono' => '9939999999']);

        Http::fake([
            '*/otps/whatsapp/login_or_create' => Http::response(['phone_id' => 'phone-123']),
            '*/otps/authenticate' => Http::response(['status_code' => 200]),
        ]);

        $this->post('/login/codigo', ['lada' => '52', 'telefono' => '9930002222', 'canal' => 'whatsapp']);
        $this->post('/login/verificar', ['codigo' => '123456']);

        $this->post('/login/completar-registro', [
            'nombre' => 'Atacante', 'apellido_paterno' => 'X', 'correo' => 'victima@gmail.com',
        ])->assertSessionHasErrors('correo');

        $this->assertNull(Auth::guard('customer')->user());
        $this->assertSame('9939999999', $victim->fresh()->telefono);
    }

    public function test_existing_phone_signs_in_and_wrong_codes_are_limited(): void
    {
        $customer = $this->createCustomer();

        Http::fake([
            '*/otps/sms/login_or_create' => Http::response(['phone_id' => 'phone-9']),
            '*/otps/authenticate' => Http::sequence()->pushStatus(401)->push(['status_code' => 200]),
        ]);

        $this->post('/login/codigo', ['lada' => '52', 'telefono' => $customer->telefono, 'canal' => 'sms']);

        $this->post('/login/verificar', ['codigo' => '000000'])->assertSessionHasErrors('codigo');
        $this->assertNull(Auth::guard('customer')->user());

        $this->post('/login/verificar', ['codigo' => '123456'])->assertSessionHasNoErrors();
        $this->assertSame($customer->pk_usuario, Auth::guard('customer')->id());
    }

    public function test_password_login_and_registration_rules(): void
    {
        $customer = $this->createCustomer(['password' => 'secreto-seguro']);

        $this->post('/cliente/acceso', ['email' => $customer->correo, 'password' => 'otra'])->assertSessionHasErrors('email');
        $this->post('/cliente/acceso', ['email' => $customer->correo, 'password' => 'secreto-seguro'])->assertSessionHasNoErrors();
        $this->assertSame($customer->pk_usuario, Auth::guard('customer')->id());

        Auth::guard('customer')->logout();

        $this->post('/cliente/registro', [
            'nombre' => 'Eva', 'apellido_paterno' => 'Ruiz', 'phone' => '9935556666',
            'email' => 'eva@empresa-desconocida.xyz', 'password' => 'clave-larga-1', 'password_confirmation' => 'clave-larga-1',
        ])->assertSessionHasErrors('email');

        $this->post('/cliente/registro', [
            'nombre' => 'Eva', 'apellido_paterno' => 'Ruiz', 'phone' => $customer->telefono,
            'email' => 'eva@gmail.com', 'password' => 'clave-larga-1', 'password_confirmation' => 'clave-larga-1',
        ])->assertSessionHasErrors('phone');
    }
}
