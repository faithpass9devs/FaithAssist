<?php

namespace Database\Seeders;

use App\Globals\Status;
use App\Models\Ecclesiastes\Church;
use App\Models\Masses\Weekend;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class MassWeekendSeeder extends Seeder
{
    public function run(): void
    {
        $superadmin = User::query()->where('email', 'superadmin@faithassistqr.test')->first();

        if (! $superadmin) {
            $this->command?->warn('No se encontro el usuario Superadmin. Ejecuta UsersPerRoleSeeder primero.');

            return;
        }

        $church = Church::query()
            ->where('name', 'PARROQUIA NUESTRA SENORA DE LA ASUNCION')
            ->where('status', Status::ACTIVE)
            ->first();

        if (! $church) {
            $this->command?->warn('No se encontro la parroquia objetivo para crear fines de semana de misas.');

            return;
        }

        $baseSaturday = Carbon::create(2026, 5, 2, 0, 0, 0);
        $lastSaturday = $baseSaturday->copy()->addWeeks(11);
        $desiredStarts = [];

        $weekNumber = 1;
        for ($startsAt = $baseSaturday->copy(); $startsAt->lessThanOrEqualTo($lastSaturday); $startsAt->addWeek()) {
            $weekStart = $startsAt->copy();
            $endsAt = $weekStart->copy()->addDay()->setTime(23, 59);
            $desiredStarts[] = $weekStart->format('Y-m-d H:i:s');

            $status = match (true) {
                now()->lt($weekStart) => Status::UPCOMING,
                now()->between($weekStart, $endsAt) => Status::IN_PROGRESS,
                default => Status::COMPLETED,
            };

            Weekend::query()->updateOrCreate(
                [
                    'church_id' => $church->id,
                    'starts_at' => $weekStart->format('Y-m-d H:i:s'),
                ],
                [
                    'name' => 'SEMANA '.$weekNumber,
                    'ends_at' => $endsAt->format('Y-m-d H:i:s'),
                    'status' => $status,
                    'created_by' => $superadmin->id,
                    'updated_by' => $superadmin->id,
                ]
            );

            $weekNumber++;
        }

        // Keep only generated weekends for the target parish.
        Weekend::query()
            ->where('church_id', $church->id)
            ->whereNotIn('starts_at', $desiredStarts)
            ->delete();

        // Keep weekends active only for the target parish.
        Weekend::query()
            ->where('church_id', '!=', $church->id)
            ->delete();

        $this->command?->info('Fines de semana de misas creados exitosamente.');
    }
}
