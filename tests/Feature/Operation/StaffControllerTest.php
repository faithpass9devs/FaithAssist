<?php

namespace Tests\Feature\Operation;

use App\Models\Operation\Staff;
use App\Models\Operation\StaffType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\ControllerTestHelpers;
use Tests\TestCase;

class StaffControllerTest extends TestCase
{
    use ControllerTestHelpers, RefreshDatabase;

    public function test_index_requires_read_permission(): void
    {
        $user = $this->makeGlobalUser();

        $this->actingAs($user)->get('/personal')->assertForbidden();
    }

    public function test_index_returns_staff_page(): void
    {
        $user = $this->makeGlobalUser('personal.read');

        $this->actingAs($user)->get('/personal')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Operation/Staff/Index'));
    }

    public function test_store_creates_staff_with_generated_code(): void
    {
        $chain = $this->createChain();
        $type = StaffType::query()->create([
            'name' => 'CATEQUISTA',
            'status' => 'active',
        ]);
        $user = $this->makeGlobalUser('personal.create');

        $this->actingAs($user)
            ->post('/personal', [
                'staff_type_id' => $type->id,
                'church_id' => $chain['church']->id,
                'community_id' => $chain['community']->id,
                'name' => 'JUAN',
                'paterno' => 'PEREZ',
                'materno' => 'GOMEZ',
                'birthdate' => '1990-01-10',
                'phone_lada' => null,
                'phone' => '5512345678',
            ])
            ->assertRedirect('/personal');

        $this->assertDatabaseHas('staff', [
            'name' => 'JUAN',
            'paterno' => 'PEREZ',
            'staff_type_id' => $type->id,
        ]);

        $staff = Staff::query()->firstOrFail();
        $this->assertNotEmpty($staff->code);
    }

    public function test_church_scoped_user_only_sees_staff_in_own_scope(): void
    {
        $chain1 = $this->createChain();
        $chain2 = $this->createChain();
        $chain2['community']->update(['name' => 'Comunidad B']);
        $chain2['church']->update(['name' => 'Parroquia B']);

        $type = StaffType::query()->create(['name' => 'CATEQUISTA', 'status' => 'active']);

        Staff::query()->create($this->staffRow($chain1, $type->id, ['code' => '2026-JPG-19900110-CH1-0001']));
        Staff::query()->create($this->staffRow($chain2, $type->id, ['code' => '2026-JPG-19900110-CH2-0001']));

        $user = $this->makeChurchUser($chain1['diocese'], $chain1['deanery'], $chain1['church'], 'personal.read');

        $this->actingAs($user)->get('/personal')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('staff.total', 1));
    }

    public function test_badge_pdf_is_forbidden_outside_scope(): void
    {
        $chain1 = $this->createChain();
        $chain2 = $this->createChain();
        $chain2['community']->update(['name' => 'Comunidad B']);
        $chain2['church']->update(['name' => 'Parroquia B']);

        $type = StaffType::query()->create(['name' => 'CATEQUISTA', 'status' => 'active']);
        $staff = Staff::query()->create($this->staffRow($chain2, $type->id, ['code' => '2026-JPG-19900110-CH2-0001']));

        $user = $this->makeChurchUser($chain1['diocese'], $chain1['deanery'], $chain1['church'], 'personal.read');

        $this->actingAs($user)
            ->get("/personal/{$staff->id}/badge-pdf")
            ->assertForbidden();
    }

    private function staffRow(array $chain, int $staffTypeId, array $overrides = []): array
    {
        return array_merge([
            'staff_type_id' => $staffTypeId,
            'church_id' => $chain['church']->id,
            'community_id' => $chain['community']->id,
            'name' => 'JUAN',
            'paterno' => 'PEREZ',
            'materno' => 'GOMEZ',
            'phone_lada' => null,
            'phone' => '5512345678',
            'birthdate' => '1990-01-10',
            'code' => '2026-JPG-19900110-CH'.$chain['church']->id.'-0001',
        ], $overrides);
    }
}
