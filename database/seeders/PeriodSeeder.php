<?php

namespace Database\Seeders;

use App\Globals\Status;
use App\Models\Ecclesiastes\Church;
use App\Models\Ecclesiastes\Diocese;
use App\Models\Operation\Period;
use App\Models\Operation\PeriodMovement;
use App\Models\Operation\PeriodMovementType;
use App\Models\User;
use Illuminate\Database\Seeder;

class PeriodSeeder extends Seeder
{
    public function run(): void
    {
        $superadmin = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'Superadmin'))
            ->orderBy('id')
            ->first() ?? User::query()->where('email', 'superadmin@faithassistqr.test')->first();

        if (! $superadmin) {
            $this->command?->warn('No se encontró el usuario Superadmin. Ejecuta UsersPerRoleSeeder primero.');

            return;
        }

        $movementTypes = PeriodMovementType::query()
            ->whereIn('name', ['PREINSCRIPCIONES', 'INSCRIPCIONES', 'REINSCRIPCIONES'])
            ->get()
            ->keyBy('name');

        if ($movementTypes->count() !== 3) {
            $this->command?->warn('No se encontraron los tipos de movimiento. Ejecuta PeriodMovementTypeSeeder primero.');

            return;
        }

        $coatepec = Church::query()
            ->whereHas('municipality', fn ($q) => $q->where('name', 'Coatepec Harinas'))
            ->first();

        $ixtapan = Church::query()
            ->whereHas('municipality', fn ($q) => $q->where('name', 'Ixtapan de la Sal'))
            ->first();

        if (! $coatepec) {
            $this->command?->warn('No se encontró la parroquia de Coatepec. No se crearán movimientos.');

            return;
        }

        $dioceses = Diocese::all();
        $periods = [
            [
                'name' => '2023-2024',
                'start_date' => '2023-07-01',
                'end_date' => '2024-06-30',
                'years' => '2023-2024',
                'status' => Status::COMPLETED,
                'movements' => [
                    [
                        'type' => 'PREINSCRIPCIONES',
                        'status' => Status::COMPLETED,
                        'start_date' => '2023-07-01',
                        'end_date' => '2023-07-31',
                        'notes' => 'Preinscripciones periodo 2023-2024.',
                    ],
                    [
                        'type' => 'INSCRIPCIONES',
                        'status' => Status::COMPLETED,
                        'start_date' => '2023-08-01',
                        'end_date' => '2023-09-30',
                        'notes' => 'Inscripciones periodo 2023-2024.',
                    ],
                    [
                        'type' => 'REINSCRIPCIONES',
                        'status' => Status::COMPLETED,
                        'start_date' => '2023-08-01',
                        'end_date' => '2023-09-30',
                        'notes' => 'Reinscripciones periodo 2023-2024.',
                    ],
                ],
            ],
            [
                'name' => '2024-2025',
                'start_date' => '2024-07-01',
                'end_date' => '2025-06-30',
                'years' => '2024-2025',
                'status' => Status::COMPLETED,
                'movements' => [
                    [
                        'type' => 'PREINSCRIPCIONES',
                        'status' => Status::COMPLETED,
                        'start_date' => '2024-07-01',
                        'end_date' => '2024-07-31',
                        'notes' => 'Preinscripciones periodo 2024-2025.',
                    ],
                    [
                        'type' => 'INSCRIPCIONES',
                        'status' => Status::COMPLETED,
                        'start_date' => '2024-08-01',
                        'end_date' => '2024-09-30',
                        'notes' => 'Inscripciones periodo 2024-2025.',
                    ],
                    [
                        'type' => 'REINSCRIPCIONES',
                        'status' => Status::COMPLETED,
                        'start_date' => '2024-08-01',
                        'end_date' => '2024-09-30',
                        'notes' => 'Reinscripciones periodo 2024-2025.',
                    ],
                ],
            ],
            [
                'name' => '2025-2026',
                'start_date' => '2025-07-01',
                'end_date' => '2026-06-30',
                'years' => '2025-2026',
                'status' => Status::COMPLETED,
                'movements' => [
                    [
                        'type' => 'PREINSCRIPCIONES',
                        'status' => Status::COMPLETED,
                        'start_date' => '2026-07-01',
                        'end_date' => '2026-07-31',
                        'notes' => 'Preinscripciones periodo 2025-2026.',
                    ],
                    [
                        'type' => 'INSCRIPCIONES',
                        'status' => Status::COMPLETED,
                        'start_date' => '2026-07-27',
                        'end_date' => '2026-08-15',
                        'notes' => 'Inscripciones periodo 2025-2026.',
                    ],
                    [
                        'type' => 'REINSCRIPCIONES',
                        'status' => Status::COMPLETED,
                        'start_date' => '2026-08-29',
                        'end_date' => '2026-08-31',
                        'notes' => 'Reinscripciones periodo 2025-2026.',
                    ],
                ],
            ],
            [
                'name' => '2026-2027',
                'start_date' => '2026-07-01',
                'end_date' => '2027-06-30',
                'years' => '2026-2027',
                'status' => Status::IN_PROGRESS,
                'movements' => [
                    [
                        'type' => 'PREINSCRIPCIONES',
                        'status' => Status::COMPLETED,
                        'start_date' => '2026-07-01',
                        'end_date' => '2026-07-31',
                        'notes' => 'Preinscripciones periodo 2026-2027.',
                    ],
                    [
                        'type' => 'INSCRIPCIONES',
                        'status' => Status::IN_PROGRESS,
                        'start_date' => '2026-09-01',
                        'end_date' => '2026-09-30',
                        'notes' => 'Inscripciones abiertas periodo 2026-2027.',
                    ],
                    [
                        'type' => 'REINSCRIPCIONES',
                        'status' => Status::IN_PROGRESS,
                        'start_date' => '2026-09-01',
                        'end_date' => '2026-09-30',
                        'notes' => 'Reinscripciones abiertas periodo 2026-2027.',
                    ],
                ],
            ],
        ];

        foreach ($dioceses as $diocese) {
            foreach ($periods as $periodData) {
                $period = Period::updateOrCreate(
                    ['diocese_id' => $diocese->id, 'name' => $periodData['name']],
                    [
                        'start_date' => $periodData['start_date'],
                        'end_date' => $periodData['end_date'],
                        'years' => $periodData['years'],
                        'status' => $periodData['status'],
                        'created_by' => $superadmin->id,
                        'updated_by' => $superadmin->id,
                    ]
                );

                $churchIds = $this->churchIdsForPeriod($periodData['years'], $coatepec, $ixtapan);

                $this->createMovements($period, $superadmin->id, $movementTypes, $periodData['movements'], $churchIds);
            }
        }

        $this->command?->info('Periodos y movimientos creados exitosamente.');
    }

    private function churchIdsForPeriod(string $years, Church $coatepec, ?Church $ixtapan = null): array
    {
        if ($years === '2026-2027') {
            return array_values(array_filter([$coatepec->id, $ixtapan?->id]));
        }

        return [$coatepec->id];
    }

    private function createMovements(Period $period, int $userId, $movementTypes, array $movements, array $churchIds): void
    {
        foreach ($churchIds as $churchId) {
            foreach ($movements as $data) {
                PeriodMovement::updateOrCreate(
                    [
                        'period_id' => $period->id,
                        'church_id' => $churchId,
                        'period_movement_type_id' => $movementTypes[$data['type']]->id,
                    ],
                    [
                        'status' => $data['status'],
                        'start_date' => $data['start_date'],
                        'end_date' => $data['end_date'],
                        'notes' => $data['notes'],
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]
                );
            }
        }
    }
}