<?php

namespace Tests\Feature\Auth;

use App\Models\PasswordResetWhatsappCode;
use App\Models\User;
use App\Services\Auth\ForgotPasswordService;
use Database\Seeders\LadaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ForgotPasswordWhatsappTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LadaSeeder::class);
    }

    public function test_it_confirms_email_and_detects_phone(): void
    {
        $user = User::query()->create([
            'name' => 'Usuario Prueba',
            'email' => 'usuario@faithassistqr.test',
            'whatsapp_phone' => '+5215512345678',
            'password' => 'password123',
        ]);

        // Paso 1: Confirmar email
        $response = $this->post(route('password.recovery.email.confirm'), [
            'email' => $user->email,
        ]);

        $response->assertRedirect(route('password.recovery.phone.show'));
        $response->assertSessionHas('password_recovery.user_id', $user->id);
        $response->assertSessionHas('password_recovery.phone_normalized', '+5215512345678');
    }

    public function test_recovery_views_remain_available_with_valid_session_state(): void
    {
        $this->get(route('password.recovery.email.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/ForgotPasswordEmail'));

        $this->withSession(['password_recovery' => [
            'started_at' => now()->timestamp,
            'user_id' => 1,
            'masked_phone' => '*** *** 5678',
        ]])->get(route('password.recovery.phone.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/ForgotPasswordPhone'));

        $this->get(route('password.recovery.code.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/ForgotPasswordCode'));

        $this->withSession(['password_recovery.code_verified_at' => now()->timestamp])
            ->get(route('password.recovery.reset.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/ForgotPasswordReset'));
    }

    public function test_password_cannot_be_reset_without_code_verification(): void
    {
        $user = User::query()->create([
            'name' => 'Usuario Protegido',
            'email' => 'protected@faithassistqr.test',
            'password' => 'old-password',
        ]);

        $this->withSession(['password_recovery' => [
            'started_at' => now()->timestamp,
            'user_id' => $user->id,
        ]])->post(route('password.recovery.reset.update'), [
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect(route('password.recovery.code.show'));

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_expired_or_exhausted_codes_are_rejected(): void
    {
        $user = User::query()->create([
            'name' => 'Usuario Codigo Expirado',
            'email' => 'expired@faithassistqr.test',
            'password' => 'old-password',
        ]);
        $resetCode = PasswordResetWhatsappCode::query()->create([
            'user_id' => $user->id,
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->subMinute(),
        ]);
        $service = app(ForgotPasswordService::class);

        $this->assertFalse($service->verifyCode($user, '123456'));

        $resetCode->update(['expires_at' => now()->addMinutes(10), 'attempts' => 5]);
        $this->assertFalse($service->verifyCode($user, '123456'));
    }

    public function test_code_delivery_is_disabled_without_a_provider(): void
    {
        Queue::fake();
        Http::fake();

        $user = User::query()->create([
            'name' => 'Usuario Prueba',
            'email' => 'usuario@faithassistqr.test',
            'whatsapp_phone' => '+5215512345678',
            'password' => 'password123',
        ]);

        // Paso 2: Confirmar teléfono y enviar código
        $response = $this->withSession([
            'password_recovery' => [
                'started_at' => now()->timestamp,
                'email' => $user->email,
                'user_id' => $user->id,
                'phone_normalized' => '+5215512345678',
                'masked_phone' => '*** *** 5678',
            ],
        ])->post(route('password.recovery.phone.confirm'), [
            'whatsapp_country_code' => '521',
            'whatsapp_phone' => '5512345678',
        ]);

        $response->assertSessionHasErrors([
            'whatsapp_phone' => 'El envío de códigos de recuperación está temporalmente deshabilitado. Contacta al administrador.',
        ]);
        $this->assertDatabaseCount('password_reset_whatsapp_codes', 0);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_it_validates_code_and_moves_to_reset_step(): void
    {
        $user = User::query()->create([
            'name' => 'Usuario Codigo',
            'email' => 'usuario2@faithassistqr.test',
            'whatsapp_phone' => '+5215598765432',
            'password' => 'old-password',
        ]);

        PasswordResetWhatsappCode::query()->create([
            'user_id' => $user->id,
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
        ]);

        // Paso 3: Validar código
        $response = $this->withSession([
            'password_recovery' => [
                'started_at' => now()->timestamp,
                'user_id' => $user->id,
                'phone_normalized' => '+5215598765432',
                'masked_phone' => '*** *** 5432',
            ],
        ])->post(route('password.recovery.code.verify'), [
            'code' => '123456',
        ]);

        $response->assertRedirect(route('password.recovery.reset.show'));
        $response->assertSessionHas('password_recovery.code_verified_at');
    }

    public function test_it_resets_password_after_code_is_verified(): void
    {
        $user = User::query()->create([
            'name' => 'Usuario Reset',
            'email' => 'usuario3@faithassistqr.test',
            'whatsapp_phone' => '+5215587654321',
            'password' => 'old-password',
        ]);

        // Paso 4: Restablecer contraseña
        $this->withSession([
            'password_recovery' => [
                'started_at' => now()->timestamp,
                'user_id' => $user->id,
                'code_verified_at' => now()->timestamp,
            ],
        ])->post(route('password.recovery.reset.update'), [
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->assertNull(session('password_recovery'));
    }

    public function test_email_is_required(): void
    {
        $response = $this->post(route('password.recovery.email.confirm'), [
            'email' => '',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_email_must_exist_in_database(): void
    {
        $response = $this->post(route('password.recovery.email.confirm'), [
            'email' => 'nonexistent@test.com',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_user_without_phone_cannot_recover_password(): void
    {
        $user = User::query()->create([
            'name' => 'Usuario Sin Teléfono',
            'email' => 'nophone@test.com',
            'whatsapp_phone' => null,
            'password' => 'password123',
        ]);

        $response = $this->post(route('password.recovery.email.confirm'), [
            'email' => $user->email,
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_a_different_phone_cannot_bypass_disabled_delivery(): void
    {
        Queue::fake();

        $user = User::query()->create([
            'name' => 'Usuario Prueba',
            'email' => 'usuario@faithassistqr.test',
            'whatsapp_phone' => '+5215512345678',
            'password' => 'password123',
        ]);

        // Usuario ingresa un número diferente al registrado
        $response = $this->withSession([
            'password_recovery' => [
                'started_at' => now()->timestamp,
                'email' => $user->email,
                'user_id' => $user->id,
                'phone_normalized' => '+5215512345678',
                'masked_phone' => '*** *** 5678',
            ],
        ])->post(route('password.recovery.phone.confirm'), [
            'whatsapp_country_code' => '521',
            'whatsapp_phone' => '5598765432', // Número diferente
        ]);

        $response->assertSessionHasErrors('whatsapp_phone');
        $this->assertDatabaseCount('password_reset_whatsapp_codes', 0);
        Queue::assertNothingPushed();
    }
}
