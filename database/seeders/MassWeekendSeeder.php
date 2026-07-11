<?php

namespace Database\Seeders;

use App\Globals\Status;
use App\Models\Ecclesiastes\Church;
use App\Models\Masses\Weekend;
use App\Models\User;
use Illuminate\Database\Seeder;

class MassWeekendSeeder extends Seeder
{
    public function run(): void
    {
        $superadmin = User::query()->where('email', 'superadmin@faithassistqr.test')->first();

        if (! $superadmin) {
            $this->command?->warn('No se encontro el usuario Superadmin. Ejecuta UsersPerRoleSeeder primero.');

            return;
        }

        $churches = Church::query()
            ->whereIn('name', [
                'PARROQUIA NUESTRA SENORA DE LA ASUNCION',
                'PARROQUIA SAN FRANCISCO DE ASIS',
                'PARROQUIA CRISTO REY',
                'PARROQUIA SAN MIGUEL ARCANGEL DE ZUMPAHUACAN',
            ])
            ->where('status', Status::ACTIVE)
            ->orderBy('id')
            ->get();

        if ($churches->isEmpty()) {
            $this->command?->warn('No se encontraron parroquias activas para crear fines de semana de misas.');

            return;
        }

        $baseSaturday = now()->startOfWeek()->addDays(5)->startOfDay();
        $templates = [
            ['offset_weeks' => -2, 'status' => Status::COMPLETED],
            ['offset_weeks' => -1, 'status' => Status::COMPLETED],
            ['offset_weeks' => 0, 'status' => Status::IN_PROGRESS],
            ['offset_weeks' => 1, 'status' => Status::UPCOMING],
        ];

        foreach ($churches as $churchIndex => $church) {
            foreach ($templates as $template) {
                $startsAt = $baseSaturday->copy()->addWeeks($template['offset_weeks']);
                $endsAt = $startsAt->copy()->addDay()->setTime(23, 59);

                Weekend::query()->updateOrCreate(
                    [
                        'church_id' => $church->id,
                        'starts_at' => $startsAt->format('Y-m-d H:i:s'),
                    ],
                    [
                        'name' => sprintf('FDS MISA %s #%d', $startsAt->format('Ymd'), $churchIndex + 1),
                        'ends_at' => $endsAt->format('Y-m-d H:i:s'),
                        'status' => $template['status'],
                        'created_by' => $superadmin->id,
                        'updated_by' => $superadmin->id,
                    ]
                );
            }
        }

        $this->command?->info('Fines de semana de misas creados exitosamente.');
    }
}
