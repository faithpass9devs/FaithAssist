<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForcePasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_forced_password_change_is_redirected_after_login(): void
    {
        $user = User::factory()->create([
            'must_change_password' => true,
        ]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('profile.password.edit'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_with_forced_password_change_cannot_access_modules(): void
    {
        $user = User::factory()->create([
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->get('/modulos')
            ->assertRedirect(route('profile.password.edit'));
    }

    public function test_user_with_forced_password_change_can_enter_after_updating_password(): void
    {
        $user = User::factory()->create([
            'must_change_password' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.password.update'), [
                'current_password' => 'password',
                'password' => 'NuevaClave1',
                'password_confirmation' => 'NuevaClave1',
            ]);

        $response->assertRedirect(route('home'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'must_change_password' => false,
        ]);

        $this->actingAs($user->fresh())
            ->get('/')
            ->assertOk();
    }
}
