<?php

namespace Database\Seeders;

use App\Globals\Status;
use App\Models\Ecclesiastes\Diocese;
use App\Models\Regions\Municipality;
use App\Models\Regions\State;
use App\Models\User;
use Illuminate\Database\Seeder;

class MunicipalitySeeder extends Seeder
{
    public function run(): void
    {
        $superadmin = User::query()->where('email', 'superadmin@faithassistqr.test')->first();

        if (! $superadmin) {
            $this->command?->warn('No se encontró el usuario Superadmin. Ejecuta UsersPerRoleSeeder primero.');

            return;
        }

        $edomex    = State::where('name', 'Estado de México')->first();
        // $morelos   = State::where('name', 'Morelos')->first();
        // $guerrero  = State::where('name', 'Guerrero')->first();

        $dTenancingo      = Diocese::where('name', 'DIOCESIS DE TENANCINGO')->first();
        // $dCuernavaca      = Diocese::where('name', 'DIOCESIS DE CUERNAVACA')->first();
        // $dChilpancingo    = Diocese::where('name', 'DIOCESIS DE CHILPANCINGO-CHILAPA')->first();

        $municipalities = [
            // Estado de México – Diócesis de Tenancingo
            ['name' => 'Coatepec Harinas', 'state' => $edomex,   'diocese' => $dTenancingo],
            // ['name' => 'Tenancingo',        'state' => $edomex,   'diocese' => $dTenancingo],
            // ['name' => 'Villa Guerrero',    'state' => $edomex,   'diocese' => $dTenancingo],
            // ['name' => 'Zumpahuacan',       'state' => $edomex,   'diocese' => $dTenancingo],

            // Morelos – Diócesis de Cuernavaca
            // ['name' => 'Cuernavaca',        'state' => $morelos,  'diocese' => $dCuernavaca],
            // ['name' => 'Jiutepec',          'state' => $morelos,  'diocese' => $dCuernavaca],
            // ['name' => 'Temixco',           'state' => $morelos,  'diocese' => $dCuernavaca],
            // ['name' => 'Yautepec',          'state' => $morelos,  'diocese' => $dCuernavaca],

            // Guerrero – Diócesis de Chilpancingo-Chilapa
            // ['name' => 'Chilpancingo de los Bravo', 'state' => $guerrero, 'diocese' => $dChilpancingo],
            // ['name' => 'Tixtla de Guerrero',        'state' => $guerrero, 'diocese' => $dChilpancingo],
            // ['name' => 'Chilapa de Álvarez',        'state' => $guerrero, 'diocese' => $dChilpancingo],
            // ['name' => 'Mochitlán',                 'state' => $guerrero, 'diocese' => $dChilpancingo],
        ];

        foreach ($municipalities as $data) {
            if (! $data['state'] || ! $data['diocese']) {
                $this->command?->warn("Estado o diócesis no encontrados para: {$data['name']}.");
                continue;
            }

            Municipality::updateOrCreate(
                ['name' => $data['name']],
                [
                    'state_id'   => $data['state']->id,
                    'diocese_id' => $data['diocese']->id,
                    'status'     => Status::ACTIVE,
                    'created_by' => $superadmin->id,
                    'updated_by' => $superadmin->id,
                ]
            );
        }

        $this->command?->info('Municipios creados exitosamente.');
    }
}
