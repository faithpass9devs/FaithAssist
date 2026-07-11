<?php

namespace Database\Seeders;

use App\Globals\Status;
use App\Models\Masses\IncidenceType;
use App\Models\User;
use Illuminate\Database\Seeder;

class IncidenceTypeSeeder extends Seeder
{
    public function run(): void
    {
        $superadmin = User::query()->where('email', 'superadmin@faithassistqr.test')->first();

        IncidenceType::query()->updateOrCreate(
            ['name' => 'MEDICA'],
            [
                'description' => 'Justificacion medica para inasistencias.',
                'status' => Status::ACTIVE,
                'created_by' => $superadmin?->id,
                'updated_by' => $superadmin?->id,
            ],
        );
    }
}
