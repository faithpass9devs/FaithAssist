<?php

namespace Database\Seeders;

use App\Globals\Status;
use App\Models\Regions\Community;
use App\Models\Regions\Municipality;
use App\Models\User;
use Illuminate\Database\Seeder;

class CommunitySeeder extends Seeder
{
    public function run(): void
    {
        $superadmin = User::query()->where('email', 'superadmin@faithassistqr.test')->first();

        if (! $superadmin) {
            $this->command?->warn('No se encontró el usuario Superadmin. Ejecuta UsersPerRoleSeeder primero.');

            return;
        }

        $data = [
            // ── Estado de México ───────────────────────────────────────────
            'Coatepec Harinas' => [
                'ACUITLAPILCO', 'CAPULIN REDONDO', 'COCHISQUILA', 'COLONIA GUADALUPE',
                'CEDRITO', 'EL POTRERITO', 'EL REYNOSO', 'HUAYANALCO', 'IXTLAHUACA',
                'LA JAVIELA', 'LA LOMA', 'LA PRESA', 'LAS JARAS', 'PIEDRAS ANCHAS',
                'SAN ANTONIO', 'SAN FELIPE', 'SAN FRANCISCO', 'SAN JOSÉ', 'SAN LUIS',
                'SAN MARTÍN', 'SAN MIGUEL', 'SAN PABLO', 'SAN PEDRO', 'SANTA ANA',
                'SANTIAGUITO', 'SANTO NIÑO', 'TECOLOTEPEC', 'TEOCOTITLA',
                'CENTRO', 'ANALCO', 'ZACANGUILLO', 'ZACATONES',
            ],
            // 'Tenancingo' => [
            //     'CENTRO', 'SANTIAGUITO', 'SAN SIMONITO', 'EJIDO DE TENANCINGO',
            //     'LA JOYA', 'CALLE REAL', 'SAN PEDRO TLANISCO', 'TEPALCINGO',
            //     'COLONIA BENITO JUÁREZ', 'EL LINDERO',
            // ],
            // 'Villa Guerrero' => [
            //     'CENTRO', 'SAN FRANCISCO', 'SAN PABLO', 'LA PALMA',
            //     'RANCHO LOS ÓRGANOS', 'BUENAVISTA', 'EL JACAL', 'LAS TROJES',
            // ],
            // 'Zumpahuacan' => [
            //     'CENTRO', 'SAN LUCAS', 'SAN MIGUEL', 'LAS PALMAS', 'TEQUIMILPA',
            //     'EL CAPULÍN', 'SAN PEDRO', 'ZAPOTITLÁN',
            // ],

            // // ── Morelos ────────────────────────────────────────────────────
            // 'Cuernavaca' => [
            //     'CENTRO', 'CHAPULTEPEC', 'PALMIRA', 'LOMAS DE CORTÉS',
            //     'JARDINES DE CUERNAVACA', 'BUENA VISTA', 'RANCHO CORTÉS', 'ACAPANTZINGO',
            //     'CHIPITLÁN', 'LAS PALMAS',
            // ],
            // 'Jiutepec' => [
            //     'CENTRO', 'TEJALPA', 'PROGRESO', 'TEPETZINGO', 'LAS FLORES',
            //     'SAN GASPAR', 'ATLACOMULCO', 'LA JOYA',
            // ],
            // 'Temixco' => [
            //     'CENTRO', 'ACATLIPA', 'XOCHITEPEC', 'ANTIQUERA', 'CUENTEPEC',
            //     'TETECALITA', 'TEZOYUCA', 'SAN JOSÉ',
            // ],
            // 'Yautepec' => [
            //     'CENTRO', 'COCOYOC', 'OAXTEPEC', 'ITZAMATITLÁN', 'ATLATLAHUACAN',
            //     'TLAYACAPAN', 'YECAPIXTLA', 'JANTETELCO',
            // ],

            // // ── Guerrero ───────────────────────────────────────────────────
            // 'Chilpancingo de los Bravo' => [
            //     'CENTRO', 'XOCHIPALA', 'LAS PETAQUILLAS', 'EL CAMINO REAL',
            //     'COLONIA GUERRERO', 'BIENESTAR', 'OMILTEMI', 'SAN MARCOS',
            //     'RINCÓN DE LA VÍA', 'COLONIA MORELOS',
            // ],
            // 'Tixtla de Guerrero' => [
            //     'CENTRO', 'CUEXCONTITLÁN', 'ACTEOPAN', 'MIXTECAPA',
            //     'LOS ÓRGANOS', 'ZUMPANGO', 'SAN FRANCISCO',
            // ],
            // 'Chilapa de Álvarez' => [
            //     'CENTRO', 'AHUIXTLA', 'IXCATEOPAN', 'QUECHULTENANGO',
            //     'APALALA', 'OLINALÁ', 'CUETZALA DEL PROGRESO',
            // ],
            // 'Mochitlán' => [
            //     'CENTRO', 'SAN MIGUEL', 'LA CAÑADA', 'PANTITLÁN',
            //     'APANGO', 'QUECHULTENANGO', 'EL PARAÍSO',
            // ],
        ];

        foreach ($data as $municipalityName => $communities) {
            $municipality = Municipality::where('name', $municipalityName)->first();

            if (! $municipality) {
                $this->command?->warn("Municipio no encontrado: {$municipalityName}.");
                continue;
            }

            foreach ($communities as $name) {
                Community::updateOrCreate(
                    ['municipality_id' => $municipality->id, 'name' => $name],
                    [
                        'status'     => Status::ACTIVE,
                        'created_by' => $superadmin->id,
                        'updated_by' => $superadmin->id,
                    ]
                );
            }
        }

        $this->command?->info('Comunidades creadas exitosamente.');
    }
}
