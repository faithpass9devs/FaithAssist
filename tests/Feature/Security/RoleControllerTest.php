<?php

namespace Tests\Feature\Security;

use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Concerns\ControllerTestHelpers;
use Tests\TestCase;

class RoleControllerTest extends TestCase
{
    use ControllerTestHelpers, RefreshDatabase;

    public function test_permissions_seeder_removes_obsolete_messaging_permissions(): void
    {
        $permission = Permission::create([
            'name' => 'whatsapp.send',
            'guard_name' => 'web',
            'module_key' => 'whatsapp',
            'description' => 'Permiso de mensajeria retirado',
        ]);
        $role = Role::create(['name' => 'Mensajeria', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $this->seed(PermissionsSeeder::class);

        $this->assertDatabaseMissing('permissions', ['name' => 'whatsapp.send']);
        $this->assertDatabaseMissing('role_has_permissions', ['permission_id' => $permission->id]);
        $this->assertDatabaseHas('permissions', ['name' => 'children.read']);
    }

    // ── Authorization ─────────────────────────────────────────────────────────

    /**
     * RoleController has no explicit authorization — any authenticated user can access it.
     */
    public function test_unauthenticated_user_is_redirected_from_index(): void
    {
        $this->get('/roles')->assertRedirect('/login');
    }

    public function test_unauthenticated_user_is_redirected_from_create(): void
    {
        $this->get('/roles/create')->assertRedirect('/login');
    }

    // ── CRUD ──────────────────────────────────────────────────────────────────

    public function test_index_returns_inertia_response(): void
    {
        $user = $this->makeGlobalUser();

        $this->actingAs($user)->get('/roles')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Security/Roles/Index'));
    }

    public function test_create_returns_inertia_form(): void
    {
        $user = $this->makeGlobalUser();

        $this->actingAs($user)->get('/roles/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Security/Roles/Form'));
    }

    public function test_store_creates_role_and_redirects(): void
    {
        $user = $this->makeGlobalUser();

        $response = $this->actingAs($user)
            ->post('/roles', [
                'name'        => 'Coordinador',
                'description' => 'Coordina actividades',
                'permissions' => [],
            ]);

        $response->assertRedirect('/roles');
        $this->assertDatabaseHas('roles', ['name' => 'Coordinador']);
    }

    public function test_store_assigns_permissions_to_role(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $perm = Permission::firstOrCreate(['name' => 'estados.read', 'guard_name' => 'web'], ['description' => 'test', 'module_key' => 'regions']);
        $user = $this->makeGlobalUser();

        $this->actingAs($user)
            ->post('/roles', [
                'name'        => 'Lector',
                'description' => 'Solo lectura',
                'permissions' => [$perm->id],
            ])
            ->assertRedirect('/roles');

        $role = Role::where('name', 'Lector')->firstOrFail();
        $this->assertTrue($role->hasPermissionTo('estados.read'));
    }

    public function test_update_modifies_role_and_redirects(): void
    {
        $role = Role::create(['name' => 'Original', 'description' => 'Desc', 'guard_name' => 'web']);
        $user = $this->makeGlobalUser();

        $this->actingAs($user)
            ->put("/roles/{$role->id}", [
                'name'        => 'Actualizado',
                'description' => 'Nueva desc',
                'permissions' => [],
            ])
            ->assertRedirect('/roles');

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'Actualizado']);
    }

    public function test_edit_returns_inertia_form_with_role_data(): void
    {
        $role = Role::create(['name' => 'Para Editar', 'guard_name' => 'web']);
        $user = $this->makeGlobalUser();

        $this->actingAs($user)
            ->get("/roles/{$role->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Security/Roles/Form')
                ->where('role.name', 'Para Editar')
            );
    }
}
