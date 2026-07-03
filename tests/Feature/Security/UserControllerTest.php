<?php

namespace Tests\Feature\Security;

use App\Models\Profile;
use App\Models\User;
use Database\Seeders\LadaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\ControllerTestHelpers;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use ControllerTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LadaSeeder::class);
    }

    /**
     * Minimal valid payload for creating a user.
     */
    private function validUserPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Juan',
            'paterno' => 'Pérez',
            'materno' => null,
            'email' => 'juan.perez@example.com',
            'whatsapp_country_code' => '52',
            'whatsapp_phone' => '3310000001',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'role_id' => null,
            'diocese_id' => null,
            'deanery_id' => null,
            'church_id' => null,
            'permissions' => [],
        ], $overrides);
    }

    // ── Authorization ─────────────────────────────────────────────────────────

    /**
     * UserController has no explicit authorization — any authenticated user can access it.
     */
    public function test_unauthenticated_user_is_redirected_from_index(): void
    {
        $this->get('/usuarios')->assertRedirect('/login');
    }

    public function test_unauthenticated_user_is_redirected_from_create(): void
    {
        $this->get('/usuarios/create')->assertRedirect('/login');
    }

    // ── Scope ─────────────────────────────────────────────────────────────────

    public function test_global_user_sees_all_users(): void
    {
        $chain = $this->createChain();
        User::factory()->create(['diocese_id' => $chain['diocese']->id]);
        User::factory()->create(['diocese_id' => null]);

        $editor = $this->makeGlobalUser();

        // 3 total: 2 created above + 1 editor
        $this->actingAs($editor)->get('/usuarios')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('users.total', 3));
    }

    public function test_diocese_scoped_user_sees_only_users_of_own_diocese(): void
    {
        $chain1 = $this->createChain();
        $chain2 = $this->createChain();
        $chain2['diocese']->update(['name' => 'Diócesis B']);

        User::factory()->create(['diocese_id' => $chain1['diocese']->id]);
        User::factory()->create(['diocese_id' => $chain2['diocese']->id]);

        $editor = $this->makeDioceseUser($chain1['diocese']);

        // Editor sees only users of chain1's diocese (1 created + editor itself = 2)
        $this->actingAs($editor)->get('/usuarios')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('users.total', 2));
    }

    // ── CRUD ──────────────────────────────────────────────────────────────────

    public function test_index_returns_inertia_response(): void
    {
        $user = $this->makeGlobalUser();

        $this->actingAs($user)->get('/usuarios')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Security/Users/Index'));
    }

    public function test_create_returns_inertia_form(): void
    {
        $user = $this->makeGlobalUser();

        $this->actingAs($user)->get('/usuarios/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Security/Users/Form'));
    }

    public function test_store_creates_user_and_profile_then_redirects(): void
    {
        $editor = $this->makeGlobalUser();

        $this->actingAs($editor)
            ->post('/usuarios', $this->validUserPayload())
            ->assertRedirect('/usuarios');

        $this->assertDatabaseHas('users', ['email' => 'juan.perez@example.com']);
        $user = User::where('email', 'juan.perez@example.com')->firstOrFail();
        $this->assertDatabaseHas('profiles', ['user_id' => $user->id, 'name' => 'Juan']);
    }

    public function test_edit_returns_inertia_form_with_user_data(): void
    {
        $chain = $this->createChain();
        $target = User::factory()->create(['diocese_id' => null]);
        Profile::create(['user_id' => $target->id, 'name' => 'Ana', 'paterno' => 'López', 'materno' => null]);

        $editor = $this->makeGlobalUser();

        $this->actingAs($editor)
            ->get("/usuarios/{$target->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Security/Users/Form'));
    }

    public function test_update_modifies_user_and_redirects(): void
    {
        $target = User::factory()->create(['diocese_id' => null]);
        Profile::create(['user_id' => $target->id, 'name' => 'Carlos', 'paterno' => 'Gómez', 'materno' => null]);
        $editor = $this->makeGlobalUser();

        $this->actingAs($editor)
            ->put("/usuarios/{$target->id}", $this->validUserPayload([
                'email' => $target->email,
                'name' => 'Carlos',
                'paterno' => 'González',
                'whatsapp_phone' => '3320000002',
            ]))
            ->assertRedirect('/usuarios');

        $this->assertDatabaseHas('profiles', ['user_id' => $target->id, 'paterno' => 'González']);
    }

    public function test_destroy_requires_usuarios_delete_permission(): void
    {
        $target = User::factory()->create(['diocese_id' => null]);
        $editor = $this->makeGlobalUser();

        $this->actingAs($editor)
            ->deleteJson("/usuarios/{$target->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id, 'deleted_at' => null]);
    }

    public function test_destroy_blocks_self_deletion(): void
    {
        $editor = $this->makeGlobalUser('usuarios.delete');

        $this->actingAs($editor)
            ->deleteJson("/usuarios/{$editor->id}")
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No puedes eliminar tu propio usuario.');

        $this->assertDatabaseHas('users', ['id' => $editor->id, 'deleted_at' => null]);
    }

    public function test_destroy_soft_deletes_user_and_hides_it_from_index(): void
    {
        $target = User::factory()->create(['diocese_id' => null]);
        $editor = $this->makeGlobalUser('usuarios.delete');

        $this->actingAs($editor)
            ->deleteJson("/usuarios/{$target->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Usuario eliminado correctamente.');

        $this->assertSoftDeleted('users', ['id' => $target->id]);

        $this->actingAs($editor)->get('/usuarios')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('users.total', 1));
    }
}
