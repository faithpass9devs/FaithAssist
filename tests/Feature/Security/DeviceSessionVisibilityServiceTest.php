<?php

namespace Tests\Feature\Security;

use App\Models\Ecclesiastes\Chapel;
use App\Models\User;
use App\Services\Security\DeviceSessionVisibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Feature\Concerns\ControllerTestHelpers;
use Tests\TestCase;

class DeviceSessionVisibilityServiceTest extends TestCase
{
    use ControllerTestHelpers, RefreshDatabase;

    public function test_coordinator_sees_only_lower_roles_in_the_same_parish(): void
    {
        $chain = $this->createChain();
        $otherChain = $this->createChain();
        $viewer = $this->roleUser('Coordinador de Parroquia', ['church_id' => $chain['church']->id]);
        $sameParish = $this->roleUser('Capturista', ['church_id' => $chain['church']->id]);
        $otherParish = $this->roleUser('Capturista', ['church_id' => $otherChain['church']->id]);
        $sameRole = $this->roleUser('Coordinador de Parroquia', ['church_id' => $chain['church']->id]);

        $visible = app(DeviceSessionVisibilityService::class)->visibleUserIds($viewer);

        $this->assertTrue($visible->contains($sameParish->id));
        $this->assertFalse($visible->contains($otherParish->id));
        $this->assertFalse($visible->contains($sameRole->id));
        $this->assertFalse($visible->contains($viewer->id));
    }

    public function test_chapel_scope_only_exposes_lower_roles_assigned_to_the_same_chapel(): void
    {
        $chain = $this->createChain();
        $chapelOne = Chapel::query()->create([
            'name' => 'Capilla Uno',
            'community_id' => $chain['community']->id,
            'church_id' => $chain['church']->id,
            'status' => 'active',
        ]);
        $chapelTwo = Chapel::query()->create([
            'name' => 'Capilla Dos',
            'community_id' => $chain['community']->id,
            'church_id' => $chain['church']->id,
            'status' => 'active',
        ]);
        $viewer = $this->roleUser('Coordinador de Parroquia', [
            'church_id' => $chain['church']->id,
            'chapel_id' => $chapelOne->id,
        ]);
        $sameChapel = $this->roleUser('Capturista', [
            'church_id' => $chain['church']->id,
            'chapel_id' => $chapelOne->id,
        ]);
        $otherChapel = $this->roleUser('Capturista', [
            'church_id' => $chain['church']->id,
            'chapel_id' => $chapelTwo->id,
        ]);
        $parishOnly = $this->roleUser('Capturista', ['church_id' => $chain['church']->id]);

        $visible = app(DeviceSessionVisibilityService::class)->visibleUserIds($viewer);

        $this->assertTrue($visible->contains($sameChapel->id));
        $this->assertFalse($visible->contains($otherChapel->id));
        $this->assertFalse($visible->contains($parishOnly->id));
    }

    public function test_superadmin_sees_all_non_deleted_users_regardless_of_role_or_scope(): void
    {
        $viewer = $this->roleUser('Superadmin');
        $coordinator = $this->roleUser('Coordinador de Parroquia');
        $capturista = $this->roleUser('Capturista');
        $peer = $this->roleUser('Superadmin');
        $customRole = $this->roleUser('Rol personalizado');
        $roleless = User::factory()->create();

        $visible = app(DeviceSessionVisibilityService::class)->visibleUserIds($viewer);

        $this->assertTrue($visible->contains($coordinator->id));
        $this->assertTrue($visible->contains($capturista->id));
        $this->assertTrue($visible->contains($peer->id));
        $this->assertTrue($visible->contains($customRole->id));
        $this->assertTrue($visible->contains($roleless->id));
        $this->assertTrue($visible->contains($viewer->id));
        $this->assertCount(6, $visible);
    }

    public function test_user_without_a_known_role_or_parish_scope_sees_nobody(): void
    {
        $viewer = $this->roleUser('Rol personalizado');
        $target = $this->roleUser('Capturista', ['church_id' => $this->createChain()['church']->id]);

        $this->assertFalse(app(DeviceSessionVisibilityService::class)->visibleUserIds($viewer)->contains($target->id));
    }

    private function roleUser(string $roleName, array $scope = []): User
    {
        $user = User::factory()->create($scope);
        $role = Role::query()->firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $user->assignRole($role);

        return $user;
    }
}
