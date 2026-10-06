<?php

namespace Database\Seeders;

use App\Globals\Status;
use App\Models\Ecclesiastes\Church;
use App\Models\Ecclesiastes\Deanery;
use App\Models\Regions\Community;
use App\Models\Regions\Municipality;
use App\Models\Regions\State;
use App\Models\User;
use Illuminate\Database\Seeder;

class IxtapanSalSeeder extends Seeder
{
    public function run(): void
    {
        $superadmin = User::query()->where('email', 'd.g.adrian28@gmail.com')->first();

        if (! $superadmin) {
            $this->command?->warn('No se encontró el usuario Superadmin. Ejecuta UsersPerRoleSeeder primero.');

            return;
        }

        $edomex = State::where('short_name', 'Edomex')->first();

        if (! $edomex) {
            $this->command?->warn('Estado de México no encontrado. Ejecuta StateSeeder primero.');

            return;
        }

        $municipality = Municipality::updateOrCreate(
            ['name' => 'IXTAPAN DE LA SAL'],
            [
                'state_id' => $edomex->id,
                'status' => Status::ACTIVE,
                'created_by' => $superadmin->id,
                'updated_by' => $superadmin->id,
            ]
        );

        $this->command?->info('Municipio de Ixtapan de la Sal creado exitosamente.');

        $communities = [
            'BARRIO DE SAN JOSÉ',
            'BARRIO DE SAN PEDRO',
            'CENTRO',
            'COLONIA 10 DE AGOSTO',
            'COLONIA 3 DE MAYO',
            'COLONIA 5 DE FEBRERO',
            'COLONIA EL PROGRES',
            'COLONIA JUÁREZ',
            'COLONIA REVOLUCIÓN',
            'COLORINES',
            'EL SALITRE',
            'IXTAPITA',
            'LLANO DE LA UNIÓN',
            'LLANO DE SAN DIEGO',
            'LLANO DE SAN JUAN',
            'LOS NARANJOS',
            'SAN DIEGO ALCALÁ',
            'SAN MIGUEL LADERAS',
            'SAN PEDRO TLACOCHACA',
            'SANTA CATARINA',
            'TLACOCHACA',
            'YAUTEPEC',
        ];

        foreach ($communities as $name) {
            Community::updateOrCreate(
                ['municipality_id' => $municipality->id, 'name' => $name],
                [
                    'status' => Status::ACTIVE,
                    'created_by' => $superadmin->id,
                    'updated_by' => $superadmin->id,
                ]
            );
        }

        $this->command?->info('Comunidades de Ixtapan de la Sal creadas exitosamente.');

        $deanery = Deanery::where('name', 'NUESTRA SENORA DE LA ASUNCION')->first();

        if (! $deanery) {
            $this->command?->warn('Decanato NUESTRA SENORA DE LA ASUNCION no encontrado. Ejecuta DeanerySeeder primero.');

            return;
        }

        Church::updateOrCreate(
            ['name' => 'PARROQUIA DE LA ASUNCIÓN DE MARIA DE IXTAPAN DE LA SAL.MEX.'],
            [
                'alias' => 'PARROQUIA DE LA ASUNCIÓN DE MARÍA',
                'email' => 'ASUNCIONDEIXTAPAN@GMAIL.COM',
                'phone' => '7311430481',
                'address' => 'ALVARO O REGIN NO.2',
                'municipality_id' => $municipality->id,
                'deanery_id' => $deanery->id,
                'status' => Status::ACTIVE,
                'created_by' => $superadmin->id,
                'updated_by' => $superadmin->id,
            ]
        );

        $this->command?->info('Parroquia de Ixtapan de la Sal creada exitosamente.');
    }
}
