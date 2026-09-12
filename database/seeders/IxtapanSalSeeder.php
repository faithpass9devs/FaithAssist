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
            ['name' => 'Ixtapan de la Sal'],
            [
                'state_id' => $edomex->id,
                'status' => Status::ACTIVE,
                'created_by' => $superadmin->id,
                'updated_by' => $superadmin->id,
            ]
        );

        $this->command?->info('Municipio de Ixtapan de la Sal creado exitosamente.');

        $communities = [
            'Barrio de San José',
            'Barrio de San Pedro',
            'Centro',
            'Colonia 10 de Agosto',
            'Colonia 3 de Mayo',
            'Colonia 5 de Febrero',
            'Colonia el Progres',
            'Colonia Juárez',
            'Colonia Revolución',
            'Colorines',
            'El Salitre',
            'Ixtapita',
            'Llano de la unión',
            'Llano de San Diego',
            'Llano de San Juan',
            'Los Naranjos',
            'San Diego Alcalá',
            'San Miguel laderas',
            'San Pedro tlacochaca',
            'Santa Catarina',
            'Tlacochaca',
            'Yautepec',
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
                'alias' => 'Parroquia de la Asunción de María',
                'email' => 'asunciondeixtapan@gmail.com',
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
