<?php

namespace Tests\Feature\Regions;

use App\Models\Regions\State;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\ControllerTestHelpers;
use Tests\TestCase;

class StateControllerTest extends TestCase
{
    use ControllerTestHelpers, RefreshDatabase;

    // ── Authorization ─────────────────────────────────────────────────────────

    public function test_unauthenticated_user_is_redirected_from_index(): void
    {
        $this->get('/estados')->assertRedirect('/login');
    }

    public function test_user_without_read_permission_gets_403_on_index(): void
    {
        $user = $this->makeGlobalUser();
        $this->actingAs($user)->get('/estados')->assertForbidden();
    }

    public function test_user_without_create_permission_gets_403_on_store(): void
    {
        $user = $this->makeGlobalUser('estados.read');
        $this->actingAs($user)
            ->postJson('/estados', ['name' => 'Nuevo', 'status' => 'active'])
            ->assertForbidden();
    }

    public function test_user_without_update_permission_gets_403_on_update(): void
    {
        $state = State::query()->create(['name' => 'Estado X', 'short_name' => 'EX', 'status' => 'active']);
        $user = $this->makeGlobalUser('estados.read');

        $this->actingAs($user)
            ->putJson("/estados/{$state->id}", ['name' => 'Cambio', 'status' => 'active'])
            ->assertForbidden();
    }

    public function test_user_without_delete_permission_gets_403_on_destroy(): void
    {
        $state = State::query()->create(['name' => 'Estado Y', 'short_name' => 'EY', 'status' => 'active']);
        $user = $this->makeGlobalUser('estados.read');

        $this->actingAs($user)
            ->deleteJson("/estados/{$state->id}")
            ->assertForbidden();
    }

    public function test_user_without_export_permission_gets_403_on_export(): void
    {
        $user = $this->makeGlobalUser('estados.read');

        $this->actingAs($user)
            ->get('/estados/export')
            ->assertForbidden();
    }

    // ── CRUD ──────────────────────────────────────────────────────────────────

    public function test_index_returns_inertia_response(): void
    {
        $user = $this->makeGlobalUser('estados.read');

        $this->actingAs($user)
            ->get('/estados')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Regions/States/Index'));
    }

    public function test_store_creates_state_and_returns_201(): void
    {
        $user = $this->makeGlobalUser('estados.create');

        $response = $this->actingAs($user)
            ->postJson('/estados', [
                'name'       => 'JALISCO',
                'short_name' => 'JAL',
                'status' => 'active',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'JALISCO');

        $this->assertDatabaseHas('states', ['name' => 'JALISCO']);
    }

    public function test_update_modifies_state_and_returns_200(): void
    {
        $state = State::query()->create(['name' => 'ORIGINAL', 'short_name' => 'OR', 'status' => 'active']);
        $user = $this->makeGlobalUser('estados.update');

        $response = $this->actingAs($user)
            ->putJson("/estados/{$state->id}", [
                'name'       => 'ACTUALIZADO',
                'short_name' => 'AC',
                'status' => 'inactive',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'ACTUALIZADO');

        $this->assertDatabaseHas('states', ['id' => $state->id, 'name' => 'ACTUALIZADO']);
    }

    public function test_destroy_soft_deletes_state_and_returns_200(): void
    {
        $state = State::query()->create(['name' => 'A ELIMINAR', 'short_name' => 'AE', 'status' => 'active']);
        $user = $this->makeGlobalUser('estados.delete');

        $this->actingAs($user)
            ->deleteJson("/estados/{$state->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('states', ['id' => $state->id]);
    }

    public function test_export_returns_xlsx_for_authorized_user(): void
    {
        State::query()->create(['name' => 'JALISCO', 'short_name' => 'JAL', 'status' => 'active']);
        $user = $this->makeGlobalUser('estados.read', 'estados.export');

        $response = $this->actingAs($user)->get('/estados/export');

        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type') ?? ''
        );
    }
}
