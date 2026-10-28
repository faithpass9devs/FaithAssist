<?php

namespace Tests\Feature\Masses;

use App\Globals\BloodType;
use App\Globals\Sex;
use App\Globals\Status;
use App\Models\Catechism\Child;
use App\Models\Masses\Event;
use App\Models\Operation\Staff;
use App\Models\Operation\StaffType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\ControllerTestHelpers;
use Tests\TestCase;

class EventModuleTest extends TestCase
{
    use ControllerTestHelpers, RefreshDatabase;

    public function test_church_user_creates_event_for_own_church(): void
    {
        $chain = $this->createChain();
        $user = $this->makeChurchUser($chain['diocese'], $chain['deanery'], $chain['church'], 'eventos.create');

        $response = $this->actingAs($user)->post('/eventos', [
            'church_id' => $chain['church']->id,
            'name' => 'EVENTO TEST',
            'starts_at' => '2026-11-01',
            'status' => Status::UPCOMING,
        ]);

        $response->assertRedirect('/eventos');

        $this->assertDatabaseHas('events', [
            'church_id' => $chain['church']->id,
            'name' => 'EVENTO TEST',
            'status' => Status::UPCOMING,
        ]);
    }

    public function test_event_attendance_requires_check_in_and_check_out_to_be_valid_for_child_and_staff(): void
    {
        $chain = $this->createChain();
        $event = $this->createEvent($chain, [
            'status' => Status::IN_PROGRESS,
            'attendance_check_in_status' => Status::IN_PROGRESS,
            'attendance_check_out_status' => Status::IN_PROGRESS,
        ]);
        $child = Child::query()->create($this->childRow($chain));
        $staff = $this->createStaff($chain);
        $user = $this->makeGlobalUser('event_attendance.scan');

        $this->actingAs($user)
            ->postJson("/eventos/{$event->id}/asistencias/scan", [
                'attendee_code' => $child->code,
                'action' => Status::CHECK_IN,
            ])
            ->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.attendee_type', Child::class);

        $this->actingAs($user)
            ->postJson("/eventos/{$event->id}/asistencias/scan", [
                'attendee_code' => $child->code,
                'action' => Status::CHECK_OUT,
            ])
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.attendee_type', Child::class);

        $this->actingAs($user)
            ->postJson("/eventos/{$event->id}/asistencias/scan", [
                'attendee_code' => $staff->code,
                'action' => Status::CHECK_IN,
            ])
            ->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.attendee_type', Staff::class);

        $this->actingAs($user)
            ->postJson("/eventos/{$event->id}/asistencias/scan", [
                'attendee_code' => $staff->code,
                'action' => Status::CHECK_OUT,
            ])
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.attendee_type', Staff::class);

        $this->assertDatabaseHas('event_attendance', [
            'event_id' => $event->id,
            'attendee_type' => Child::class,
            'attendee_id' => $child->id,
            'status' => Status::CHECK_OUT,
        ]);

        $this->assertDatabaseHas('event_attendance', [
            'event_id' => $event->id,
            'attendee_type' => Staff::class,
            'attendee_id' => $staff->id,
            'status' => Status::CHECK_OUT,
        ]);
    }

    public function test_event_attendance_rejects_attendee_from_other_church(): void
    {
        $chain = $this->createChain();
        $other = $this->createChain();
        $event = $this->createEvent($chain, [
            'status' => Status::IN_PROGRESS,
            'attendance_check_in_status' => Status::IN_PROGRESS,
            'attendance_check_out_status' => Status::IN_PROGRESS,
        ]);
        $child = Child::query()->create($this->childRow($other, ['code' => 'CH-OTHER-001']));
        $user = $this->makeGlobalUser('event_attendance.scan');

        $this->actingAs($user)
            ->postJson("/eventos/{$event->id}/asistencias/scan", [
                'attendee_code' => $child->code,
                'action' => Status::CHECK_IN,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['attendee_code']);
    }

    public function test_event_attendance_scan_requires_scan_permission(): void
    {
        $chain = $this->createChain();
        $event = $this->createEvent($chain, [
            'status' => Status::IN_PROGRESS,
            'attendance_check_in_status' => Status::IN_PROGRESS,
            'attendance_check_out_status' => Status::IN_PROGRESS,
        ]);
        $child = Child::query()->create($this->childRow($chain));
        $user = $this->makeGlobalUser('event_attendance.create');

        $this->actingAs($user)
            ->postJson("/eventos/{$event->id}/asistencias/scan", [
                'attendee_code' => $child->code,
                'action' => Status::CHECK_IN,
            ])
            ->assertForbidden();
    }

    public function test_event_attendance_scan_respects_scope_by_church(): void
    {
        $chain = $this->createChain();
        $other = $this->createChain();
        $event = $this->createEvent($other, [
            'status' => Status::IN_PROGRESS,
            'attendance_check_in_status' => Status::IN_PROGRESS,
            'attendance_check_out_status' => Status::IN_PROGRESS,
        ]);
        $child = Child::query()->create($this->childRow($other, ['code' => 'OTHER-CHILD-EVT-01']));
        $user = $this->makeChurchUser($chain['diocese'], $chain['deanery'], $chain['church'], 'event_attendance.scan');

        $this->actingAs($user)
            ->postJson("/eventos/{$event->id}/asistencias/scan", [
                'attendee_code' => $child->code,
                'action' => Status::CHECK_IN,
            ])
            ->assertForbidden();
    }

    public function test_event_attendance_capture_status_requires_manage_permission(): void
    {
        $chain = $this->createChain();
        $event = $this->createEvent($chain, [
            'status' => Status::IN_PROGRESS,
            'attendance_check_in_status' => Status::IN_PROGRESS,
            'attendance_check_out_status' => Status::IN_PROGRESS,
        ]);
        $user = $this->makeGlobalUser('event_attendance.scan');

        $this->actingAs($user)
            ->postJson("/eventos/{$event->id}/asistencias/status", [
                'capture' => Status::CHECK_IN,
                'status' => Status::COMPLETED,
            ])
            ->assertForbidden();
    }

    public function test_event_attendance_capture_status_completes_and_reopens_capture(): void
    {
        $chain = $this->createChain();
        $event = $this->createEvent($chain, [
            'status' => Status::IN_PROGRESS,
            'attendance_check_in_status' => Status::IN_PROGRESS,
            'attendance_check_out_status' => Status::IN_PROGRESS,
        ]);
        $user = $this->makeGlobalUser('event_attendance.manage');

        $this->actingAs($user)
            ->postJson("/eventos/{$event->id}/asistencias/status", [
                'capture' => Status::CHECK_IN,
                'status' => Status::COMPLETED,
            ])
            ->assertOk()
            ->assertJsonPath('data.attendance_check_in_status', Status::COMPLETED);

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'attendance_check_in_status' => Status::COMPLETED,
            'attendance_check_out_status' => Status::IN_PROGRESS,
        ]);

        $this->actingAs($user)
            ->postJson("/eventos/{$event->id}/asistencias/status", [
                'capture' => Status::CHECK_IN,
                'status' => Status::IN_PROGRESS,
            ])
            ->assertOk()
            ->assertJsonPath('data.attendance_check_in_status', Status::IN_PROGRESS);
    }

    private function createEvent(array $chain, array $overrides = []): Event
    {
        return Event::query()->create(array_merge([
            'church_id' => $chain['church']->id,
            'name' => 'EVENTO TEST',
            'starts_at' => '2026-11-01 00:00:00',
            'ends_at' => '2026-11-01 23:59:00',
            'status' => Status::UPCOMING,
            'attendance_check_in_status' => Status::UPCOMING,
            'attendance_check_out_status' => Status::UPCOMING,
        ], $overrides));
    }

    private function createStaff(array $chain): Staff
    {
        $type = StaffType::query()->create([
            'name' => 'COORDINADOR',
            'description' => 'TEST',
            'status' => Status::ACTIVE,
        ]);

        return Staff::query()->create([
            'staff_type_id' => $type->id,
            'church_id' => $chain['church']->id,
            'community_id' => $chain['community']->id,
            'name' => 'MARIA',
            'paterno' => 'LOPEZ',
            'materno' => 'SOTO',
            'code' => 'STAFF-EVT-001',
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
            'code' => '2026-JPG-20180314-CH'.$chain['church']->id.'-0099',
            'birthdate' => '2018-03-14',
            'sex' => Sex::MALE,
            'blood_type' => BloodType::O_POSITIVE,
            'privacy_terms' => true,
            'status' => Status::ACTIVE,
        ], $overrides);
    }
}
