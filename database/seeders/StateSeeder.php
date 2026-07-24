<?php

namespace Database\Seeders;

use App\Globals\Status;
use App\Models\Regions\State;
use App\Models\User;
use Illuminate\Database\Seeder;

class StateSeeder extends Seeder
{
    public function run(): void
    {
        $superadmin = User::query()->where('email', 'superadmin@faithassistqr.test')->first();

        if (! $superadmin) {
            $this->command?->warn('No se encontró el usuario Superadmin. Ejecuta UsersPerRoleSeeder primero.');

            return;
        }

        $states = [
            ['name' => 'Estado de México', 'short_name' => 'Edomex'],
            // ['name' => 'Morelos',           'short_name' => 'Mor.'],
            // ['name' => 'Guerrero',          'short_name' => 'Gro.'],
        ];

        foreach ($states as $state) {
            State::updateOrCreate(
                ['name' => $state['name']],
                [
                    'short_name' => $state['short_name'],
                    'status'     => Status::ACTIVE,
                    'created_by' => $superadmin->id,
                    'updated_by' => $superadmin->id,
                ]
            );
        }

        $this->command?->info('Estados creados exitosamente.');
    }
}
