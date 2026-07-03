<?php

namespace Tests\Feature\Regions;

use App\Models\Regions\Municipality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\ControllerTestHelpers;
use Tests\TestCase;

class MunicipalityControllerTest extends TestCase
{
    use ControllerTestHelpers, RefreshDatabase;

    // ── Authorization ─────────────────────────────────────────────────────────

    public function test_unauthenticated_user_is_redirected_from_index(): void
    {
        $this->get('/municipios')->assertRedirect('/login');
    }

    public function test_user_without_read_permission_gets_403_on_index(): void
    {
        $user = $this->makeGlobalUser();
        $this->actingAs($user)->get('/municipios')->assertForbidden();
    }

    public function test_user_without_create_permission_gets_403_on_store(): void
    {
        $chain = $this->createChain();
        $user = $this->makeGlobalUser('municipios.read');

        $this->actingAs($user)
            ->postJson('/municipios', ['state_id' => $chain['state']->id, 'name' => 'X', 'status' => 'active'])
            ->assertForbidden();
    }

    public function test_user_without_export_permission_gets_403_on_export(): void
    {
        $user = $this->makeGlobalUser('municipios.read');

        $this->actingAs($user)
            ->get('/municipios/export')
            ->assertForbidden();
    }

    // ── Scope ─────────────────────────────────────────────────────────────────

    public function test_global_user_sees_all_municipalities(): void
    {
        $chain1 = $this->createChain();
        $chain2 = $this->createChain();
        // Rename second chain to avoid unique constraint
        $chain2['municipality']->update(['name' => 'Municipio Otro']);

        $user = $this->makeGlobalUser('municipios.read');

        $this->actingAs($user)
            ->get('/municipios')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Regions/Municipalities/Index')
                ->where('municipalities.total', 2)
            );
    }

    public function test_diocese_scoped_user_sees_only_own_municipalities(): void
    {
        $chain1 = $this->createChain();
        // Second chain with a different diocese/state
        $chain2 = $this->createChain();
        $chain2['municipality']->update(['name' => 'Municipio Foraneo', 'diocese_id' => $chain2['diocese']->id]);

        $user = $this->makeDioceseUser($chain1['diocese'], 'municipios.read');

        $this->actingAs($user)
            ->get('/municipios')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('municipalities.total', 1)
                ->where('municipalities.data.0.name', 'Municipio Test')
            );
    }

    // ── CRUD ──────────────────────────────────────────────────────────────────

    public function test_index_returns_inertia_response(): void
    {
        $user = $this->makeGlobalUser('municipios.read');

        $this->actingAs($user)->get('/municipios')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Regions/Municipalities/Index'));
    }

    public function test_store_creates_municipality_and_returns_201(): void
    {
        $chain = $this->createChain();
        $user = $this->makeGlobalUser('municipios.create', 'municipios.scope.all');

        $response = $this->actingAs($user)
            ->postJson('/municipios', [
                'state_id'   => $chain['state']->id,
                'diocese_id' => $chain['diocese']->id,
                'name'       => 'NUEVO MUNICIPIO',
                'status' => 'active',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'NUEVO MUNICIPIO');

        $this->assertDatabaseHas('municipalities', ['name' => 'NUEVO MUNICIPIO']);
    }

    public function test_update_modifies_municipality_and_returns_200(): void
    {
        $chain = $this->createChain();
        $user = $this->makeGlobalUser('municipios.update');

        $response = $this->actingAs($user)
            ->putJson("/municipios/{$chain['municipality']->id}", [
                'state_id' => $chain['state']->id,
                'name'     => 'MUNICIPIO ACTUALIZADO',
                'status' => 'active',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'MUNICIPIO ACTUALIZADO');
    }

    public function test_destroy_soft_deletes_municipality_and_returns_200(): void
    {
        $chain = $this->createChain();
        $user = $this->makeGlobalUser('municipios.delete');

        $this->actingAs($user)
            ->deleteJson("/municipios/{$chain['municipality']->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('municipalities', ['id' => $chain['municipality']->id]);
    }

    public function test_export_returns_xlsx_for_authorized_user(): void
    {
        $this->createChain();
        $user = $this->makeGlobalUser('municipios.read', 'municipios.export');

        $response = $this->actingAs($user)->get('/municipios/export');

        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type') ?? ''
        );
    }
}
