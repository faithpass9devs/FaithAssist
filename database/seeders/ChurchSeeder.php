<?php

namespace Database\Seeders;

use App\Globals\Status;
use App\Models\Ecclesiastes\Church;
use App\Models\Ecclesiastes\Deanery;
use App\Models\Regions\Municipality;
use App\Models\User;
use Illuminate\Database\Seeder;

class ChurchSeeder extends Seeder
{
    public function run(): void
    {
        $superadmin = User::query()->where('email', 'superadmin@faithassistqr.test')->first();

        if (! $superadmin) {
            $this->command?->warn('No se encontró el usuario Superadmin. Ejecuta UsersPerRoleSeeder primero.');

            return;
        }

        $churches = [
            // ── Diócesis de Tenancingo ─────────────────────────────────────
            [
                'name'         => 'PARROQUIA NUESTRA SENORA DE LA ASUNCION',
                'alias'        => 'Parroquia Principal',
                'email'        => 'parroquia.asuncion@tenancingo.diocesis.mx',
                'phone'        => '7141234567',
                'address'      => 'Centro, Coatepec Harinas, Estado de México',
                'municipality' => 'Coatepec Harinas',
                'deanery'      => 'NUESTRA SENORA DE LA ASUNCION',
            ],
            // [
            //     'name'         => 'PARROQUIA SAN FRANCISCO DE ASIS',
            //     'alias'        => 'San Francisco',
            //     'email'        => 'parroquia.sanfrancisco@tenancingo.diocesis.mx',
            //     'phone'        => '7141234568',
            //     'address'      => 'Centro, Tenancingo, Estado de México',
            //     'municipality' => 'Tenancingo',
            //     'deanery'      => 'SAN FRANCISCO DE ASIS',
            // ],
            // [
            //     'name'         => 'PARROQUIA CRISTO REY',
            //     'alias'        => 'Cristo Rey',
            //     'email'        => 'parroquia.cristorey@tenancingo.diocesis.mx',
            //     'phone'        => '7141234569',
            //     'address'      => 'Centro, Villa Guerrero, Estado de México',
            //     'municipality' => 'Villa Guerrero',
            //     'deanery'      => 'CRISTO REY',
            // ],
            // [
            //     'name'         => 'PARROQUIA SAN MIGUEL ARCANGEL DE ZUMPAHUACAN',
            //     'alias'        => 'San Miguel Zumpahuacan',
            //     'email'        => 'parroquia.sanmiguel.zumpa@tenancingo.diocesis.mx',
            //     'phone'        => '7141234570',
            //     'address'      => 'Centro, Zumpahuacan, Estado de México',
            //     'municipality' => 'Zumpahuacan',
            //     'deanery'      => 'CRISTO REY',
            // ],

            // // ── Diócesis de Cuernavaca ─────────────────────────────────────
            // [
            //     'name'         => 'PARROQUIA NUESTRA SENORA DE LA ASUNCION CUERNAVACA',
            //     'alias'        => 'Asunción Cuernavaca',
            //     'email'        => 'parroquia.asuncion@cuernavaca.diocesis.mx',
            //     'phone'        => '7771234567',
            //     'address'      => 'Centro Histórico, Cuernavaca, Morelos',
            //     'municipality' => 'Cuernavaca',
            //     'deanery'      => 'LA ASUNCION DE MARIA',
            // ],
            // [
            //     'name'         => 'PARROQUIA SAN JUAN BAUTISTA JIUTEPEC',
            //     'alias'        => 'San Juan Bautista',
            //     'email'        => 'parroquia.sanjuan@cuernavaca.diocesis.mx',
            //     'phone'        => '7771234568',
            //     'address'      => 'Centro, Jiutepec, Morelos',
            //     'municipality' => 'Jiutepec',
            //     'deanery'      => 'SAN JUAN BAUTISTA',
            // ],
            // [
            //     'name'         => 'PARROQUIA SAN MARCOS EVANGELISTA TEMIXCO',
            //     'alias'        => 'San Marcos',
            //     'email'        => 'parroquia.sanmarcos@cuernavaca.diocesis.mx',
            //     'phone'        => '7771234569',
            //     'address'      => 'Centro, Temixco, Morelos',
            //     'municipality' => 'Temixco',
            //     'deanery'      => 'SAN JUAN BAUTISTA',
            // ],
            // [
            //     'name'         => 'PARROQUIA SANTA CATARINA MARTIR YAUTEPEC',
            //     'alias'        => 'Santa Catarina',
            //     'email'        => 'parroquia.santacatarina@cuernavaca.diocesis.mx',
            //     'phone'        => '7771234570',
            //     'address'      => 'Centro, Yautepec, Morelos',
            //     'municipality' => 'Yautepec',
            //     'deanery'      => 'SANTA CATARINA',
            // ],

            // // ── Diócesis de Chilpancingo-Chilapa ───────────────────────────
            // [
            //     'name'         => 'PARROQUIA SAN MATEO APOSTOL CHILPANCINGO',
            //     'alias'        => 'San Mateo',
            //     'email'        => 'parroquia.sanmateo@chilpancingo.diocesis.mx',
            //     'phone'        => '7471234567',
            //     'address'      => 'Centro, Chilpancingo de los Bravo, Guerrero',
            //     'municipality' => 'Chilpancingo de los Bravo',
            //     'deanery'      => 'SAN MATEO APOSTOL',
            // ],
            // [
            //     'name'         => 'PARROQUIA SAN MARTIN OBISPO TIXTLA',
            //     'alias'        => 'San Martín',
            //     'email'        => 'parroquia.sanmartin@chilpancingo.diocesis.mx',
            //     'phone'        => '7471234568',
            //     'address'      => 'Centro, Tixtla de Guerrero, Guerrero',
            //     'municipality' => 'Tixtla de Guerrero',
            //     'deanery'      => 'SAN MARTIN OBISPO',
            // ],
            // [
            //     'name'         => 'PARROQUIA SAN MIGUEL ARCANGEL CHILAPA',
            //     'alias'        => 'San Miguel Chilapa',
            //     'email'        => 'parroquia.sanmiguel@chilpancingo.diocesis.mx',
            //     'phone'        => '7471234569',
            //     'address'      => 'Centro, Chilapa de Álvarez, Guerrero',
            //     'municipality' => 'Chilapa de Álvarez',
            //     'deanery'      => 'SAN MIGUEL ARCANGEL',
            // ],
            // [
            //     'name'         => 'PARROQUIA SANTIAGO APOSTOL MOCHITLAN',
            //     'alias'        => 'Santiago Mochitlán',
            //     'email'        => 'parroquia.santiago@chilpancingo.diocesis.mx',
            //     'phone'        => '7471234570',
            //     'address'      => 'Centro, Mochitlán, Guerrero',
            //     'municipality' => 'Mochitlán',
            //     'deanery'      => 'SAN MARTIN OBISPO',
            // ],
        ];

        foreach ($churches as $data) {
            $municipality = Municipality::where('name', $data['municipality'])->first();
            $deanery      = Deanery::where('name', $data['deanery'])->first();

            if (! $municipality || ! $deanery) {
                $this->command?->warn("Municipio o decanato no encontrado para: {$data['name']}.");
                continue;
            }

            Church::updateOrCreate(
                ['name' => $data['name']],
                [
                    'alias'           => $data['alias'],
                    'email'           => $data['email'],
                    'phone'           => $data['phone'],
                    'address'         => $data['address'],
                    'municipality_id' => $municipality->id,
                    'deanery_id'      => $deanery->id,
                    'status'          => Status::ACTIVE,
                    'created_by'      => $superadmin->id,
                    'updated_by'      => $superadmin->id,
                ]
            );
        }

        $this->command?->info('Parroquias creadas exitosamente.');
    }
}
