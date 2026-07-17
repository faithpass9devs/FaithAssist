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

    public function test_login_persists_selected_guest_palette_for_authenticated_session(): void
    {
        $user = User::factory()->create([
            'email' => 'palette@example.com',
            'ui_palette' => 'indigo',
        ]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
            'palette' => 'emerald',
        ]);

        $response->assertRedirect(route('home', absolute: false));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'ui_palette' => 'emerald',
        ]);
    }

    public function test_login_persists_selected_guest_custom_color_for_authenticated_session(): void
    {
        $user = User::factory()->create([
            'email' => 'custom@example.com',
            'ui_custom_color' => '#aabbcc',
        ]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
            'custom_color' => '#ff5500',
        ]);

        $response->assertRedirect(route('home', absolute: false));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'ui_custom_color' => '#ff5500',
        ]);
    }

    public function test_login_rejects_invalid_custom_color_format(): void
    {
        $user = User::factory()->create([
            'email' => 'invalid@example.com',
            'ui_custom_color' => '#aabbcc',
        ]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
            'custom_color' => 'not-a-color',
        ]);

        $response->assertSessionHasErrors('custom_color');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'ui_custom_color' => '#aabbcc',
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

    public function test_authenticated_user_can_persist_palette(): void
    {
        $user = User::factory()->create([
            'ui_theme' => 'light',
            'ui_palette' => 'steel',
        ]);

        $response = $this
            ->actingAs($user)
            ->patchJson(route('profile.theme.update'), [
                'theme' => 'light',
                'palette' => 'ocean',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('palette', 'ocean');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'ui_palette' => 'ocean',
        ]);
    }

    public function test_authenticated_user_can_persist_custom_color(): void
    {
        $user = User::factory()->create([
            'ui_theme' => 'dark',
            'ui_custom_color' => '#3b82f6',
        ]);

        $response = $this
            ->actingAs($user)
            ->patchJson(route('profile.theme.update'), [
                'theme' => 'dark',
                'custom_color' => '#a855f7',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('custom_color', '#a855f7');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'ui_custom_color' => '#a855f7',
        ]);
    }

    public function test_custom_color_must_be_a_valid_hex_color(): void
    {
        $user = User::factory()->create([
            'ui_theme' => 'light',
            'ui_custom_color' => '#3b82f6',
        ]);

        $response = $this
            ->actingAs($user)
            ->patchJson(route('profile.theme.update'), [
                'theme' => 'light',
                'custom_color' => 'not-a-color',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('custom_color');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'ui_custom_color' => '#3b82f6',
        ]);
    }

    public function test_custom_color_rejects_shorthand_hex(): void
    {
        $user = User::factory()->create([
            'ui_theme' => 'light',
            'ui_custom_color' => '#3b82f6',
        ]);

        $response = $this
            ->actingAs($user)
            ->patchJson(route('profile.theme.update'), [
                'theme' => 'light',
                'custom_color' => '#fff',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('custom_color');
    }

    public function test_palette_and_custom_color_are_persisted_together(): void
    {
        $user = User::factory()->create([
            'ui_theme' => 'light',
            'ui_palette' => 'steel',
            'ui_custom_color' => '#3b82f6',
        ]);

        $response = $this
            ->actingAs($user)
            ->patchJson(route('profile.theme.update'), [
                'theme' => 'dark',
                'palette' => 'custom',
                'custom_color' => '#e11d48',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('theme', 'dark')
            ->assertJsonPath('palette', 'custom')
            ->assertJsonPath('custom_color', '#e11d48');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'ui_theme' => 'dark',
            'ui_palette' => 'custom',
            'ui_custom_color' => '#e11d48',
        ]);
    }
}
