<?php

namespace Tests\Feature\Masses;

use App\Globals\BloodType;
use App\Globals\Sex;
use App\Globals\Status;
use App\Models\Catechism\Child;
use App\Models\Masses\IncidenceType;
use App\Models\Masses\Mass;
use App\Models\Masses\MassAttendance;
use App\Models\Masses\Weekend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\ControllerTestHelpers;
use Tests\TestCase;

class AttendanceIncidentTest extends TestCase
{
    use ControllerTestHelpers, RefreshDatabase;

    public function test_incidence_type_catalog_creates_types(): void
    {
        $user = $this->makeGlobalUser('tipos_incidencias.create');

        $this->actingAs($user)
            ->postJson('/tipos-incidencias', [
                'name' => 'enfermedad',
                'description' => 'justificante medico',
                'status' => Status::ACTIVE,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'ENFERMEDAD')
            ->assertJsonPath('data.description', 'JUSTIFICANTE MEDICO');

        $this->assertDatabaseHas('incidence_types', [
            'name' => 'ENFERMEDAD',
            'description' => 'JUSTIFICANTE MEDICO',
            'status' => Status::ACTIVE,
        ]);
    }

    public function test_attendance_incident_creates_missing_absence_without_marking_it_justified(): void
    {
        $chain = $this->createChain();
        $weekend = $this->createWeekend($chain);
        $mass = $this->createMass($weekend, $chain);
        $child = Child::query()->create($this->childRow($chain));
        $type = IncidenceType::query()->create([
            'name' => 'ENFERMEDAD',
            'description' => 'JUSTIFICANTE MEDICO',
            'status' => Status::ACTIVE,
        ]);
        $user = $this->makeGlobalUser('incidencias_asistencia.create');

        $this->actingAs($user)
            ->postJson('/incidencias-asistencia', [
                'weekend_id' => $weekend->id,
                'child_id' => $child->id,
                'incidence_type_id' => $type->id,
                'description' => 'Presento receta medica.',
                'status' => Status::ACTIVE,
            ])
            ->assertCreated()
            ->assertJsonPath('data.child_id', $child->id);

        $this->assertDatabaseHas('mass_attendance_incidents', [
            'weekend_id' => $weekend->id,
            'child_id' => $child->id,
            'incidence_type_id' => $type->id,
            'description' => 'PRESENTO RECETA MEDICA.',
            'status' => Status::ACTIVE,
        ]);

        $this->assertDatabaseHas('mass_attendance', [
            'mass_id' => $mass->id,
            'child_id' => $child->id,
            'status' => Status::FAILED,
        ]);
    }

    public function test_attendance_incident_preserves_existing_absence_status(): void
    {
        $chain = $this->createChain();
        $weekend = $this->createWeekend($chain);
        $mass = $this->createMass($weekend, $chain);
        $child = Child::query()->create($this->childRow($chain));
        $attendance = MassAttendance::query()->create([
            'mass_id' => $mass->id,
            'child_id' => $child->id,
            'child_code' => $child->code,
            'church_id' => $chain['church']->id,
            'status' => Status::CHECK_IN,
            'check_in_at' => now(),
        ]);
        $type = IncidenceType::query()->create([
            'name' => 'FAMILIAR',
            'status' => Status::ACTIVE,
        ]);
        $user = $this->makeGlobalUser('incidencias_asistencia.create');

        $this->actingAs($user)
            ->postJson('/incidencias-asistencia', [
                'weekend_id' => $weekend->id,
                'child_id' => $child->id,
                'incidence_type_id' => $type->id,
                'description' => 'Aviso familiar.',
                'status' => Status::ACTIVE,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('mass_attendance', [
            'id' => $attendance->id,
            'status' => Status::CHECK_IN,
        ]);
    }

    public function test_attendance_incident_rejects_weekend_without_absences(): void
    {
        $chain = $this->createChain();
        $weekend = $this->createWeekend($chain);
        $mass = $this->createMass($weekend, $chain);
        $child = Child::query()->create($this->childRow($chain));
        MassAttendance::query()->create([
            'mass_id' => $mass->id,
            'child_id' => $child->id,
            'child_code' => $child->code,
            'church_id' => $chain['church']->id,
            'status' => Status::CHECK_OUT,
            'check_in_at' => now()->subHour(),
            'check_out_at' => now(),
        ]);
        $type = IncidenceType::query()->create([
            'name' => 'FAMILIAR',
            'status' => Status::ACTIVE,
        ]);
        $user = $this->makeGlobalUser('incidencias_asistencia.create');

        $this->actingAs($user)
            ->postJson('/incidencias-asistencia', [
                'weekend_id' => $weekend->id,
                'child_id' => $child->id,
                'incidence_type_id' => $type->id,
                'description' => 'Aviso familiar.',
                'status' => Status::ACTIVE,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['weekend_id']);
    }

    public function test_attendance_incident_prevents_duplicate_active_incidents(): void
    {
        $chain = $this->createChain();
        $weekend = $this->createWeekend($chain);
        $this->createMass($weekend, $chain);
        $child = Child::query()->create($this->childRow($chain));
        $type = IncidenceType::query()->create([
            'name' => 'FAMILIAR',
            'status' => Status::ACTIVE,
        ]);
        $user = $this->makeGlobalUser('incidencias_asistencia.create');
        $payload = [
            'weekend_id' => $weekend->id,
            'child_id' => $child->id,
            'incidence_type_id' => $type->id,
            'description' => 'Aviso familiar.',
            'status' => Status::ACTIVE,
        ];

        $this->actingAs($user)->postJson('/incidencias-asistencia', $payload)->assertCreated();
        $this->actingAs($user)
            ->postJson('/incidencias-asistencia', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['child_id']);
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

    private function createMass(Weekend $weekend, array $chain): Mass
    {
        return Mass::query()->create([
            'weekend_id' => $weekend->id,
            'church_id' => $chain['church']->id,
            'name' => 'MISA DOMINICAL',
            'starts_at' => '2026-07-05 10:00',
            'ends_at' => '2026-07-05 11:00',
            'status' => Status::IN_PROGRESS,
            'attendance_status' => Status::IN_PROGRESS,
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
