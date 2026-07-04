<?php

namespace Tests\Feature\Catechism;

use App\Globals\BloodType;
use App\Globals\Sex;
use App\Globals\Status;
use App\Models\Catechism\Child;
use App\Models\Catechism\ChildLevelAssignment;
use App\Models\Catechism\ChildReinscription;
use App\Models\Operation\Level;
use App\Models\Operation\Period;
use App\Models\Operation\PeriodMovement;
use App\Models\Operation\PeriodMovementType;
use App\Services\CatechismPeriodMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\ControllerTestHelpers;
use Tests\TestCase;

class ReinscriptionControllerTest extends TestCase
{
    use ControllerTestHelpers, RefreshDatabase;

    public function test_index_requires_read_permission(): void
    {
        $user = $this->makeGlobalUser();

        $this->actingAs($user)->get('/reinscripciones')->assertForbidden();
    }

    public function test_index_returns_reinscriptions_page(): void
    {
        $user = $this->makeGlobalUser('reinscripciones.read');

        $this->actingAs($user)->get('/reinscripciones')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Catechism/Reinscriptions/Index'));
    }

    public function test_user_without_export_permission_gets_403_on_export(): void
    {
        $user = $this->makeGlobalUser('reinscripciones.read');

        $this->actingAs($user)
            ->get('/reinscripciones/export')
            ->assertForbidden();
    }

    public function test_create_returns_reinscription_form_page(): void
    {
        $chain = $this->createChain();
        $fromLevel = $this->createLevel($chain, ['name' => 'PRIMERO']);
        $toLevel = $this->createLevel($chain, ['name' => 'SEGUNDO']);
        $movement = $this->createActiveMovement($chain, CatechismPeriodMovementService::INSCRIPTIONS);
        $child = Child::query()->create($this->childRow($chain));
        $this->assignLevel($child, $fromLevel, $movement);
        $user = $this->makeGlobalUser('reinscripciones.create');

        $this->actingAs($user)->get("/reinscripciones/{$child->id}/create")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Catechism/Reinscriptions/Form')
                ->where('child.id', $child->id)
                ->where('levels.0.id', $fromLevel->id)
                ->where('levels.1.id', $toLevel->id));
    }

    public function test_store_requires_active_reinscription_movement(): void
    {
        $chain = $this->createChain();
        $fromLevel = $this->createLevel($chain, ['name' => 'PRIMERO']);
        $toLevel = $this->createLevel($chain, ['name' => 'SEGUNDO']);
        $movement = $this->createActiveMovement($chain, CatechismPeriodMovementService::INSCRIPTIONS);
        $child = Child::query()->create($this->childRow($chain));
        $assignment = $this->assignLevel($child, $fromLevel, $movement);
        $user = $this->makeGlobalUser('reinscripciones.create');

        $this->actingAs($user)
            ->post('/reinscripciones', [
                'child_id' => $child->id,
                'to_level_ids' => [$toLevel->id],
            ])
            ->assertSessionHasErrors('child_id');
    }

    public function test_store_creates_reinscription_record_and_assigns_destination_levels(): void
    {
        $chain = $this->createChain();
        $fromLevel = $this->createLevel($chain, ['name' => 'PRIMERO']);
        $toLevel = $this->createLevel($chain, ['name' => 'SEGUNDO']);
        $extraLevel = $this->createLevel($chain, ['name' => 'TERCERO']);
        $inscriptionMovement = $this->createActiveMovement($chain, CatechismPeriodMovementService::INSCRIPTIONS);
        $reinscriptionMovement = $this->createActiveMovement($chain, CatechismPeriodMovementService::REINSCRIPTIONS);
        $child = Child::query()->create($this->childRow($chain));
        $assignment = $this->assignLevel($child, $fromLevel, $inscriptionMovement);
        $user = $this->makeGlobalUser('reinscripciones.create');

        $this->actingAs($user)
            ->post('/reinscripciones', [
                'child_id' => $child->id,
                'to_level_ids' => [$toLevel->id, $extraLevel->id],
            ])
            ->assertRedirect('/reinscripciones');

        $this->assertDatabaseHas('child_reinscriptions', [
            'child_id' => $child->id,
            'period_id' => $reinscriptionMovement->period_id,
            'period_movement_id' => $reinscriptionMovement->id,
        ]);
        $reinscription = ChildReinscription::query()->where('child_id', $child->id)->firstOrFail();
        $this->assertSame([$fromLevel->id], $reinscription->from_level_ids);
        $this->assertSame([$toLevel->id, $extraLevel->id], $reinscription->to_level_ids);
        $this->assertDatabaseHas('child_level_assignments', [
            'id' => $assignment->id,
            'status' => Status::COMPLETED,
        ]);
        $this->assertDatabaseHas('child_level_assignments', [
            'child_id' => $child->id,
            'level_id' => $toLevel->id,
            'period_movement_id' => $reinscriptionMovement->id,
            'status' => Status::ACTIVE,
        ]);
        $this->assertDatabaseHas('child_level_assignments', [
            'child_id' => $child->id,
            'level_id' => $extraLevel->id,
            'period_movement_id' => $reinscriptionMovement->id,
            'status' => Status::ACTIVE,
        ]);
    }

    public function test_store_rejects_already_reinscribed_child(): void
    {
        $chain = $this->createChain();
        $fromLevel = $this->createLevel($chain, ['name' => 'PRIMERO']);
        $toLevel = $this->createLevel($chain, ['name' => 'SEGUNDO']);
        $inscriptionMovement = $this->createActiveMovement($chain, CatechismPeriodMovementService::INSCRIPTIONS);
        $reinscriptionMovement = $this->createActiveMovement($chain, CatechismPeriodMovementService::REINSCRIPTIONS);
        $child = Child::query()->create($this->childRow($chain));
        $this->assignLevel($child, $fromLevel, $inscriptionMovement);
        ChildReinscription::query()->create([
            'child_id' => $child->id,
            'period_id' => $reinscriptionMovement->period_id,
            'period_movement_id' => $reinscriptionMovement->id,
            'from_level_ids' => [$fromLevel->id],
            'to_level_ids' => [$toLevel->id],
        ]);
        $user = $this->makeGlobalUser('reinscripciones.create');

        $this->actingAs($user)
            ->post('/reinscripciones', [
                'child_id' => $child->id,
                'to_level_ids' => [$toLevel->id],
            ])
            ->assertSessionHasErrors('child_id');
    }

    public function test_export_returns_xlsx_for_authorized_user(): void
    {
        $chain = $this->createChain();
        $level = $this->createLevel($chain, ['name' => 'PRIMERO']);
        $movement = $this->createActiveMovement($chain, CatechismPeriodMovementService::INSCRIPTIONS);
        $child = Child::query()->create($this->childRow($chain));
        $this->assignLevel($child, $level, $movement);

        $user = $this->makeGlobalUser('reinscripciones.read', 'reinscripciones.export');

        $response = $this->actingAs($user)->get('/reinscripciones/export');

        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type') ?? ''
        );
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
            'name' => 'PERIODO '.$typeName,
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
}
