<?php

namespace Tests\Feature\Regions;

use App\Models\Regions\Community;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\ControllerTestHelpers;
use Tests\TestCase;

class CommunityControllerTest extends TestCase
{
    use ControllerTestHelpers, RefreshDatabase;

    // ── Authorization ─────────────────────────────────────────────────────────

    public function test_unauthenticated_user_is_redirected_from_index(): void
    {
        $this->get('/comunidades')->assertRedirect('/login');
    }

    public function test_user_without_read_permission_gets_403_on_index(): void
    {
        $user = $this->makeGlobalUser();
        $this->actingAs($user)->get('/comunidades')->assertForbidden();
    }

    public function test_user_without_create_permission_gets_403_on_store(): void
    {
        $chain = $this->createChain();
        $user = $this->makeGlobalUser('comunidades.read');

        $this->actingAs($user)
            ->postJson('/comunidades', ['municipality_id' => $chain['municipality']->id, 'name' => 'X', 'status' => 'active'])
            ->assertForbidden();
    }

    public function test_user_without_export_permission_gets_403_on_export(): void
    {
        $user = $this->makeGlobalUser('comunidades.read');

        $this->actingAs($user)
            ->get('/comunidades/export')
            ->assertForbidden();
    }

    // ── Scope ─────────────────────────────────────────────────────────────────

    public function test_global_user_sees_all_communities(): void
    {
        $chain1 = $this->createChain();
        $chain2 = $this->createChain();
        $chain2['municipality']->update(['name' => 'Municipio B']);
        $chain2['community']->update(['name' => 'Comunidad B']);

        $user = $this->makeGlobalUser('comunidades.read');

        $this->actingAs($user)
            ->get('/comunidades')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('communities.total', 2));
    }

    public function test_diocese_scoped_user_sees_only_communities_in_own_municipalities(): void
    {
        $chain1 = $this->createChain();
        $chain2 = $this->createChain();
        $chain2['municipality']->update(['name' => 'Municipio Foraneo']);
        $chain2['community']->update(['name' => 'Comunidad Foranea']);

        $user = $this->makeDioceseUser($chain1['diocese'], 'comunidades.read');

        $this->actingAs($user)
            ->get('/comunidades')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('communities.total', 1));
    }

    // ── CRUD ──────────────────────────────────────────────────────────────────

    public function test_index_returns_inertia_response(): void
    {
        $user = $this->makeGlobalUser('comunidades.read');

        $this->actingAs($user)->get('/comunidades')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Regions/Communities/Index'));
    }

    public function test_store_creates_community_and_returns_201(): void
    {
        $chain = $this->createChain();
        $user = $this->makeGlobalUser('comunidades.create', 'comunidades.scope.all');

        $response = $this->actingAs($user)
            ->postJson('/comunidades', [
                'municipality_id' => $chain['municipality']->id,
                'name'            => 'NUEVA COMUNIDAD',
                'status' => 'active',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'NUEVA COMUNIDAD');

        $this->assertDatabaseHas('communities', ['name' => 'NUEVA COMUNIDAD']);
    }

    public function test_update_modifies_community_and_returns_200(): void
    {
        $chain = $this->createChain();
        $user = $this->makeGlobalUser('comunidades.update');

        $response = $this->actingAs($user)
            ->putJson("/comunidades/{$chain['community']->id}", [
                'municipality_id' => $chain['municipality']->id,
                'name'            => 'COMUNIDAD ACTUALIZADA',
                'status' => 'active',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'COMUNIDAD ACTUALIZADA');
    }

    public function test_destroy_soft_deletes_community_and_returns_200(): void
    {
        $chain = $this->createChain();
        $user = $this->makeGlobalUser('comunidades.delete');

        $this->actingAs($user)
            ->deleteJson("/comunidades/{$chain['community']->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('communities', ['id' => $chain['community']->id]);
    }

    public function test_export_returns_xlsx_for_authorized_user(): void
    {
        $chain = $this->createChain();
        $user = $this->makeGlobalUser('comunidades.read', 'comunidades.export');

        $response = $this->actingAs($user)->get('/comunidades/export');

        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type') ?? ''
        );
    }
}
