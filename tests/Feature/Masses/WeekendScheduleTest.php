<?php

namespace Tests\Feature\Masses;

use App\Globals\Status;
use App\Models\Ecclesiastes\Chapel;
use App\Models\Ecclesiastes\Church;
use App\Models\Masses\Mass;
use App\Models\Masses\Weekend;
use App\Models\Masses\WeekendMassSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\ControllerTestHelpers;
use Tests\TestCase;

class WeekendScheduleTest extends TestCase
{
    use ControllerTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-14 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_church_user_sees_schedule_page_locked_to_own_church(): void
    {
        $chain = $this->createChain();
        $user = $this->makeChurchUser($chain['diocese'], $chain['deanery'], $chain['church'], 'weekends.create');
        $this->createSchedule($chain['church']);

        $this->actingAs($user)
            ->get('/fines-semana-misas/programar')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Masses/Weekends/Schedule')
                ->where('lockedChurchId', $chain['church']->id)
                ->where('selectedChurchId', $chain['church']->id)
                ->has('churches', 1)
                ->has('schedules', 1)
                ->where('schedules.0.starts_time', '18:00')
            );
    }

    public function test_church_user_saves_weekend_schedule(): void
    {
        $chain = $this->createChain();
        $user = $this->makeChurchUser($chain['diocese'], $chain['deanery'], $chain['church'], 'weekends.create');
        $removed = $this->createSchedule($chain['church']);

        $this->actingAs($user)
            ->put('/fines-semana-misas/programar', [
                'church_id' => $chain['church']->id,
                'masses' => [
                    ['day_of_week' => 'saturday', 'name' => ' misa vespertina ', 'starts_time' => '19:00', 'ends_time' => '20:00'],
                    ['day_of_week' => 'sunday', 'name' => 'misa dominical', 'starts_time' => '09:00', 'ends_time' => '10:00', 'chapel_id' => ''],
                ],
            ])
            ->assertRedirect('/fines-semana-misas');

        $this->assertSoftDeleted('weekend_mass_schedules', ['id' => $removed->id]);
        $this->assertDatabaseHas('weekend_mass_schedules', [
            'church_id' => $chain['church']->id,
            'day_of_week' => 'saturday',
            'name' => 'MISA VESPERTINA',
            'created_by' => $user->id,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('weekend_mass_schedules', [
            'church_id' => $chain['church']->id,
            'day_of_week' => 'sunday',
            'name' => 'MISA DOMINICAL',
            'chapel_id' => null,
            'deleted_at' => null,
        ]);
    }

    public function test_church_user_cannot_schedule_other_church_or_chapel_masses(): void
    {
        $chain = $this->createChain();
        $user = $this->makeChurchUser($chain['diocese'], $chain['deanery'], $chain['church'], 'weekends.create');
        $otherChurch = $this->createChurch($chain, 'Otra Parroquia');
        $chapel = $this->createChapel($chain);

        $this->actingAs($user)
            ->putJson('/fines-semana-misas/programar', [
                'church_id' => $otherChurch->id,
                'masses' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('church_id');

        $this->actingAs($user)
            ->putJson('/fines-semana-misas/programar', [
                'church_id' => $chain['church']->id,
                'masses' => [
                    ['day_of_week' => 'saturday', 'name' => 'Misa', 'starts_time' => '19:00', 'ends_time' => '20:00', 'chapel_id' => $chapel->id],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('masses.0.chapel_id');
    }

    public function test_schedule_validates_time_range_and_chapel_church(): void
    {
        $chain = $this->createChain();
        $user = $this->makeGlobalUser('weekends.create', 'weekends.scope.all');
        $otherChurch = $this->createChurch($chain, 'Otra Parroquia');
        $foreignChapel = Chapel::query()->create([
            'name' => 'Capilla Ajena',
            'community_id' => $chain['community']->id,
            'church_id' => $otherChurch->id,
            'status' => Status::ACTIVE,
        ]);

        $this->actingAs($user)
            ->putJson('/fines-semana-misas/programar', [
                'church_id' => $chain['church']->id,
                'masses' => [
                    ['day_of_week' => 'saturday', 'name' => 'Misa', 'starts_time' => '20:00', 'ends_time' => '19:00'],
                    ['day_of_week' => 'friday', 'name' => 'Misa', 'starts_time' => '19:00', 'ends_time' => '20:00'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['masses.0.ends_time', 'masses.1.day_of_week']);

        $this->actingAs($user)
            ->putJson('/fines-semana-misas/programar', [
                'church_id' => $chain['church']->id,
                'masses' => [
                    ['day_of_week' => 'sunday', 'name' => 'Misa', 'starts_time' => '09:00', 'ends_time' => '10:00', 'chapel_id' => $foreignChapel->id],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('masses.0.chapel_id');
    }

    public function test_preview_lists_remaining_month_weekends_without_existing_ones(): void
    {
        $chain = $this->createChain();
        $user = $this->makeChurchUser($chain['diocese'], $chain['deanery'], $chain['church'], 'weekends.create');
        $this->createSchedule($chain['church']);
        $this->createSchedule($chain['church'], ['day_of_week' => 'sunday', 'name' => 'MISA DOMINICAL', 'starts_time' => '09:00', 'ends_time' => '10:00']);
        $this->createWeekend($chain['church'], '2026-10-24');

        $this->actingAs($user)
            ->getJson('/fines-semana-misas/generar?church_id='.$chain['church']->id)
            ->assertOk()
            ->assertJsonPath('schedules_count', 2)
            ->assertJsonCount(2, 'weekends')
            ->assertJsonPath('weekends.0.starts_at', '2026-10-17 00:00:00')
            ->assertJsonPath('weekends.0.ends_at', '2026-10-18 23:59:00')
            ->assertJsonPath('weekends.0.name', 'FIN DE SEMANA 17-18 OCT 2026')
            ->assertJsonPath('weekends.0.masses.0.starts_at', '2026-10-17 18:00')
            ->assertJsonPath('weekends.0.masses.1.starts_at', '2026-10-18 09:00')
            ->assertJsonPath('weekends.1.starts_at', '2026-10-31 00:00:00')
            ->assertJsonPath('weekends.1.ends_at', '2026-11-01 23:59:00')
            ->assertJsonPath('weekends.1.name', 'FIN DE SEMANA 31 OCT - 01 NOV 2026');
    }

    public function test_preview_includes_weekend_in_progress_today(): void
    {
        Carbon::setTestNow('2026-10-18 08:00:00');
        $chain = $this->createChain();
        $user = $this->makeGlobalUser('weekends.create', 'weekends.scope.all');
        $this->createSchedule($chain['church']);

        $this->actingAs($user)
            ->getJson('/fines-semana-misas/generar?church_id='.$chain['church']->id)
            ->assertOk()
            ->assertJsonCount(3, 'weekends')
            ->assertJsonPath('weekends.0.starts_at', '2026-10-17 00:00:00');
    }

    public function test_generate_creates_upcoming_weekends_and_masses_once(): void
    {
        $chain = $this->createChain();
        $user = $this->makeChurchUser($chain['diocese'], $chain['deanery'], $chain['church'], 'weekends.create', 'masses.create');
        $this->createSchedule($chain['church']);
        $this->createSchedule($chain['church'], ['day_of_week' => 'sunday', 'name' => 'MISA DOMINICAL', 'starts_time' => '09:00', 'ends_time' => '10:00']);
        $this->createWeekend($chain['church'], '2026-10-24');

        $this->actingAs($user)
            ->post('/fines-semana-misas/generar', ['church_id' => $chain['church']->id])
            ->assertRedirect('/fines-semana-misas?tab=upcoming');

        $this->assertSame(3, Weekend::query()->count());
        $this->assertSame(2, Weekend::query()->where('status', Status::UPCOMING)->count());
        $this->assertDatabaseHas('weekends', [
            'church_id' => $chain['church']->id,
            'starts_at' => '2026-10-31 00:00:00',
            'ends_at' => '2026-11-01 23:59:00',
            'status' => Status::UPCOMING,
            'created_by' => $user->id,
        ]);
        $this->assertSame(4, Mass::query()->count());
        $this->assertSame(4, Mass::query()
            ->where('status', Status::UPCOMING)
            ->where('attendance_check_in_status', Status::UPCOMING)
            ->where('attendance_check_out_status', Status::UPCOMING)
            ->count());
        $this->assertDatabaseHas('masses', [
            'church_id' => $chain['church']->id,
            'name' => 'MISA DOMINICAL',
            'starts_at' => '2026-11-01 09:00:00',
            'ends_at' => '2026-11-01 10:00:00',
        ]);

        $this->actingAs($user)
            ->post('/fines-semana-misas/generar', ['church_id' => $chain['church']->id])
            ->assertRedirect('/fines-semana-misas')
            ->assertSessionHas('error');

        $this->assertSame(3, Weekend::query()->count());
        $this->assertSame(4, Mass::query()->count());
    }

    public function test_church_user_with_full_scope_is_locked_to_own_church(): void
    {
        $chain = $this->createChain();
        $other = $this->createChurch($chain, 'Otra Parroquia');
        $this->createSchedule($other);
        $user = $this->makeChurchUser(
            $chain['diocese'],
            $chain['deanery'],
            $chain['church'],
            'weekends.read',
            'weekends.create',
            'weekends.scope.all',
            'masses.create',
            'masses.scope.all',
        );

        $this->actingAs($user)
            ->get('/fines-semana-misas')
            ->assertInertia(fn ($page) => $page
                ->where('lockedChurchId', $chain['church']->id)
                ->has('churches', 1)
            );

        $this->actingAs($user)
            ->putJson('/fines-semana-misas/programar', [
                'church_id' => $other->id,
                'masses' => [['day_of_week' => 'saturday', 'name' => 'MISA', 'starts_time' => '18:00', 'ends_time' => '19:00']],
            ])
            ->assertUnprocessable();

        $this->actingAs($user)
            ->getJson('/fines-semana-misas/generar?church_id='.$other->id)
            ->assertUnprocessable();

        $this->actingAs($user)
            ->postJson('/fines-semana-misas/generar', ['church_id' => $other->id])
            ->assertUnprocessable();

        $this->assertSame(0, Weekend::query()->count());
        $this->assertSame(1, WeekendMassSchedule::query()->count());
    }

    public function test_generate_requires_mass_create_permission(): void
    {
        $chain = $this->createChain();
        $user = $this->makeChurchUser($chain['diocese'], $chain['deanery'], $chain['church'], 'weekends.create');
        $this->createSchedule($chain['church']);

        $this->actingAs($user)
            ->post('/fines-semana-misas/generar', ['church_id' => $chain['church']->id])
            ->assertForbidden();

        $this->assertSame(0, Weekend::query()->count());
    }

    public function test_chapel_user_cannot_access_schedule(): void
    {
        $chain = $this->createChain();
        $chapel = $this->createChapel($chain);
        $user = User::factory()->create([
            'diocese_id' => $chain['diocese']->id,
            'deanery_id' => $chain['deanery']->id,
            'church_id' => $chain['church']->id,
            'chapel_id' => $chapel->id,
        ]);
        $this->givePermissions($user, 'weekends.create', 'masses.create');

        $this->actingAs($user)->get('/fines-semana-misas/programar')->assertForbidden();
        $this->actingAs($user)
            ->post('/fines-semana-misas/generar', ['church_id' => $chain['church']->id])
            ->assertForbidden();
    }

    public function test_index_exposes_locked_church_and_scheduled_churches(): void
    {
        $chain = $this->createChain();
        $user = $this->makeChurchUser($chain['diocese'], $chain['deanery'], $chain['church'], 'weekends.read', 'weekends.create');
        $this->createSchedule($chain['church']);

        $this->actingAs($user)
            ->get('/fines-semana-misas')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Masses/Weekends/Index')
                ->where('lockedChurchId', $chain['church']->id)
                ->where('scheduledChurchIds', [$chain['church']->id])
            );
    }

    private function createSchedule(Church $church, array $overrides = []): WeekendMassSchedule
    {
        return WeekendMassSchedule::query()->create(array_merge([
            'church_id' => $church->id,
            'day_of_week' => 'saturday',
            'name' => 'MISA SABATINA',
            'starts_time' => '18:00',
            'ends_time' => '19:00',
        ], $overrides));
    }

    private function createWeekend(Church $church, string $saturday): Weekend
    {
        return Weekend::query()->create([
            'church_id' => $church->id,
            'name' => 'EXISTENTE',
            'starts_at' => "{$saturday} 00:00:00",
            'ends_at' => Carbon::parse($saturday)->addDay()->format('Y-m-d').' 23:59:00',
            'status' => Status::IN_PROGRESS,
        ]);
    }

    private function createChurch(array $chain, string $name): Church
    {
        return Church::query()->create([
            'name' => $name,
            'deanery_id' => $chain['deanery']->id,
            'municipality_id' => $chain['municipality']->id,
            'status' => Status::ACTIVE,
        ]);
    }

    private function createChapel(array $chain): Chapel
    {
        return Chapel::query()->create([
            'name' => 'Capilla Test',
            'community_id' => $chain['community']->id,
            'church_id' => $chain['church']->id,
            'status' => Status::ACTIVE,
        ]);
    }
}
