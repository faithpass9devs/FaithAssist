<?php

namespace Database\Seeders;

use App\Globals\Status;
use App\Models\Operation\PeriodMovementType;
use App\Models\User;
use Illuminate\Database\Seeder;

class PeriodMovementTypeSeeder extends Seeder
{
    public function run(): void
    {
        $superadmin = User::query()->where('email', 'superadmin@faithassistqr.test')->first();

        $types = [
            [
                'name' => 'PREINSCRIPCIONES',
                'description' => 'Movimientos de preinscripción previos al periodo.',
                'status' => Status::ACTIVE,
            ],
            [
                'name' => 'INSCRIPCIONES',
                'description' => 'Movimientos de registro principal del periodo.',
                'status' => Status::ACTIVE,
            ],
            [
                'name' => 'REINSCRIPCIONES',
                'description' => 'Movimientos de reinscripción dentro del periodo.',
                'status' => Status::ACTIVE,
            ],
        ];

        foreach ($types as $type) {
            PeriodMovementType::query()->updateOrCreate(
                ['name' => $type['name']],
                [
                    ...$type,
                    'created_by' => $superadmin?->id,
                    'updated_by' => $superadmin?->id,
                ],
            );
        }
    }
}
