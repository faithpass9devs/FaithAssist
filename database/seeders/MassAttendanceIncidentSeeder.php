<?php

namespace Database\Seeders;

use App\Globals\Status;
use App\Models\Masses\IncidenceType;
use App\Models\Masses\MassAttendance;
use App\Models\Masses\MassAttendanceIncident;
use App\Models\Masses\Weekend;
use App\Models\User;
use Illuminate\Database\Seeder;

class MassAttendanceIncidentSeeder extends Seeder
{
    public function run(): void
    {
        $superadmin = User::query()->where('email', 'superadmin@faithassistqr.test')->first();

        if (! $superadmin) {
            $this->command?->warn('No se encontro el usuario Superadmin. Ejecuta UsersPerRoleSeeder primero.');

            return;
        }

        $incidenceTypes = $this->seedIncidenceTypes($superadmin->id);

        $weekends = Weekend::query()
            ->whereIn('status', [Status::COMPLETED, Status::IN_PROGRESS])
            ->orderBy('starts_at')
            ->get();

        foreach ($weekends as $weekendIndex => $weekend) {
            $invalidAttendances = MassAttendance::query()
                ->whereHas('mass', fn ($query) => $query->where('weekend_id', $weekend->id))
                ->whereIn('status', [Status::CHECK_IN, Status::FAILED])
                ->orderBy('id')
                ->get()
                ->unique('child_id')
                ->take(2)
                ->values();

            if ($invalidAttendances->isEmpty()) {
                continue;
            }

            $incidenceType = $incidenceTypes[$weekendIndex % count($incidenceTypes)];

            foreach ($invalidAttendances as $attendance) {
                MassAttendanceIncident::query()->updateOrCreate(
                    [
                        'weekend_id' => $weekend->id,
                        'child_id' => $attendance->child_id,
                    ],
                    [
                        'incidence_type_id' => $incidenceType->id,
                        'description' => 'Incidencia de asistencia generada automaticamente para datos de prueba.',
                        'status' => Status::ACTIVE,
                        'created_by' => $superadmin->id,
                        'updated_by' => $superadmin->id,
                    ]
                );
            }
        }

        $this->command?->info('Incidencias de asistencia de prueba creadas exitosamente.');
    }

    /**
     * @return array<int, IncidenceType>
     */
    private function seedIncidenceTypes(int $userId): array
    {
        $types = [
            [
                'name' => 'MEDICA',
                'description' => 'Justificacion medica para inasistencias.',
            ],
            [
                'name' => 'FAMILIAR',
                'description' => 'Situacion familiar extraordinaria.',
            ],
            [
                'name' => 'ESCOLAR',
                'description' => 'Actividad o evaluacion escolar.',
            ],
            [
                'name' => 'TRASLADO',
                'description' => 'Dificultad de movilidad o transporte.',
            ],
        ];

        return collect($types)
            ->map(fn (array $type): IncidenceType => IncidenceType::query()->updateOrCreate(
                ['name' => $type['name']],
                [
                    'description' => $type['description'],
                    'status' => Status::ACTIVE,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]
            ))
            ->all();
    }
}
