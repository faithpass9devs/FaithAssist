<?php

namespace Database\Seeders;

use App\Globals\Status;
use App\Models\Ecclesiastes\Deanery;
use App\Models\Ecclesiastes\Diocese;
use App\Models\User;
use Illuminate\Database\Seeder;

class DeanerySeeder extends Seeder
{
    public function run(): void
    {
        $superadmin = User::query()->where('email', 'superadmin@faithassistqr.test')->first();

        if (! $superadmin) {
            $this->command?->warn('No se encontró el usuario Superadmin. Ejecuta UsersPerRoleSeeder primero.');

            return;
        }

        $deaneries = [
            // Diócesis de Tenancingo (Edomex)
            ['diocese' => 'DIOCESIS DE TENANCINGO',          'name' => 'NUESTRA SENORA DE LA ASUNCION'],
            ['diocese' => 'DIOCESIS DE TENANCINGO',          'name' => 'SAN FRANCISCO DE ASIS'],
            ['diocese' => 'DIOCESIS DE TENANCINGO',          'name' => 'CRISTO REY'],

            // // Diócesis de Cuernavaca (Morelos)
            // ['diocese' => 'DIOCESIS DE CUERNAVACA',          'name' => 'LA ASUNCION DE MARIA'],
            // ['diocese' => 'DIOCESIS DE CUERNAVACA',          'name' => 'SAN JUAN BAUTISTA'],
            // ['diocese' => 'DIOCESIS DE CUERNAVACA',          'name' => 'SANTA CATARINA'],

            // // Diócesis de Chilpancingo-Chilapa (Guerrero)
            // ['diocese' => 'DIOCESIS DE CHILPANCINGO-CHILAPA', 'name' => 'SAN MATEO APOSTOL'],
            // ['diocese' => 'DIOCESIS DE CHILPANCINGO-CHILAPA', 'name' => 'SAN MARTIN OBISPO'],
            // ['diocese' => 'DIOCESIS DE CHILPANCINGO-CHILAPA', 'name' => 'SAN MIGUEL ARCANGEL'],
        ];

        foreach ($deaneries as $data) {
            $diocese = Diocese::where('name', $data['diocese'])->first();

            if (! $diocese) {
                $this->command?->warn("Diócesis no encontrada: {$data['diocese']}.");
                continue;
            }

            Deanery::updateOrCreate(
                ['diocese_id' => $diocese->id, 'name' => $data['name']],
                [
                    'status'     => Status::ACTIVE,
                    'created_by' => $superadmin->id,
                    'updated_by' => $superadmin->id,
                ]
            );
        }

        $this->command?->info('Decanatos creados exitosamente.');
    }
}
