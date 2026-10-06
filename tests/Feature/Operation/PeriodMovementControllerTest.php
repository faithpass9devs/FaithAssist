<?php

namespace Tests\Feature\Operation;

use App\Globals\Status;
use App\Models\Operation\Period;
use App\Models\Operation\PeriodMovement;
use App\Models\Operation\PeriodMovementType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\ControllerTestHelpers;
use Tests\TestCase;

class PeriodMovementControllerTest extends TestCase
{
    use ControllerTestHelpers, RefreshDatabase;

    private function createPeriodAndType(): array
    {
        $chain = $this->createChain();
        $period = Period::query()->create([
            'diocese_id' => $chain['diocese']->id,
            'name'       => 'Periodo Test',
            'start_date' => '2025-01-01',
            'end_date'   => '2025-12-31',
            'years'      => '2025',
            'status'     => Status::IN_PROGRESS,
        ]);
        $type = PeriodMovementType::query()->create(['name' => 'Tipo Test', 'status' => 'active']);

        return compact('chain', 'period', 'type');
    }

    // ── Authorization ─────────────────────────────────────────────────────────

    public function test_unauthenticated_user_is_redirected_from_index(): void
    {
        $this->get('/periodo-movimientos')->assertRedirect('/login');
    }

    public function test_user_without_read_permission_gets_403_on_index(): void
    {
        $user = $this->makeGlobalUser();
        $this->actingAs($user)->get('/periodo-movimientos')->assertForbidden();
    }

    public function test_user_without_create_permission_gets_403_on_store(): void
    {
        ['chain' => $chain, 'period' => $period, 'type' => $type] = $this->createPeriodAndType();
        $user = $this->makeGlobalUser('periodo_movimientos.read');

        $this->actingAs($user)
            ->postJson('/periodo-movimientos', [
                'period_id'              => $period->id,
                'church_id'              => $chain['church']->id,
                'period_movement_type_id' => $type->id,
                'status'                 => Status::PENDING,
                'start_date'             => '2025-02-01',
                'end_date'               => '2025-03-31',
            ])
            ->assertForbidden();
    }

    // ── Scope ─────────────────────────────────────────────────────────────────

    public function test_global_user_sees_all_movements(): void
    {
        ['chain' => $chain1, 'period' => $period1, 'type' => $type] = $this->createPeriodAndType();
        $chain2 = $this->createChain();
        $chain2['diocese']->update(['name' => 'Diócesis B']);
        $period2 = Period::query()->create(['diocese_id' => $chain2['diocese']->id, 'name' => 'Periodo B', 'start_date' => '2025-01-01', 'end_date' => '2025-12-31', 'years' => '2025', 'status' => Status::IN_PROGRESS]);

        PeriodMovement::query()->create(['period_id' => $period1->id, 'church_id' => $chain1['church']->id, 'period_movement_type_id' => $type->id, 'status' => Status::PENDING, 'start_date' => '2025-02-01', 'end_date' => '2025-03-31']);
        PeriodMovement::query()->create(['period_id' => $period2->id, 'church_id' => $chain2['church']->id, 'period_movement_type_id' => $type->id, 'status' => Status::PENDING, 'start_date' => '2025-02-01', 'end_date' => '2025-03-31']);

        $user = $this->makeGlobalUser('periodo_movimientos.read');

        $this->actingAs($user)->get('/periodo-movimientos')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('movements.total', 2));
    }

    public function test_diocese_scoped_user_sees_only_movements_of_own_diocese(): void
    {
        ['chain' => $chain1, 'period' => $period1, 'type' => $type] = $this->createPeriodAndType();
        $chain2 = $this->createChain();
        $chain2['diocese']->update(['name' => 'Diócesis B']);
        $period2 = Period::query()->create(['diocese_id' => $chain2['diocese']->id, 'name' => 'Periodo B', 'start_date' => '2025-01-01', 'end_date' => '2025-12-31', 'years' => '2025', 'status' => Status::IN_PROGRESS]);

        PeriodMovement::query()->create(['period_id' => $period1->id, 'church_id' => $chain1['church']->id, 'period_movement_type_id' => $type->id, 'status' => Status::PENDING, 'start_date' => '2025-02-01', 'end_date' => '2025-03-31']);
        PeriodMovement::query()->create(['period_id' => $period2->id, 'church_id' => $chain2['church']->id, 'period_movement_type_id' => $type->id, 'status' => Status::PENDING, 'start_date' => '2025-02-01', 'end_date' => '2025-03-31']);

        $user = $this->makeDioceseUser($chain1['diocese'], 'periodo_movimientos.read');

        $this->actingAs($user)->get('/periodo-movimientos')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('movements.total', 1));
    }

    // ── CRUD ──────────────────────────────────────────────────────────────────

    public function test_index_returns_inertia_response(): void
    {
        $user = $this->makeGlobalUser('periodo_movimientos.read');

        $this->actingAs($user)->get('/periodo-movimientos')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Operation/PeriodMovements/Index'));
    }

    public function test_store_creates_movement_and_returns_201(): void
    {
        ['chain' => $chain, 'period' => $period, 'type' => $type] = $this->createPeriodAndType();
        $user = $this->makeGlobalUser('periodo_movimientos.create');

        $response = $this->actingAs($user)
            ->postJson('/periodo-movimientos', [
                'period_id'               => $period->id,
                'church_id'               => $chain['church']->id,
                'period_movement_type_id' => $type->id,
                'status'                  => Status::PENDING,
                'start_date'              => '2025-02-01',
                'end_date'                => '2025-04-30',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', Status::PENDING);

        $this->assertDatabaseHas('period_movements', ['period_id' => $period->id]);
    }

    public function test_update_modifies_movement_and_returns_200(): void
    {
        ['chain' => $chain, 'period' => $period, 'type' => $type] = $this->createPeriodAndType();
        $movement = PeriodMovement::query()->create(['period_id' => $period->id, 'church_id' => $chain['church']->id, 'period_movement_type_id' => $type->id, 'status' => Status::PENDING, 'start_date' => '2025-02-01', 'end_date' => '2025-03-31']);
        $user = $this->makeGlobalUser('periodo_movimientos.update');

        $response = $this->actingAs($user)
            ->putJson("/periodo-movimientos/{$movement->id}", [
                'period_id'               => $period->id,
                'church_id'               => $chain['church']->id,
                'period_movement_type_id' => $type->id,
                'status'                  => Status::IN_PROGRESS,
                'start_date'              => '2025-02-01',
                'end_date'                => '2025-03-31',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', Status::IN_PROGRESS);
    }

    public function test_destroy_soft_deletes_movement_and_returns_200(): void
    {
        ['chain' => $chain, 'period' => $period, 'type' => $type] = $this->createPeriodAndType();
        $movement = PeriodMovement::query()->create(['period_id' => $period->id, 'church_id' => $chain['church']->id, 'period_movement_type_id' => $type->id, 'status' => Status::PENDING, 'start_date' => '2025-02-01', 'end_date' => '2025-03-31']);
        $user = $this->makeGlobalUser('periodo_movimientos.delete');

        $this->actingAs($user)
            ->deleteJson("/periodo-movimientos/{$movement->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('period_movements', ['id' => $movement->id]);
    }
}
