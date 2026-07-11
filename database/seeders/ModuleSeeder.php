<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superadmin = User::query()->where('email', 'superadmin@faithassistqr.test')->first();

        if (! $superadmin) {
            $this->command?->warn('No se encontro el usuario Superadmin. Ejecuta UsersPerRoleSeeder primero.');

            return;
        }

        $modules = [
            [
                'name' => 'Ecclesiastes',
                'description' => 'Módulo para la gestión de datos eclesiásticos.',
                'key' => 'ecclesiastes',
            ],
            [
                'name' => 'Regions',
                'description' => 'Módulo para la gestión de regiones, estados y municipios.',
                'key' => 'regions',
            ],
            [
                'name' => 'Seguridad',
                'description' => 'Módulo para la gestión de roles, permisos y usuarios.',
                'key' => 'security',
            ],
            [
                'name' => 'Operación',
                'description' => 'Módulo para la gestión de operaciones y actividades.',
                'key' => 'operation',
            ],
            [
                'name' => 'Catechism',
                'description' => 'Módulo para la gestión de catecismo.',
                'key' => 'catechism',
            ],
            [
                'name' => 'Misas',
                'description' => 'Módulo para la gestión de misas y asistencias.',
                'key' => 'masses',
            ],
        ];

        foreach ($modules as $module) {
            DB::table('modules')->updateOrInsert(
                ['key' => $module['key']],
                [
                    'name' => $module['name'],
                    'description' => $module['description'],
                    'created_by' => $superadmin->id,
                    'updated_by' => $superadmin->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
