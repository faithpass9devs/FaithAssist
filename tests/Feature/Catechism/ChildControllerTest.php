<?php

namespace Tests\Feature\Catechism;

use App\Globals\BloodType;
use App\Globals\Sex;
use App\Globals\Status;
use App\Models\Catechism\Child;
use App\Models\Catechism\ChildLevelAssignment;
use App\Models\Operation\Level;
use App\Models\Operation\Period;
use App\Models\Operation\PeriodMovement;
use App\Models\Operation\PeriodMovementType;
use App\Services\CatechismPeriodMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\ControllerTestHelpers;
use Tests\TestCase;

class ChildControllerTest extends TestCase
{
    use ControllerTestHelpers, RefreshDatabase;

    public function test_index_requires_read_permission(): void
    {
        $user = $this->makeGlobalUser();

        $this->actingAs($user)->get('/children')->assertForbidden();
    }

    public function test_index_returns_children_page(): void
    {
        $user = $this->makeGlobalUser('children.read');

        $this->actingAs($user)->get('/children')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Catechism/Children/Index'));
    }

    public function test_user_without_export_permission_gets_403_on_export(): void
    {
        $user = $this->makeGlobalUser('children.read');

        $this->actingAs($user)
            ->get('/children/export')
            ->assertForbidden();
    }

    public function test_create_returns_children_form_options(): void
    {
        $chain = $this->createChain();
        $user = $this->makeGlobalUser('children.create', 'children.scope.all');

        $this->actingAs($user)->get('/children/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Catechism/Children/Form')
                ->where('child', null)
                ->where('churches.0.id', $chain['church']->id)
                ->where('communities.0.id', $chain['community']->id));
    }

    public function test_scoped_create_options_are_limited_to_user_scope(): void
    {
        $chain1 = $this->createChain();
        $chain2 = $this->createChain();
        $chain2['community']->update(['name' => 'Comunidad B']);
        $chain2['church']->update(['name' => 'Parroquia B']);

        $user = $this->makeChurchUser($chain1['diocese'], $chain1['deanery'], $chain1['church'], 'children.create');

        $this->actingAs($user)->get('/children/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('churches.0.id', $chain1['church']->id)
                ->where('churches.0.municipality_id', $chain1['municipality']->id)
                ->where('municipalities.0.id', $chain1['municipality']->id)
                ->where('communities.0.id', $chain1['community']->id));
    }

    public function test_store_creates_child_with_generated_code(): void
    {
        $chain = $this->createChain();
        $level = $this->createLevel($chain);
        $secondLevel = $this->createLevel($chain, ['name' => 'SEGUNDO']);
        $this->createActiveMovement($chain, CatechismPeriodMovementService::INSCRIPTIONS);
        $user = $this->makeGlobalUser('children.create', 'children.scope.all');

        $this->actingAs($user)
            ->post('/children', $this->payload($chain, ['level_ids' => [$level->id, $secondLevel->id]]))
            ->assertRedirect('/children');

        $this->assertDatabaseHas('children', [
            'name' => 'JUAN',
            'paterno' => 'PEREZ',
            'materno' => 'GOMEZ',
            'code' => now()->format('Y')."-JPG-20180314-CH{$chain['church']->id}-0001",
        ]);
        $this->assertDatabaseHas('child_level_assignments', [
            'level_id' => $level->id,
            'status' => Status::ACTIVE,
        ]);
        $this->assertDatabaseHas('child_level_assignments', [
            'level_id' => $secondLevel->id,
            'status' => Status::ACTIVE,
        ]);
    }

    public function test_privacy_terms_must_be_accepted(): void
    {
        $chain = $this->createChain();
        $level = $this->createLevel($chain);
        $this->createActiveMovement($chain, CatechismPeriodMovementService::INSCRIPTIONS);
        $user = $this->makeGlobalUser('children.create', 'children.scope.all');
        $payload = $this->payload($chain, ['privacy_terms' => false, 'level_ids' => [$level->id]]);

        $this->actingAs($user)
            ->post('/children', $payload)
            ->assertSessionHasErrors('privacy_terms');
    }

    public function test_store_requires_active_inscription_movement(): void
    {
        $chain = $this->createChain();
        $level = $this->createLevel($chain);
        $user = $this->makeGlobalUser('children.create', 'children.scope.all');

        $this->actingAs($user)
            ->post('/children', $this->payload($chain, ['level_ids' => [$level->id]]))
            ->assertSessionHasErrors('church_id');
    }

    public function test_church_scoped_user_sees_children_in_own_scope_only(): void
    {
        $chain1 = $this->createChain();
        $chain2 = $this->createChain();
        $chain2['community']->update(['name' => 'Comunidad B']);
        $chain2['church']->update(['name' => 'Parroquia B']);

        Child::query()->create($this->childRow($chain1, ['code' => '2026-AAA-20180314-CH1-0001']));
        Child::query()->create($this->childRow($chain2, ['code' => '2026-BBB-20180314-CH2-0001']));

        $user = $this->makeChurchUser($chain1['diocese'], $chain1['deanery'], $chain1['church'], 'children.read');

        $this->actingAs($user)->get('/children')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('children.total', 1));
    }

    public function test_non_global_user_with_scope_all_still_uses_assigned_scope(): void
    {
        $chain1 = $this->createChain();
        $chain2 = $this->createChain();
        $chain2['community']->update(['name' => 'Comunidad B']);
        $chain2['church']->update(['name' => 'Parroquia B']);

        Child::query()->create($this->childRow($chain1, ['code' => '2026-AAA-20180314-CH1-0001']));
        Child::query()->create($this->childRow($chain2, ['code' => '2026-BBB-20180314-CH2-0001']));

        $user = $this->makeChurchUser(
            $chain1['diocese'],
            $chain1['deanery'],
            $chain1['church'],
            'children.read',
            'children.scope.all'
        );

        $this->actingAs($user)->get('/children')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('children.total', 1));
    }

    public function test_index_filters_by_church_and_municipality(): void
    {
        $chain1 = $this->createChain();
        $chain2 = $this->createChain();
        $chain2['community']->update(['name' => 'Comunidad B']);
        $chain2['church']->update(['name' => 'Parroquia B']);

        Child::query()->create($this->childRow($chain1, ['code' => '2026-AAA-20180314-CH1-0001']));
        Child::query()->create($this->childRow($chain2, ['code' => '2026-BBB-20180314-CH2-0001']));

        $user = $this->makeGlobalUser('children.read');

        $this->actingAs($user)->get("/children?church_id={$chain2['church']->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('children.total', 1)
                ->where('filters.church_id', $chain2['church']->id));

        $this->actingAs($user)->get("/children?municipality_id={$chain1['municipality']->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('children.total', 1)
                ->where('filters.municipality_id', $chain1['municipality']->id));
    }

    public function test_index_filters_by_level(): void
    {
        $chain = $this->createChain();
        $level1 = $this->createLevel($chain, ['name' => 'PRIMERO']);
        $level2 = $this->createLevel($chain, ['name' => 'SEGUNDO']);
        $movement = $this->createActiveMovement($chain, CatechismPeriodMovementService::INSCRIPTIONS);
        $child1 = Child::query()->create($this->childRow($chain, ['code' => '2026-AAA-20180314-CH1-0001']));
        $child2 = Child::query()->create($this->childRow($chain, ['code' => '2026-BBB-20180314-CH1-0002']));

        $this->assignLevel($child1, $level1, $movement);
        $this->assignLevel($child2, $level2, $movement);

        $user = $this->makeGlobalUser('children.read');

        $this->actingAs($user)->get("/children?level_id={$level2->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('children.total', 1)
                ->where('filters.level_id', $level2->id));
    }

    public function test_invalid_enum_values_are_rejected(): void
    {
        $chain = $this->createChain();
        $level = $this->createLevel($chain);
        $this->createActiveMovement($chain, CatechismPeriodMovementService::INSCRIPTIONS);
        $user = $this->makeGlobalUser('children.create', 'children.scope.all');
        $payload = $this->payload($chain, ['sex' => 'other', 'blood_type' => 'z_positive', 'level_ids' => [$level->id]]);

        $this->actingAs($user)
            ->post('/children', $payload)
            ->assertSessionHasErrors(['sex', 'blood_type']);
    }

    public function test_update_does_not_modify_personal_data(): void
    {
        $chain = $this->createChain();
        $child = Child::query()->create($this->childRow($chain));
        $user = $this->makeGlobalUser('children.update');

        $payload = $this->payload($chain, [
            'name' => 'Carlos',
            'paterno' => 'Lopez',
            'materno' => 'Ramos',
            'birthdate' => '2019-04-15',
            'sex' => Sex::FEMALE,
            'blood_type' => BloodType::A_NEGATIVE,
            'email' => 'nuevo@example.com',
            'phone' => '5500000000',
            'status' => Status::INACTIVE,
        ]);

        $this->actingAs($user)
            ->put("/children/{$child->id}", $payload)
            ->assertRedirect('/children');

        $child->refresh();

        $this->assertSame('JUAN', $child->name);
        $this->assertSame('PEREZ', $child->paterno);
        $this->assertSame('GOMEZ', $child->materno);
        $this->assertSame('2018-03-14', $child->birthdate->format('Y-m-d'));
        $this->assertSame(Sex::MALE, $child->sex);
        $this->assertSame(BloodType::O_POSITIVE, $child->blood_type);
        $this->assertSame('nuevo@example.com', $child->email);
        $this->assertSame('5500000000', $child->phone);
        $this->assertSame(Status::INACTIVE, $child->status);
    }

    public function test_export_returns_xlsx_for_authorized_user(): void
    {
        $chain = $this->createChain();
        Child::query()->create($this->childRow($chain));
        $user = $this->makeGlobalUser('children.read', 'children.export');

        $response = $this->actingAs($user)->get('/children/export');

        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type') ?? ''
        );
    }

    public function test_badge_pdf_returns_inline_pdf_response(): void
    {
        $chain = $this->createChain();
        $child = Child::query()->create($this->childRow($chain));
        $user = $this->makeGlobalUser('children.read');

        $response = $this->actingAs($user)->get("/children/{$child->id}/badge-pdf");

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type') ?? '');
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition') ?? '');
    }

    public function test_badge_pdf_is_forbidden_outside_scope(): void
    {
        $chain1 = $this->createChain();
        $chain2 = $this->createChain();
        $child = Child::query()->create($this->childRow($chain2, ['code' => '2026-BBB-20180314-CH2-0001']));

        $user = $this->makeChurchUser($chain1['diocese'], $chain1['deanery'], $chain1['church'], 'children.read');

        $this->actingAs($user)
            ->get("/children/{$child->id}/badge-pdf")
            ->assertForbidden();
    }

    private function payload(array $chain, array $overrides = []): array
    {
        return array_merge([
            'church_id' => $chain['church']->id,
            'community_id' => $chain['community']->id,
            'name' => 'Juan',
            'paterno' => 'Perez',
            'materno' => 'Gomez',
            'birthdate' => '2018-03-14',
            'sex' => Sex::MALE,
            'email' => 'juan@example.com',
            'phone_lada' => null,
            'phone' => '5512345678',
            'emergency_phone_lada' => null,
            'emergency_phone' => '5598765432',
            'blood_type' => BloodType::O_POSITIVE,
            'observations' => 'Sin observaciones',
            'privacy_terms' => true,
            'status' => Status::ACTIVE,
            'level_ids' => [],
        ], $overrides);
    }

    private function childRow(array $chain, array $overrides = []): array
    {
        return array_merge([
            'church_id' => $chain['church']->id,
            'community_id' => $chain['community']->id,
            'name' => 'JUAN',
            'paterno' => 'PEREZ',
            'materno' => 'GOMEZ',
            'code' => '2026-JPG-20180314-CH'.$chain['church']->id.'-0001',
            'birthdate' => '2018-03-14',
            'sex' => Sex::MALE,
            'blood_type' => BloodType::O_POSITIVE,
            'privacy_terms' => true,
            'status' => Status::ACTIVE,
        ], $overrides);
    }

    private function createLevel(array $chain, array $overrides = []): Level
    {
        return Level::query()->create(array_merge([
            'diocese_id' => $chain['diocese']->id,
            'name' => 'NIVEL TEST',
            'status' => Status::ACTIVE,
        ], $overrides));
    }

    private function createActiveMovement(array $chain, string $typeName): PeriodMovement
    {
        $type = PeriodMovementType::query()->create([
            'name' => $typeName,
            'status' => Status::ACTIVE,
        ]);
        $period = Period::query()->create([
            'diocese_id' => $chain['diocese']->id,
            'name' => 'PERIODO TEST',
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => Status::IN_PROGRESS,
        ]);

        return PeriodMovement::query()->create([
            'period_id' => $period->id,
            'period_movement_type_id' => $type->id,
            'status' => Status::IN_PROGRESS,
            'start_date' => now()->subWeek()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
        ]);
    }

    private function assignLevel(Child $child, Level $level, PeriodMovement $movement): ChildLevelAssignment
    {
        return ChildLevelAssignment::query()->create([
            'child_id' => $child->id,
            'level_id' => $level->id,
            'period_id' => $movement->period_id,
            'period_movement_id' => $movement->id,
            'status' => Status::ACTIVE,
            'assigned_at' => now()->toDateString(),
        ]);
    }
}
