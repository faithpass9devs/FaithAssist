<?php

namespace Tests\Feature\Masses;

use App\Globals\BloodType;
use App\Globals\Sex;
use App\Globals\Status;
use App\Models\Catechism\Child;
use App\Models\Ecclesiastes\Chapel;
use App\Models\Ecclesiastes\Church;
use App\Models\Masses\Mass;
use App\Models\Masses\MassAttendance;
use App\Models\Masses\Weekend;
use App\Models\Regions\Community;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\Feature\Concerns\ControllerTestHelpers;
use Tests\TestCase;

class MassModuleTest extends TestCase
{
    use ControllerTestHelpers, RefreshDatabase;

    public function test_church_user_creates_weekend_for_own_church(): void
    {
        $chain = $this->createChain();
        $user = $this->makeChurchUser($chain['diocese'], $chain['deanery'], $chain['church'], 'weekends.create');

        $this->actingAs($user)
            ->post('/fines-semana-misas', [
                'church_id' => $chain['church']->id,
                'name' => 'Fin de semana test',
                'starts_at' => '2026-07-04',
                'status' => Status::UPCOMING,
            ])
            ->assertRedirect('/fines-semana-misas');

        $this->assertDatabaseHas('weekends', [
            'church_id' => $chain['church']->id,
            'starts_at' => '2026-07-04 00:00:00',
            'ends_at' => '2026-07-05 23:59:00',
            'name' => 'FIN DE SEMANA TEST',
        ]);
    }

    public function test_chapel_user_cannot_create_weekends(): void
    {
        $chain = $this->createChain();
        $chapel = $this->createChapel($chain);
        $user = $this->makeChapelUser($chain, $chapel, 'weekends.create');

        $this->actingAs($user)
            ->postJson('/fines-semana-misas', [
                'church_id' => $chain['church']->id,
                'starts_at' => '2026-07-04',
                'status' => Status::UPCOMING,
            ])
            ->assertForbidden();
    }

    public function test_weekend_must_be_saturday_to_sunday(): void
    {
        $chain = $this->createChain();
        $user = $this->makeChurchUser($chain['diocese'], $chain['deanery'], $chain['church'], 'weekends.create');

        $this->actingAs($user)
            ->postJson('/fines-semana-misas', [
                'church_id' => $chain['church']->id,
                'starts_at' => '2026-07-03',
                'status' => Status::UPCOMING,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['starts_at', 'ends_at']);
    }

    public function test_weekend_index_defaults_to_current_and_tabs_filter_other_statuses(): void
    {
        $chain = $this->createChain();
        $user = $this->makeGlobalUser('weekends.read', 'weekends.scope.all');

        $this->createWeekend($chain, [
            'name' => 'FIN DE SEMANA PROXIMO',
            'starts_at' => '2026-07-04 00:00:00',
            'status' => Status::UPCOMING,
        ]);
        $this->createWeekend($chain, [
            'name' => 'FIN DE SEMANA EN CURSO 1',
            'starts_at' => '2026-07-11 00:00:00',
            'status' => Status::IN_PROGRESS,
        ]);
        $this->createWeekend($chain, [
            'name' => 'FIN DE SEMANA EN CURSO 2',
            'starts_at' => '2026-07-18 00:00:00',
            'status' => Status::IN_PROGRESS,
        ]);
        $this->createWeekend($chain, [
            'name' => 'FIN DE SEMANA TERMINADO',
            'starts_at' => '2026-07-25 00:00:00',
            'status' => Status::COMPLETED,
        ]);
        $this->createWeekend($chain, [
            'name' => 'FIN DE SEMANA ANTERIOR',
            'starts_at' => '2026-06-27 00:00:00',
            'ends_at' => '2026-06-28 23:59:00',
            'status' => Status::COMPLETED,
        ]);

        $this->actingAs($user)
            ->get('/fines-semana-misas')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Masses/Weekends/Index')
                ->where('tab', 'current')
                ->where('weekends.total', 2)
                ->where('weekends.data.0.status', Status::IN_PROGRESS)
            );

        $this->actingAs($user)
            ->get('/fines-semana-misas?tab=history')
            ->assertInertia(fn ($page) => $page
                ->where('tab', 'history')
                ->where('weekends.total', 2)
                ->where('weekends.data.0.status', Status::COMPLETED)
                ->where('weekends.data.0.name', 'FIN DE SEMANA TERMINADO')
                ->where('weekends.data.1.name', 'FIN DE SEMANA ANTERIOR')
            );

        $this->actingAs($user)
            ->get('/fines-semana-misas?tab=upcoming')
            ->assertInertia(fn ($page) => $page
                ->where('tab', 'upcoming')
                ->where('weekends.total', 1)
                ->where('weekends.data.0.status', Status::UPCOMING)
            );
    }

    public function test_multiple_upcoming_weekends_can_be_created_per_church(): void
    {
        $chain = $this->createChain();
        $this->createWeekend($chain, ['status' => Status::UPCOMING]);
        $user = $this->makeChurchUser($chain['diocese'], $chain['deanery'], $chain['church'], 'weekends.create');

        $this->actingAs($user)
            ->post('/fines-semana-misas', [
                'church_id' => $chain['church']->id,
                'name' => 'OTRO FIN DE SEMANA',
                'starts_at' => '2026-07-11',
                'status' => Status::UPCOMING,
            ])
            ->assertRedirect('/fines-semana-misas');

        $this->assertDatabaseCount('weekends', 2);
    }

    public function test_completing_current_weekend_starts_the_next_weekend(): void
    {
        $chain = $this->createChain();
        $current = $this->createWeekend($chain);
        $later = $this->createWeekend($chain, [
            'name' => 'POSTERIOR FIN DE SEMANA',
            'starts_at' => '2026-07-18 00:00:00',
            'ends_at' => '2026-07-19 23:59:00',
            'status' => Status::UPCOMING,
        ]);
        $next = $this->createWeekend($chain, [
            'name' => 'SIGUIENTE FIN DE SEMANA',
            'starts_at' => '2026-07-11 00:00:00',
            'ends_at' => '2026-07-12 23:59:00',
            'status' => Status::UPCOMING,
        ]);
        $user = $this->makeGlobalUser('weekends.update', 'weekends.scope.all');

        $this->actingAs($user)
            ->put("/fines-semana-misas/{$current->id}", [
                'church_id' => $chain['church']->id,
                'name' => $current->name,
                'starts_at' => '2026-07-04',
                'status' => Status::COMPLETED,
            ])
            ->assertRedirect('/fines-semana-misas');

        $this->assertDatabaseHas('weekends', [
            'id' => $current->id,
            'status' => Status::COMPLETED,
        ]);
        $this->assertDatabaseHas('weekends', [
            'id' => $next->id,
            'status' => Status::IN_PROGRESS,
        ]);
        $this->assertDatabaseHas('weekends', [
            'id' => $later->id,
            'status' => Status::UPCOMING,
        ]);
    }

    public function test_completing_last_weekend_does_nothing_else(): void
    {
        $chain = $this->createChain();
        $current = $this->createWeekend($chain);
        $previous = $this->createWeekend($chain, [
            'name' => 'ANTERIOR',
            'starts_at' => '2026-06-27 00:00:00',
            'ends_at' => '2026-06-28 23:59:00',
            'status' => Status::UPCOMING,
        ]);
        $user = $this->makeGlobalUser('weekends.update', 'weekends.scope.all');

        $this->actingAs($user)
            ->put("/fines-semana-misas/{$current->id}", [
                'church_id' => $chain['church']->id,
                'name' => $current->name,
                'starts_at' => '2026-07-04',
                'status' => Status::COMPLETED,
            ])
            ->assertRedirect('/fines-semana-misas');

        $this->assertDatabaseHas('weekends', ['id' => $current->id, 'status' => Status::COMPLETED]);
        $this->assertDatabaseHas('weekends', ['id' => $previous->id, 'status' => Status::UPCOMING]);
        $this->assertSame(0, Weekend::query()->where('status', Status::IN_PROGRESS)->count());
    }

    public function test_church_user_with_full_scope_cannot_manage_other_church_weekends(): void
    {
        $chain = $this->createChain();
        $otherChurch = Church::query()->create([
            'name' => 'Otra Parroquia',
            'deanery_id' => $chain['deanery']->id,
            'municipality_id' => $chain['municipality']->id,
            'status' => Status::ACTIVE,
        ]);
        $otherWeekend = $this->createWeekend($chain, ['church_id' => $otherChurch->id]);
        $user = $this->makeChurchUser(
            $chain['diocese'],
            $chain['deanery'],
            $chain['church'],
            'weekends.create',
            'weekends.update',
            'weekends.scope.all',
        );

        $this->actingAs($user)
            ->postJson('/fines-semana-misas', [
                'church_id' => $otherChurch->id,
                'starts_at' => '2026-07-11',
                'status' => Status::UPCOMING,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('church_id');

        $this->actingAs($user)
            ->get("/fines-semana-misas/{$otherWeekend->id}/edit")
            ->assertForbidden();
    }

    public function test_deleting_weekend_soft_deletes_its_masses_and_allows_recreating_it(): void
    {
        $chain = $this->createChain();
        $weekend = $this->createWeekend($chain, ['status' => Status::UPCOMING]);
        $mass = Mass::query()->create([
            'weekend_id' => $weekend->id,
            'church_id' => $chain['church']->id,
            'name' => 'MISA DOMINICAL',
            'starts_at' => '2026-07-05 10:00',
            'ends_at' => '2026-07-05 11:00',
            'status' => Status::UPCOMING,
            'attendance_check_in_status' => Status::UPCOMING,
            'attendance_check_out_status' => Status::UPCOMING,
        ]);
        $user = $this->makeGlobalUser('weekends.create', 'weekends.delete', 'weekends.scope.all');
        $payload = [
            'church_id' => $chain['church']->id,
            'starts_at' => '2026-07-04',
            'ends_at' => '2026-07-05 23:59',
            'status' => Status::UPCOMING,
        ];

        $this->actingAs($user)
            ->postJson('/fines-semana-misas', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_at');

        $this->actingAs($user)
            ->delete("/fines-semana-misas/{$weekend->id}")
            ->assertRedirect('/fines-semana-misas');

        $this->assertSoftDeleted('weekends', ['id' => $weekend->id, 'deleted_by' => $user->id]);
        $this->assertSoftDeleted('masses', ['id' => $mass->id, 'deleted_by' => $user->id]);

        $this->actingAs($user)
            ->post('/fines-semana-misas', $payload)
            ->assertRedirect('/fines-semana-misas');

        $this->assertSame(1, Weekend::query()->where('church_id', $chain['church']->id)->count());
        $this->assertSame(2, Weekend::withTrashed()->where('church_id', $chain['church']->id)->count());
    }

    public function test_chapel_user_creates_mass_for_own_chapel(): void
    {
        $chain = $this->createChain();
        $chapel = $this->createChapel($chain);
        $weekend = $this->createWeekend($chain);
        $user = $this->makeChapelUser($chain, $chapel, 'masses.create');

        $this->actingAs($user)
            ->post('/misas', [
                'weekend_id' => $weekend->id,
                'church_id' => $chain['church']->id,
                'chapel_id' => $chapel->id,
                'name' => 'Misa de capilla',
                'starts_at' => '2026-07-04 18:00',
                'ends_at' => '2026-07-04 19:00',
                'status' => Status::UPCOMING,
                'attendance_check_in_status' => Status::UPCOMING,
                'attendance_check_out_status' => Status::UPCOMING,
            ])
            ->assertRedirect("/misas?weekend_id={$weekend->id}");

        $this->assertDatabaseHas('masses', [
            'church_id' => $chain['church']->id,
            'chapel_id' => $chapel->id,
            'name' => 'MISA DE CAPILLA',
        ]);
    }

    public function test_mass_time_range_must_be_inside_weekend(): void
    {
        $chain = $this->createChain();
        $weekend = $this->createWeekend($chain);
        $user = $this->makeChurchUser($chain['diocese'], $chain['deanery'], $chain['church'], 'masses.create');

        $this->actingAs($user)
            ->post('/misas', [
                'weekend_id' => $weekend->id,
                'church_id' => $chain['church']->id,
                'chapel_id' => null,
                'name' => 'Misa fuera de rango',
                'starts_at' => '2026-07-06 10:00',
                'ends_at' => '2026-07-06 11:00',
                'status' => Status::UPCOMING,
                'attendance_check_in_status' => Status::UPCOMING,
                'attendance_check_out_status' => Status::UPCOMING,
            ])
            ->assertSessionHasErrors(['starts_at', 'ends_at']);
    }

    public function test_masses_form_routes_render(): void
    {
        $chain = $this->createChain();
        $weekend = $this->createWeekend($chain);
        $mass = Mass::query()->create([
            'weekend_id' => $weekend->id,
            'church_id' => $chain['church']->id,
            'name' => 'MISA DOMINICAL',
            'starts_at' => '2026-07-05 10:00',
            'ends_at' => '2026-07-05 11:00',
            'status' => Status::UPCOMING,
            'attendance_check_in_status' => Status::UPCOMING,
            'attendance_check_out_status' => Status::UPCOMING,
        ]);
        $user = $this->makeGlobalUser('weekends.create', 'weekends.update', 'masses.create', 'masses.update');

        $this->actingAs($user)
            ->get('/fines-semana-misas/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Masses/Weekends/Form'));

        $this->actingAs($user)
            ->get("/misas/{$mass->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Masses/Masses/Form'));

        $this->actingAs($user)
            ->get("/misas/create?weekend_id={$weekend->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Masses/Masses/Form')
                ->where('weekendId', $weekend->id)
            );
    }

    public function test_masses_index_lists_only_selected_weekend_masses(): void
    {
        $chain = $this->createChain();
        $weekend = $this->createWeekend($chain);
        $otherWeekend = $this->createWeekend($chain, [
            'starts_at' => '2026-07-11 00:00:00',
            'ends_at' => '2026-07-12 23:59:00',
            'status' => Status::UPCOMING,
        ]);
        foreach ([[$weekend, '2026-07-05', 'MISA SELECCIONADA'], [$otherWeekend, '2026-07-12', 'MISA OTRA']] as [$owner, $day, $name]) {
            Mass::query()->create([
                'weekend_id' => $owner->id,
                'church_id' => $chain['church']->id,
                'name' => $name,
                'starts_at' => "{$day} 10:00",
                'ends_at' => "{$day} 11:00",
                'status' => Status::UPCOMING,
                'attendance_check_in_status' => Status::UPCOMING,
                'attendance_check_out_status' => Status::UPCOMING,
            ]);
        }
        $user = $this->makeGlobalUser('masses.read');

        $this->actingAs($user)
            ->get("/misas?weekend_id={$weekend->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Masses/Masses/Index')
                ->where('weekend.id', $weekend->id)
                ->has('masses.data', 1)
                ->where('masses.data.0.name', 'MISA SELECCIONADA')
                ->missing('weekends')
            );

        $this->actingAs($user)
            ->get('/misas')
            ->assertRedirect('/fines-semana-misas');
    }

    public function test_masses_index_rejects_weekend_outside_scope(): void
    {
        $chain = $this->createChain();
        $otherChurch = Church::query()->create([
            'name' => 'Otra Parroquia',
            'deanery_id' => $chain['deanery']->id,
            'municipality_id' => $chain['municipality']->id,
            'status' => Status::ACTIVE,
        ]);
        $foreignWeekend = $this->createWeekend($chain, ['church_id' => $otherChurch->id]);
        $user = $this->makeChurchUser($chain['diocese'], $chain['deanery'], $chain['church'], 'masses.read');

        $this->actingAs($user)
            ->get("/misas?weekend_id={$foreignWeekend->id}")
            ->assertRedirect('/fines-semana-misas');
    }

    public function test_mass_attendance_requires_check_in_and_check_out_to_be_valid(): void
    {
        $chain = $this->createChain();
        $weekend = $this->createWeekend($chain);
        $mass = Mass::query()->create([
            'weekend_id' => $weekend->id,
            'church_id' => $chain['church']->id,
            'name' => 'MISA DOMINICAL',
            'starts_at' => '2026-07-05 10:00',
            'ends_at' => '2026-07-05 11:00',
            'status' => Status::IN_PROGRESS,
            'attendance_check_in_status' => Status::IN_PROGRESS,
            'attendance_check_out_status' => Status::IN_PROGRESS,
        ]);
        $child = Child::query()->create($this->childRow($chain));
        $user = $this->makeGlobalUser('mass_attendance.scan');

        $this->actingAs($user)
            ->postJson("/misas/{$mass->id}/asistencias/scan", [
                'child_code' => $child->code,
                'action' => Status::CHECK_IN,
            ])
            ->assertOk()
            ->assertJsonPath('data.valid', false);

        $this->actingAs($user)
            ->postJson("/misas/{$mass->id}/asistencias/scan", [
                'child_code' => $child->code,
                'action' => Status::CHECK_OUT,
            ])
            ->assertOk()
            ->assertJsonPath('data.valid', true);

        $this->assertDatabaseHas('mass_attendance', [
            'mass_id' => $mass->id,
            'child_id' => $child->id,
            'status' => Status::CHECK_OUT,
            'church_id' => $chain['church']->id,
        ]);

        $attendance = MassAttendance::query()->where('mass_id', $mass->id)->where('child_id', $child->id)->firstOrFail();
        $attendanceEvents = Activity::query()
            ->where('subject_type', MassAttendance::class)
            ->where('subject_id', $attendance->id)
            ->get();

        $this->assertNotEmpty($attendanceEvents);
        $this->assertTrue($attendanceEvents->every(fn (Activity $event): bool => filled(data_get($event->properties, 'session_id'))));
    }

    public function test_mass_attendance_rejects_child_from_other_church(): void
    {
        $chain = $this->createChain();
        $otherChain = $this->createChain();
        $weekend = $this->createWeekend($chain);
        $mass = Mass::query()->create([
            'weekend_id' => $weekend->id,
            'church_id' => $chain['church']->id,
            'name' => 'MISA DOMINICAL',
            'starts_at' => '2026-07-05 10:00',
            'ends_at' => '2026-07-05 11:00',
            'status' => Status::IN_PROGRESS,
            'attendance_check_in_status' => Status::IN_PROGRESS,
            'attendance_check_out_status' => Status::IN_PROGRESS,
        ]);
        $child = Child::query()->create($this->childRow($otherChain, ['code' => 'OTHER-CHILD']));
        $user = $this->makeGlobalUser('mass_attendance.scan');

        $this->actingAs($user)
            ->postJson("/misas/{$mass->id}/asistencias/scan", [
                'child_code' => $child->code,
                'action' => Status::CHECK_IN,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['child_code']);
    }

    public function test_mass_attendance_allows_child_from_another_community(): void
    {
        $chain = $this->createChain();
        $otherCommunity = Community::query()->create([
            'name' => 'Comunidad Vecina',
            'municipality_id' => $chain['municipality']->id,
            'status' => Status::ACTIVE,
        ]);
        $chapel = Chapel::query()->create([
            'name' => 'Capilla Vecina',
            'community_id' => $otherCommunity->id,
            'church_id' => $chain['church']->id,
            'status' => Status::ACTIVE,
        ]);
        $weekend = $this->createWeekend($chain);
        $mass = Mass::query()->create([
            'weekend_id' => $weekend->id,
            'church_id' => $chain['church']->id,
            'chapel_id' => $chapel->id,
            'name' => 'MISA DE CAPILLA VECINA',
            'starts_at' => '2026-07-05 18:00',
            'ends_at' => '2026-07-05 19:00',
            'status' => Status::IN_PROGRESS,
            'attendance_check_in_status' => Status::IN_PROGRESS,
            'attendance_check_out_status' => Status::IN_PROGRESS,
        ]);
        $child = Child::query()->create($this->childRow($chain));
        $user = $this->makeGlobalUser('mass_attendance.scan');

        $this->actingAs($user)
            ->postJson("/misas/{$mass->id}/asistencias/scan", [
                'child_code' => $child->code,
                'action' => Status::CHECK_IN,
            ])
            ->assertOk()
            ->assertJsonPath('data.valid', false);
    }

    public function test_scan_permission_can_capture_without_attendance_read_or_mass_show(): void
    {
        $chain = $this->createChain();
        $weekend = $this->createWeekend($chain);
        $mass = Mass::query()->create([
            'weekend_id' => $weekend->id,
            'church_id' => $chain['church']->id,
            'name' => 'MISA DOMINICAL',
            'starts_at' => '2026-07-05 10:00',
            'ends_at' => '2026-07-05 11:00',
            'status' => Status::IN_PROGRESS,
            'attendance_check_in_status' => Status::IN_PROGRESS,
            'attendance_check_out_status' => Status::IN_PROGRESS,
        ]);
        $child = Child::query()->create($this->childRow($chain));
        $user = $this->makeGlobalUser('mass_attendance.scan');

        $this->actingAs($user)
            ->get("/misas/{$mass->id}/asistencias")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Masses/Attendance/Scan')
                ->where('canScan', true)
                ->where('attendances.total', 0));

        $this->actingAs($user)
            ->postJson("/misas/{$mass->id}/asistencias/scan", [
                'child_code' => $child->code,
                'action' => Status::CHECK_IN,
            ])
            ->assertOk()
            ->assertJsonPath('data.valid', false);
    }

    public function test_attendance_create_permission_does_not_allow_qr_capture(): void
    {
        $chain = $this->createChain();
        $weekend = $this->createWeekend($chain);
        $mass = Mass::query()->create([
            'weekend_id' => $weekend->id,
            'church_id' => $chain['church']->id,
            'name' => 'MISA DOMINICAL',
            'starts_at' => '2026-07-05 10:00',
            'ends_at' => '2026-07-05 11:00',
            'status' => Status::IN_PROGRESS,
            'attendance_check_in_status' => Status::IN_PROGRESS,
            'attendance_check_out_status' => Status::IN_PROGRESS,
        ]);
        $child = Child::query()->create($this->childRow($chain));
        $user = $this->makeGlobalUser('mass_attendance.create');

        $this->actingAs($user)
            ->postJson("/misas/{$mass->id}/asistencias/scan", [
                'child_code' => $child->code,
                'action' => Status::CHECK_IN,
            ])
            ->assertForbidden();
    }

    public function test_check_in_capture_rejected_when_check_in_capture_is_completed(): void
    {
        $chain = $this->createChain();
        $weekend = $this->createWeekend($chain);
        $mass = Mass::query()->create([
            'weekend_id' => $weekend->id,
            'church_id' => $chain['church']->id,
            'name' => 'MISA DOMINICAL',
            'starts_at' => '2026-07-05 10:00',
            'ends_at' => '2026-07-05 11:00',
            'status' => Status::IN_PROGRESS,
            'attendance_check_in_status' => Status::COMPLETED,
            'attendance_check_out_status' => Status::IN_PROGRESS,
        ]);
        $child = Child::query()->create($this->childRow($chain));
        $user = $this->makeGlobalUser('mass_attendance.scan');

        $this->actingAs($user)
            ->postJson("/misas/{$mass->id}/asistencias/scan", [
                'child_code' => $child->code,
                'action' => Status::CHECK_IN,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mass']);
    }

    public function test_check_in_capture_completed_still_allows_check_out_capture(): void
    {
        $chain = $this->createChain();
        $weekend = $this->createWeekend($chain);
        $mass = Mass::query()->create([
            'weekend_id' => $weekend->id,
            'church_id' => $chain['church']->id,
            'name' => 'MISA DOMINICAL',
            'starts_at' => '2026-07-05 10:00',
            'ends_at' => '2026-07-05 11:00',
            'status' => Status::IN_PROGRESS,
            'attendance_check_in_status' => Status::COMPLETED,
            'attendance_check_out_status' => Status::IN_PROGRESS,
        ]);
        $child = Child::query()->create($this->childRow($chain));
        $user = $this->makeGlobalUser('mass_attendance.scan');

        MassAttendance::query()->create([
            'mass_id' => $mass->id,
            'child_id' => $child->id,
            'child_code' => $child->code,
            'church_id' => $chain['church']->id,
            'check_in_at' => now(),
            'check_in_by' => $user->id,
            'status' => Status::CHECK_IN,
        ]);

        $this->actingAs($user)
            ->postJson("/misas/{$mass->id}/asistencias/scan", [
                'child_code' => $child->code,
                'action' => Status::CHECK_OUT,
            ])
            ->assertOk()
            ->assertJsonPath('data.valid', true);
    }

    public function test_check_out_capture_rejected_when_check_out_capture_not_in_progress(): void
    {
        $chain = $this->createChain();
        $weekend = $this->createWeekend($chain);
        $mass = Mass::query()->create([
            'weekend_id' => $weekend->id,
            'church_id' => $chain['church']->id,
            'name' => 'MISA DOMINICAL',
            'starts_at' => '2026-07-05 10:00',
            'ends_at' => '2026-07-05 11:00',
            'status' => Status::IN_PROGRESS,
            'attendance_check_in_status' => Status::IN_PROGRESS,
            'attendance_check_out_status' => Status::UPCOMING,
        ]);
        $child = Child::query()->create($this->childRow($chain));
        $user = $this->makeGlobalUser('mass_attendance.scan');

        $this->actingAs($user)
            ->postJson("/misas/{$mass->id}/asistencias/scan", [
                'child_code' => $child->code,
                'action' => Status::CHECK_OUT,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mass']);
    }

    public function test_capture_status_endpoint_requires_manage_permission(): void
    {
        $chain = $this->createChain();
        $weekend = $this->createWeekend($chain);
        $mass = Mass::query()->create([
            'weekend_id' => $weekend->id,
            'church_id' => $chain['church']->id,
            'name' => 'MISA DOMINICAL',
            'starts_at' => '2026-07-05 10:00',
            'ends_at' => '2026-07-05 11:00',
            'status' => Status::IN_PROGRESS,
            'attendance_check_in_status' => Status::IN_PROGRESS,
            'attendance_check_out_status' => Status::IN_PROGRESS,
        ]);
        $capturista = $this->makeGlobalUser('mass_attendance.scan');

        $this->actingAs($capturista)
            ->postJson("/misas/{$mass->id}/asistencias/status", [
                'capture' => Status::CHECK_IN,
                'status' => Status::COMPLETED,
            ])
            ->assertForbidden();
    }

    public function test_capture_status_endpoint_completes_and_reopens_capture(): void
    {
        $chain = $this->createChain();
        $weekend = $this->createWeekend($chain);
        $mass = Mass::query()->create([
            'weekend_id' => $weekend->id,
            'church_id' => $chain['church']->id,
            'name' => 'MISA DOMINICAL',
            'starts_at' => '2026-07-05 10:00',
            'ends_at' => '2026-07-05 11:00',
            'status' => Status::IN_PROGRESS,
            'attendance_check_in_status' => Status::IN_PROGRESS,
            'attendance_check_out_status' => Status::IN_PROGRESS,
        ]);
        $coordinador = $this->makeGlobalUser('mass_attendance.manage');

        $this->actingAs($coordinador)
            ->postJson("/misas/{$mass->id}/asistencias/status", [
                'capture' => Status::CHECK_IN,
                'status' => Status::COMPLETED,
            ])
            ->assertOk()
            ->assertJsonPath('data.attendance_check_in_status', Status::COMPLETED);

        $this->assertDatabaseHas('masses', [
            'id' => $mass->id,
            'attendance_check_in_status' => Status::COMPLETED,
            'attendance_check_out_status' => Status::IN_PROGRESS,
        ]);

        $this->actingAs($coordinador)
            ->postJson("/misas/{$mass->id}/asistencias/status", [
                'capture' => Status::CHECK_IN,
                'status' => Status::IN_PROGRESS,
            ])
            ->assertOk()
            ->assertJsonPath('data.attendance_check_in_status', Status::IN_PROGRESS);
    }

    private function createWeekend(array $chain, array $overrides = []): Weekend
    {
        return Weekend::query()->create(array_merge([
            'church_id' => $chain['church']->id,
            'name' => 'FIN DE SEMANA TEST',
            'starts_at' => '2026-07-04 00:00:00',
            'ends_at' => '2026-07-05 23:59:00',
            'status' => Status::IN_PROGRESS,
        ], $overrides));
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

    private function makeChapelUser(array $chain, Chapel $chapel, string ...$permissions): User
    {
        $user = User::factory()->create([
            'diocese_id' => $chain['diocese']->id,
            'deanery_id' => $chain['deanery']->id,
            'church_id' => $chain['church']->id,
            'chapel_id' => $chapel->id,
        ]);

        $this->givePermissions($user, ...$permissions);

        return $user;
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
