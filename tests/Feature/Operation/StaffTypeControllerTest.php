<?php

namespace Tests\Feature\Operation;

use App\Models\Operation\StaffType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Feature\Concerns\ControllerTestHelpers;
use Tests\TestCase;

class StaffTypeControllerTest extends TestCase
{
    use ControllerTestHelpers, RefreshDatabase;

    public function test_only_superadmin_can_access_staff_types_even_with_permissions(): void
    {
        $user = $this->makeGlobalUser('tipos_personal.read');

        $this->actingAs($user)
            ->get('/tipos-personal')
            ->assertForbidden();
    }

    public function test_superadmin_with_permission_can_access_staff_types_index(): void
    {
        $user = $this->makeGlobalUser('tipos_personal.read');
        $user->assignRole(Role::query()->firstOrCreate(['name' => 'Superadmin', 'guard_name' => 'web']));

        $this->actingAs($user)
            ->get('/tipos-personal')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Operation/StaffTypes/Index'));
    }

    public function test_superadmin_can_create_update_and_delete_staff_type(): void
    {
        $user = $this->makeGlobalUser('tipos_personal.create', 'tipos_personal.update', 'tipos_personal.delete');
        $user->assignRole(Role::query()->firstOrCreate(['name' => 'Superadmin', 'guard_name' => 'web']));

        $created = $this->actingAs($user)
            ->postJson('/tipos-personal', [
                'name' => 'CATEQUISTA',
                'description' => 'Personal de catequesis',
                'status' => 'active',
            ]);

        $created->assertCreated()->assertJsonPath('success', true);

        $type = StaffType::query()->firstOrFail();

        $this->actingAs($user)
            ->putJson("/tipos-personal/{$type->id}", [
                'name' => 'COORDINADOR',
                'description' => 'Coordinación',
                'status' => 'inactive',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'COORDINADOR');

        $this->actingAs($user)
            ->deleteJson("/tipos-personal/{$type->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('staff_types', ['id' => $type->id]);
    }
}
