<?php

namespace Tests\Feature\Security;

use App\Models\Profile;
use App\Models\User;
use Database\Seeders\LadaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
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

    public function test_create_form_shows_export_permissions_when_editor_has_them(): void
    {
        $editor = $this->makeGlobalUser(
            'estados.export',
            'municipios.export',
            'comunidades.export',
            'children.export',
            'reinscripciones.export'
        );

        $this->actingAs($editor)
            ->get('/usuarios/create')
            ->assertOk()
            ->assertSee('estados.export')
            ->assertSee('municipios.export')
            ->assertSee('comunidades.export')
            ->assertSee('children.export')
            ->assertSee('reinscripciones.export');
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

    public function test_edit_form_shows_export_permissions_when_editor_has_them(): void
    {
        $target = User::factory()->create(['diocese_id' => null]);
        Profile::create(['user_id' => $target->id, 'name' => 'Ana', 'paterno' => 'López', 'materno' => null]);

        $editor = $this->makeGlobalUser(
            'estados.export',
            'municipios.export',
            'comunidades.export',
            'children.export',
            'reinscripciones.export'
        );

        $this->actingAs($editor)
            ->get("/usuarios/{$target->id}/edit")
            ->assertOk()
            ->assertSee('estados.export')
            ->assertSee('municipios.export')
            ->assertSee('comunidades.export')
            ->assertSee('children.export')
            ->assertSee('reinscripciones.export');
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

    public function test_update_can_grant_export_permission_to_user(): void
    {
        $target = User::factory()->create(['diocese_id' => null]);
        Profile::create(['user_id' => $target->id, 'name' => 'Mario', 'paterno' => 'Lopez', 'materno' => null]);

        $permission = Permission::firstOrCreate(
            ['name' => 'municipios.export', 'guard_name' => 'web'],
            ['description' => 'municipios.export', 'module_key' => 'regions']
        );

        $editor = $this->makeGlobalUser('municipios.export');

        $this->actingAs($editor)
            ->put("/usuarios/{$target->id}", $this->validUserPayload([
                'email' => $target->email,
                'name' => 'Mario',
                'paterno' => 'Lopez',
                'permissions' => [$permission->id],
            ]))
            ->assertRedirect('/usuarios');

        $target->refresh();

        $this->assertTrue($target->hasDirectPermission('municipios.export'));
        $this->assertTrue($target->can('municipios.export'));
    }

    public function test_update_can_revoke_export_permission_from_user(): void
    {
        $target = User::factory()->create(['diocese_id' => null]);
        Profile::create(['user_id' => $target->id, 'name' => 'Lucia', 'paterno' => 'Mora', 'materno' => null]);

        $permission = Permission::firstOrCreate(
            ['name' => 'estados.export', 'guard_name' => 'web'],
            ['description' => 'estados.export', 'module_key' => 'regions']
        );

        $target->givePermissionTo($permission);

        $editor = $this->makeGlobalUser('estados.export');

        $this->actingAs($editor)
            ->put("/usuarios/{$target->id}", $this->validUserPayload([
                'email' => $target->email,
                'name' => 'Lucia',
                'paterno' => 'Mora',
                'permissions' => [],
            ]))
            ->assertRedirect('/usuarios');

        $target->refresh();

        $this->assertFalse($target->hasDirectPermission('estados.export'));
        $this->assertFalse($target->can('estados.export'));
    }

    public function test_update_can_customize_permissions_by_removing_role_inherited_permission(): void
    {
        $readPermission = Permission::firstOrCreate(
            ['name' => 'usuarios.read', 'guard_name' => 'web'],
            ['description' => 'usuarios.read', 'module_key' => 'security']
        );
        $updatePermission = Permission::firstOrCreate(
            ['name' => 'usuarios.update', 'guard_name' => 'web'],
            ['description' => 'usuarios.update', 'module_key' => 'security']
        );

        $role = Role::create(['name' => 'Gestor Usuarios', 'guard_name' => 'web']);
        $role->givePermissionTo([$readPermission, $updatePermission]);

        $target = User::factory()->create(['diocese_id' => null]);
        $target->assignRole($role);
        Profile::create(['user_id' => $target->id, 'name' => 'Raul', 'paterno' => 'Ramos', 'materno' => null]);

        $editor = $this->makeGlobalUser('usuarios.read', 'usuarios.update');

        $this->actingAs($editor)
            ->put("/usuarios/{$target->id}", $this->validUserPayload([
                'email' => $target->email,
                'name' => 'Raul',
                'paterno' => 'Ramos',
                'role_id' => $role->id,
                'permissions' => [$readPermission->id],
            ]))
            ->assertRedirect('/usuarios');

        $target->refresh();

        $this->assertFalse($target->hasRole($role));
        $this->assertTrue($target->hasDirectPermission('usuarios.read'));
        $this->assertTrue($target->can('usuarios.read'));
        $this->assertFalse($target->can('usuarios.update'));
    }

    public function test_update_preserves_role_when_adding_direct_permission_to_role_permissions(): void
    {
        $readPermission = Permission::firstOrCreate(
            ['name' => 'roles.read', 'guard_name' => 'web'],
            ['description' => 'roles.read', 'module_key' => 'security']
        );
        $extraPermission = Permission::firstOrCreate(
            ['name' => 'roles.update', 'guard_name' => 'web'],
            ['description' => 'roles.update', 'module_key' => 'security']
        );

        $role = Role::create(['name' => 'Lector Roles', 'guard_name' => 'web']);
        $role->givePermissionTo($readPermission);

        $target = User::factory()->create(['diocese_id' => null]);
        $target->assignRole($role);
        Profile::create(['user_id' => $target->id, 'name' => 'Sofia', 'paterno' => 'Suarez', 'materno' => null]);

        $editor = $this->makeGlobalUser('roles.read', 'roles.update');

        $this->actingAs($editor)
            ->put("/usuarios/{$target->id}", $this->validUserPayload([
                'email' => $target->email,
                'name' => 'Sofia',
                'paterno' => 'Suarez',
                'role_id' => $role->id,
                'permissions' => [$readPermission->id, $extraPermission->id],
            ]))
            ->assertRedirect('/usuarios');

        $target->refresh();

        $this->assertTrue($target->hasRole($role));
        $this->assertFalse($target->hasDirectPermission('roles.read'));
        $this->assertTrue($target->hasDirectPermission('roles.update'));
        $this->assertTrue($target->can('roles.read'));
        $this->assertTrue($target->can('roles.update'));
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
