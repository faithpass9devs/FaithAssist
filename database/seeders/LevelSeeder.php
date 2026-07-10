<?php

namespace Database\Seeders;

use App\Globals\Status;
use App\Models\Ecclesiastes\Diocese;
use App\Models\Operation\Level;
use App\Models\User;
use Illuminate\Database\Seeder;

class LevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superadmin = User::query()->where('email', 'superadmin@faithassistqr.test')->first();

        if (! $superadmin) {
            $this->command?->warn('No se encontró el usuario Superadmin. Ejecuta UsersPerRoleSeeder primero.');

            return;
        }

        $dioceses = Diocese::query()->get();

        if ($dioceses->isEmpty()) {
            $this->command?->warn('No se encontraron diócesis. Ejecuta DioceseSeeder primero.');

            return;
        }

        $levels = [
            [
                'name' => 'Nivel 1',
                'description' => 'Descubro a mi Papá Dios',
            ],
            [
                'name' => 'Nivel 2',
                'description' => 'Descubro a mi Papá Dios',
            ],
            [
                'name' => 'Nivel 3',
                'description' => 'Descubro a mi Papá Dios',
            ],
            [
                'name' => 'Nivel 4',
                'description' => 'Jesús vive entre nosotros',
            ],
            [
                'name' => 'Nivel 5',
                'description' => 'Jesús vive entre nosotros',
            ],
            [
                'name' => 'Nivel 6',
                'description' => 'Jesús vive entre nosotros',
            ],
            [
                'name' => 'Nivel 7',
                'description' => 'Por el espíritu conozco y vivo mi fe',
            ],
            [
                'name' => 'Nivel 8',
                'description' => 'Por el espíritu conozco y vivo mi fe',
            ],
            [
                'name' => 'Nivel 9',
                'description' => 'Por el espíritu conozco y vivo mi fe',
            ],
        ];

        foreach ($dioceses as $diocese) {
            foreach ($levels as $level) {
                Level::query()->updateOrCreate(
                    [
                        'diocese_id' => $diocese->id,
                        'name' => $level['name'],
                    ],
                    [
                        'description' => $level['description'],
                        'status' => Status::ACTIVE,
                        'created_by' => $superadmin->id,
                        'updated_by' => $superadmin->id,
                    ],
                );
            }
        }

        $this->command?->info('Niveles creados exitosamente para todas las diócesis.');
    }
}
