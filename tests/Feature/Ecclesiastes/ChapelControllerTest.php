<?php

namespace Tests\Feature\Ecclesiastes;

use App\Models\Ecclesiastes\Chapel;
use App\Models\Regions\Community;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\ControllerTestHelpers;
use Tests\TestCase;

class ChapelControllerTest extends TestCase
{
    use ControllerTestHelpers, RefreshDatabase;

    // ── Authorization ─────────────────────────────────────────────────────────

    public function test_unauthenticated_user_is_redirected_from_index(): void
    {
        $this->get('/capillas')->assertRedirect('/login');
    }

    public function test_user_without_read_permission_gets_403_on_index(): void
    {
        $user = $this->makeGlobalUser();
        $this->actingAs($user)->get('/capillas')->assertForbidden();
    }

    public function test_user_without_create_permission_gets_403_on_store(): void
    {
        $chain = $this->createChain();
        $user = $this->makeGlobalUser('capillas.read');

        $this->actingAs($user)
            ->postJson('/capillas', [
                'community_id' => $chain['community']->id,
                'name' => 'X',
                'status' => 'active',
            ])
            ->assertForbidden();
    }

    // ── Scope ─────────────────────────────────────────────────────────────────

    public function test_global_user_sees_all_chapels(): void
    {
        $chain1 = $this->createChain();
        $chain2 = $this->createChain();
        $chain2['community']->update(['name' => 'Comunidad B']);
        $chain2['church']->update(['name' => 'Parroquia B']);

        Chapel::query()->create(['name' => 'Capilla A', 'community_id' => $chain1['community']->id, 'church_id' => $chain1['church']->id, 'status' => 'active']);
        Chapel::query()->create(['name' => 'Capilla B', 'community_id' => $chain2['community']->id, 'church_id' => $chain2['church']->id, 'status' => 'active']);

        $user = $this->makeGlobalUser('capillas.read');

        $this->actingAs($user)->get('/capillas')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('chapels.total', 2));
    }

    public function test_church_scoped_user_sees_only_chapels_of_own_church(): void
    {
        $chain1 = $this->createChain();
        $chain2 = $this->createChain();
        $chain2['community']->update(['name' => 'Comunidad B']);
        $chain2['church']->update(['name' => 'Parroquia B']);

        $ownChapel = Chapel::query()->create(['name' => 'Capilla Propia', 'community_id' => $chain1['community']->id, 'church_id' => $chain1['church']->id, 'status' => 'active']);
        Chapel::query()->create(['name' => 'Capilla Ajena', 'community_id' => $chain2['community']->id, 'church_id' => $chain2['church']->id, 'status' => 'active']);

        $user = $this->makeChurchUser($chain1['diocese'], $chain1['deanery'], $chain1['church'], 'capillas.read');

        $this->actingAs($user)->get('/capillas')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('chapels.total', 1));
    }

    public function test_church_scoped_user_only_receives_communities_associated_with_own_church(): void
    {
        $chain = $this->createChain();
        $otherChurch = $this->createChain();
        $otherChurch['community']->update([
            'municipality_id' => $chain['municipality']->id,
            'name' => 'Comunidad de otra parroquia',
        ]);
        Community::query()->create([
            'municipality_id' => $chain['municipality']->id,
            'name' => 'Comunidad sin capilla',
            'status' => 'active',
        ]);

        Chapel::query()->create([
            'name' => 'Capilla propia',
            'community_id' => $chain['community']->id,
            'church_id' => $chain['church']->id,
            'status' => 'active',
        ]);
        Chapel::query()->create([
            'name' => 'Capilla de otra parroquia',
            'community_id' => $otherChurch['community']->id,
            'church_id' => $otherChurch['church']->id,
            'status' => 'active',
        ]);

        $user = $this->makeChurchUser($chain['diocese'], $chain['deanery'], $chain['church'], 'capillas.read');

        $this->actingAs($user)->get('/capillas')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('communities', [['id' => $chain['community']->id, 'name' => $chain['community']->name]])
            );
    }

    public function test_church_scoped_user_cannot_assign_an_unassociated_community(): void
    {
        $chain = $this->createChain();
        $unassociatedCommunity = Community::query()->create([
            'municipality_id' => $chain['municipality']->id,
            'name' => 'Comunidad no asociada',
            'status' => 'active',
        ]);
        $user = $this->makeChurchUser(
            $chain['diocese'],
            $chain['deanery'],
            $chain['church'],
            'capillas.create'
        );

        $this->actingAs($user)
            ->postJson('/capillas', [
                'community_id' => $unassociatedCommunity->id,
                'church_id' => $chain['church']->id,
                'name' => 'Capilla no permitida',
                'status' => 'active',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('community_id');
    }

    public function test_scoped_user_cannot_update_chapel_outside_scope(): void
    {
        $chain1 = $this->createChain();
        $chain2 = $this->createChain();
        $chain2['community']->update(['name' => 'Comunidad B']);
        $chain2['church']->update(['name' => 'Parroquia B']);

        $foreignChapel = Chapel::query()->create(['name' => 'Capilla Foranea', 'community_id' => $chain2['community']->id, 'church_id' => $chain2['church']->id, 'status' => 'active']);
        $user = $this->makeChurchUser($chain1['diocese'], $chain1['deanery'], $chain1['church'], 'capillas.update');

        $this->actingAs($user)
            ->putJson("/capillas/{$foreignChapel->id}", [
                'community_id' => $chain2['community']->id,
                'name' => 'MODIFICADA',
                'status' => 'active',
            ])
            ->assertForbidden();
    }

    // ── CRUD ──────────────────────────────────────────────────────────────────

    public function test_index_returns_inertia_response(): void
    {
        $user = $this->makeGlobalUser('capillas.read');

        $this->actingAs($user)->get('/capillas')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Ecclesiastes/Chapels/Index'));
    }

    public function test_store_creates_chapel_and_returns_201(): void
    {
        $chain = $this->createChain();
        $user = $this->makeGlobalUser('capillas.create', 'capillas.scope.all');

        $response = $this->actingAs($user)
            ->postJson('/capillas', [
                'community_id' => $chain['community']->id,
                'church_id' => $chain['church']->id,
                'name' => 'CAPILLA NUEVA',
                'status' => 'active',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'CAPILLA NUEVA');

        $this->assertDatabaseHas('chapels', ['name' => 'CAPILLA NUEVA']);
    }

    public function test_update_modifies_chapel_and_returns_200(): void
    {
        $chain = $this->createChain();
        $chapel = Chapel::query()->create(['name' => 'Original', 'community_id' => $chain['community']->id, 'church_id' => $chain['church']->id, 'status' => 'active']);
        $user = $this->makeGlobalUser('capillas.update');

        $response = $this->actingAs($user)
            ->putJson("/capillas/{$chapel->id}", [
                'community_id' => $chain['community']->id,
                'name' => 'CAPILLA ACTUALIZADA',
                'status' => 'active',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'CAPILLA ACTUALIZADA');
    }

    public function test_destroy_soft_deletes_chapel_and_returns_200(): void
    {
        $chain = $this->createChain();
        $chapel = Chapel::query()->create(['name' => 'A Eliminar', 'community_id' => $chain['community']->id, 'status' => 'active']);
        $user = $this->makeGlobalUser('capillas.delete');

        $this->actingAs($user)
            ->deleteJson("/capillas/{$chapel->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('chapels', ['id' => $chapel->id]);
    }
}
