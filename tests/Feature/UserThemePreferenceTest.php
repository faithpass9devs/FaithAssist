<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserThemePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_persists_selected_guest_theme_for_authenticated_session(): void
    {
        $user = User::factory()->create([
            'email' => 'theme@example.com',
            'ui_theme' => 'light',
        ]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
            'theme' => 'dark',
        ]);

        $response->assertRedirect(route('home', absolute: false));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'ui_theme' => 'dark',
        ]);
    }

    public function test_authenticated_user_can_store_dark_theme_preference(): void
    {
        $user = User::factory()->create(['ui_theme' => 'light']);

        $response = $this
            ->actingAs($user)
            ->patchJson(route('profile.theme.update'), [
                'theme' => 'dark',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('theme', 'dark');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'ui_theme' => 'dark',
        ]);
    }

    public function test_theme_preference_only_accepts_supported_values(): void
    {
        $user = User::factory()->create(['ui_theme' => 'light']);

        $response = $this
            ->actingAs($user)
            ->patchJson(route('profile.theme.update'), [
                'theme' => 'system',
            ]);

        $response->assertUnprocessable();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'ui_theme' => 'light',
        ]);
    }
}
