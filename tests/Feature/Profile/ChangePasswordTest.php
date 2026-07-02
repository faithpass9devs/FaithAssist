<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_change_own_password(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.password.update'), [
                'current_password' => 'password',
                'password' => 'NuevaClave1',
                'password_confirmation' => 'NuevaClave1',
            ]);

        $response->assertRedirect(route('profile.password.edit'));
        $this->assertTrue(Hash::check('NuevaClave1', $user->fresh()->password));
    }

    public function test_change_password_requires_valid_current_password(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.password.edit'))
            ->patch(route('profile.password.update'), [
                'current_password' => 'incorrecta',
                'password' => 'NuevaClave1',
                'password_confirmation' => 'NuevaClave1',
            ]);

        $response->assertRedirect(route('profile.password.edit'));
        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
