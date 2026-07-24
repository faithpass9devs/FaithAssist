<?php

namespace Database\Seeders;

use App\Globals\Status;
use App\Models\Ecclesiastes\Diocese;
use App\Models\Regions\State;
use App\Models\User;
use Illuminate\Database\Seeder;

class DioceseSeeder extends Seeder
{
    public function run(): void
    {
        $superadmin = User::query()->where('email', 'superadmin@faithassistqr.test')->first();

        if (! $superadmin) {
            $this->command?->warn('No se encontró el usuario Superadmin. Ejecuta UsersPerRoleSeeder primero.');

            return;
        }

        $dioceses = [
            [
                'state'  => 'Estado de México',
                'name'   => 'DIOCESIS DE TENANCINGO',
                'bishop' => 'MONS. VICTOR CARABES CHAVEZ',
            ],
            // [
            //     'state'  => 'Morelos',
            //     'name'   => 'DIOCESIS DE CUERNAVACA',
            //     'bishop' => 'MONS. RAMON CASTRO CASTRO',
            // ],
            // [
            //     'state'  => 'Guerrero',
            //     'name'   => 'DIOCESIS DE CHILPANCINGO-CHILAPA',
            //     'bishop' => 'MONS. JOSE DE JESUS GONZALEZ HERNANDEZ',
            // ],
        ];

        foreach ($dioceses as $data) {
            $state = State::where('name', $data['state'])->first();

            if (! $state) {
                $this->command?->warn("No se encontró el estado: {$data['state']}.");
                continue;
            }

            Diocese::updateOrCreate(
                ['name' => $data['name']],
                [
                    'state_id'   => $state->id,
                    'bishop'     => $data['bishop'],
                    'status'     => Status::ACTIVE,
                    'created_by' => $superadmin->id,
                    'updated_by' => $superadmin->id,
                ]
            );
        }

        $this->command?->info('Diócesis creadas exitosamente.');
    }
}
