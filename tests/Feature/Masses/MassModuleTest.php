<?php

namespace Tests\Feature\Masses;

use App\Globals\BloodType;
use App\Globals\Sex;
use App\Globals\Status;
use App\Models\Catechism\Child;
use App\Models\Ecclesiastes\Chapel;
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
            ->assertRedirect('/misas');

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

    private function createWeekend(array $chain): Weekend
    {
        return Weekend::query()->create([
            'church_id' => $chain['church']->id,
            'name' => 'FIN DE SEMANA TEST',
            'starts_at' => '2026-07-04 00:00:00',
            'ends_at' => '2026-07-05 23:59:00',
            'status' => Status::IN_PROGRESS,
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
