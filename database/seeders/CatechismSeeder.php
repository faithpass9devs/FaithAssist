<?php

namespace Database\Seeders;

use App\Globals\BloodType;
use App\Globals\Sex;
use App\Globals\Status;
use App\Models\Catechism\Child;
use App\Models\Catechism\ChildLevelAssignment;
use App\Models\Catechism\ChildReinscription;
use App\Models\Ecclesiastes\Church;
use App\Models\Ecclesiastes\Diocese;
use App\Models\Operation\Level;
use App\Models\Operation\Period;
use App\Models\Operation\PeriodMovement;
use App\Models\Operation\PeriodMovementType;
use App\Models\Regions\Community;
use App\Models\User;
use App\Services\CatechismPeriodMovementService;
use Illuminate\Database\Seeder;

class CatechismSeeder extends Seeder
{
    public function run(): void
    {
        $superadmin = User::query()->where('email', 'superadmin@faithassistqr.test')->first();

        if (! $superadmin) {
            $this->command?->warn('No se encontro el usuario Superadmin. Ejecuta UsersPerRoleSeeder primero.');

            return;
        }

        $diocese = Diocese::query()->where('name', 'DIOCESIS DE TENANCINGO')->first();

        if (! $diocese) {
            $this->command?->warn('No se encontro la diocesis base para catecismo.');

            return;
        }

        $period = Period::query()
            ->where('diocese_id', $diocese->id)
            ->where('status', Status::IN_PROGRESS)
            ->latest('id')
            ->first();

        if (! $period) {
            $this->command?->warn('No se encontro un periodo activo para la diocesis base.');

            return;
        }

        $inscriptionsType = PeriodMovementType::query()->where('name', CatechismPeriodMovementService::INSCRIPTIONS)->first();
        $reinscriptionsType = PeriodMovementType::query()->where('name', CatechismPeriodMovementService::REINSCRIPTIONS)->first();

        if (! $inscriptionsType || ! $reinscriptionsType) {
            $this->command?->warn('No se encontraron los tipos de movimiento de catecismo.');

            return;
        }

        $inscriptionsMovement = PeriodMovement::query()->updateOrCreate(
            [
                'period_id' => $period->id,
                'period_movement_type_id' => $inscriptionsType->id,
            ],
            [
                'status' => Status::IN_PROGRESS,
                'start_date' => now()->subWeek()->toDateString(),
                'end_date' => now()->addMonth()->toDateString(),
                'notes' => 'Inscripciones activas para datos de prueba de catecismo.',
                'created_by' => $superadmin->id,
                'updated_by' => $superadmin->id,
            ]
        );

        $reinscriptionsMovement = PeriodMovement::query()->updateOrCreate(
            [
                'period_id' => $period->id,
                'period_movement_type_id' => $reinscriptionsType->id,
            ],
            [
                'status' => Status::IN_PROGRESS,
                'start_date' => now()->subDay()->toDateString(),
                'end_date' => now()->addMonth()->toDateString(),
                'notes' => 'Reinscripciones activas para datos de prueba de catecismo.',
                'created_by' => $superadmin->id,
                'updated_by' => $superadmin->id,
            ]
        );

        $churches = [
            [
                'church' => 'PARROQUIA NUESTRA SENORA DE LA ASUNCION',
                'community' => ['SANTA ANA', 'SAN MIGUEL', 'CENTRO'],
                'level' => 'Nivel 1',
                'reinscription' => 'Nivel 2',
            ],
            [
                'church' => 'PARROQUIA SAN FRANCISCO DE ASIS',
                'community' => ['SANTIAGUITO', 'LA JOYA', 'CENTRO'],
                'level' => 'Nivel 1',
                'reinscription' => 'Nivel 2',
            ],
            [
                'church' => 'PARROQUIA CRISTO REY',
                'community' => ['SAN FRANCISCO', 'SAN PABLO', 'CENTRO'],
                'level' => 'Nivel 2',
                'reinscription' => 'Nivel 3',
            ],
            [
                'church' => 'PARROQUIA SAN MIGUEL ARCANGEL DE ZUMPAHUACAN',
                'community' => ['SAN LUCAS', 'LAS PALMAS', 'CENTRO'],
                'level' => 'Nivel 1',
                'reinscription' => null,
            ],
            [
                'church' => 'PARROQUIA NUESTRA SENORA DE LA ASUNCION',
                'community' => ['SANTA ANA', 'SAN MIGUEL', 'CENTRO'],
                'level' => 'Nivel 3',
                'reinscription' => 'Nivel 4',
            ],
            [
                'church' => 'PARROQUIA SAN FRANCISCO DE ASIS',
                'community' => ['SANTIAGUITO', 'LA JOYA', 'CENTRO'],
                'level' => 'Nivel 2',
                'reinscription' => null,
            ],
        ];

        $firstNames = [
            'ANA MARIA', 'LUIS ANGEL', 'SOFIA', 'DIEGO', 'MARIANA', 'JAVIER',
            'REGINA', 'MATEO', 'PAULA', 'EMILIANO', 'VALERIA', 'HUGO',
            'CAMILA', 'ANDRES', 'XIMENA', 'GABRIEL', 'FERNANDA', 'DANIEL',
            'RENATA', 'VICTOR', 'LUCIA', 'ELIAS', 'NATALIA', 'OSCAR',
            'ABRIL', 'ALONSO', 'ISABELLA', 'FERNANDO', 'MIA', 'SANTIAGO',
        ];

        $paternos = [
            'LOPEZ', 'HERNANDEZ', 'REYES', 'RUIZ', 'GONZALEZ', 'TORRES',
            'CASTILLO', 'MORALES', 'RAMIREZ', 'PEREZ', 'SANCHEZ', 'FLORES',
        ];

        $maternos = [
            'CASTILLO', 'TORRES', 'MORALES', 'NAVARRO', 'GARCIA', 'ROJAS',
            'CRUZ', 'VEGA', 'HERRERA', 'MARTINEZ', 'CAMPOS', 'DIAZ',
        ];

        $bloodTypes = [
            BloodType::A_POSITIVE,
            BloodType::O_POSITIVE,
            BloodType::B_POSITIVE,
            BloodType::A_NEGATIVE,
            BloodType::O_NEGATIVE,
            BloodType::AB_POSITIVE,
        ];

        $children = [];
        foreach (range(1, 30) as $index) {
            $bucket = $churches[($index - 1) % count($churches)];
            $reinscription = $bucket['reinscription'];
            $needsReinscription = $reinscription !== null && $index % 3 !== 0;
            $birthYear = 2014 + (($index - 1) % 6);
            $birthMonth = str_pad((string) ((($index - 1) % 9) + 1), 2, '0', STR_PAD_LEFT);
            $birthDay = str_pad((string) ((($index - 1) % 27) + 1), 2, '0', STR_PAD_LEFT);
            $community = $bucket['community'][($index - 1) % count($bucket['community'])];

            $children[] = [
                'code' => sprintf('2026-CH%02d-%04d', $index, $index),
                'church' => $bucket['church'],
                'community' => $community,
                'name' => $firstNames[$index - 1],
                'paterno' => $paternos[($index - 1) % count($paternos)],
                'materno' => $maternos[($index - 1) % count($maternos)],
                'birthdate' => sprintf('%04d-%s-%s', $birthYear, $birthMonth, $birthDay),
                'sex' => $index % 2 === 0 ? Sex::MALE : Sex::FEMALE,
                'blood_type' => $bloodTypes[($index - 1) % count($bloodTypes)],
                'level' => $bucket['level'],
                'reinscription' => $needsReinscription ? $reinscription : null,
                'observations' => $needsReinscription
                    ? 'Reinscripcion de prueba del modulo de catecismo.'
                    : 'Inscripcion inicial de prueba para catecismo.',
            ];
        }

        foreach ($children as $position => $data) {
            $church = Church::query()->where('name', $data['church'])->first();
            $community = Community::query()
                ->where('name', $data['community'])
                ->whereHas('municipality', fn ($query) => $query->where('name', $church?->municipality?->name))
                ->first();
            $level = Level::query()
                ->where('diocese_id', $diocese->id)
                ->where('name', $data['level'])
                ->where('status', Status::ACTIVE)
                ->first();

            if (! $church || ! $community || ! $level) {
                $this->command?->warn("No se pudo resolver la cadena de catecismo para: {$data['code']}.");

                continue;
            }

            $child = Child::query()->updateOrCreate(
                ['code' => $data['code']],
                [
                    'church_id' => $church->id,
                    'community_id' => $community->id,
                    'name' => $data['name'],
                    'paterno' => $data['paterno'],
                    'materno' => $data['materno'],
                    'birthdate' => $data['birthdate'],
                    'sex' => $data['sex'],
                    'blood_type' => $data['blood_type'],
                    'observations' => $data['observations'],
                    'privacy_terms' => true,
                    'status' => Status::ACTIVE,
                    'created_by' => $superadmin->id,
                    'updated_by' => $superadmin->id,
                ]
            );

            ChildLevelAssignment::query()->updateOrCreate(
                [
                    'child_id' => $child->id,
                    'level_id' => $level->id,
                    'period_id' => $inscriptionsMovement->period_id,
                    'period_movement_id' => $inscriptionsMovement->id,
                ],
                [
                    'status' => $data['reinscription'] ? Status::COMPLETED : Status::ACTIVE,
                    'assigned_at' => now()->subDays(30 - $position)->toDateString(),
                    'ended_at' => $data['reinscription'] ? now()->subDays(2)->toDateString() : null,
                    'notes' => $data['reinscription'] ? 'Nivel inicial completado por reinscripcion de prueba.' : 'Inscripcion inicial activa de prueba.',
                    'created_by' => $superadmin->id,
                    'updated_by' => $superadmin->id,
                ]
            );

            if (! $data['reinscription']) {
                continue;
            }

            $destinationLevel = Level::query()
                ->where('diocese_id', $diocese->id)
                ->where('name', $data['reinscription'])
                ->where('status', Status::ACTIVE)
                ->first();

            if (! $destinationLevel) {
                $this->command?->warn("No se encontro el nivel destino para reinscripcion de: {$data['code']}.");

                continue;
            }

            ChildReinscription::query()->updateOrCreate(
                [
                    'child_id' => $child->id,
                    'period_id' => $reinscriptionsMovement->period_id,
                ],
                [
                    'period_movement_id' => $reinscriptionsMovement->id,
                    'from_level_ids' => [$level->id],
                    'to_level_ids' => [$destinationLevel->id],
                    'notes' => 'Reinscripcion de prueba del modulo de catecismo.',
                    'created_by' => $superadmin->id,
                    'updated_by' => $superadmin->id,
                ]
            );

            ChildLevelAssignment::query()->updateOrCreate(
                [
                    'child_id' => $child->id,
                    'level_id' => $destinationLevel->id,
                    'period_id' => $reinscriptionsMovement->period_id,
                    'period_movement_id' => $reinscriptionsMovement->id,
                ],
                [
                    'status' => Status::ACTIVE,
                    'assigned_at' => now()->subDays(1)->toDateString(),
                    'ended_at' => null,
                    'notes' => 'Nivel destino activo por reinscripcion de prueba.',
                    'created_by' => $superadmin->id,
                    'updated_by' => $superadmin->id,
                ]
            );
        }

        $this->command?->info('Datos de catecismo creados exitosamente.');
    }
}
