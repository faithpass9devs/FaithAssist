<?php

namespace Database\Seeders;

use App\Models\Ecclesiastes\Church;
use App\Models\Masses\Mass;
use App\Models\Masses\Weekend;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class MassSeeder extends Seeder
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
            ->first();

        if (! $church) {
            $this->command?->warn('No se encontro la parroquia objetivo para crear misas.');

            return;
        }

        // Keep masses only for the target parish.
        Mass::query()
            ->where('church_id', '!=', $church->id)
            ->delete();

        $weekends = Weekend::query()
            ->where('church_id', $church->id)
            ->where('name', 'like', 'SEMANA %')
            ->orderBy('starts_at')
            ->get();

        if ($weekends->isEmpty()) {
            $this->command?->warn('No hay fines de semana para crear misas. Ejecuta MassWeekendSeeder primero.');

            return;
        }

        $schedule = [
            ['day_offset' => 0, 'time_12h' => '07:00 PM', 'label' => 'MISA DE LAS 7PM'],
            ['day_offset' => 1, 'time_12h' => '06:00 AM', 'label' => 'MISA DE LAS 6AM'],
            ['day_offset' => 1, 'time_12h' => '08:00 AM', 'label' => 'MISA DE LAS 8AM'],
            ['day_offset' => 1, 'time_12h' => '10:00 AM', 'label' => 'MISA DE LAS 10AM'],
            ['day_offset' => 1, 'time_12h' => '12:00 AM', 'label' => 'MISA DE LAS 12AM'],
            ['day_offset' => 1, 'time_12h' => '05:00 PM', 'label' => 'MISA DE LAS 5PM'],
            ['day_offset' => 1, 'time_12h' => '07:00 PM', 'label' => 'MISA DE LAS 7PM'],
        ];

        foreach ($weekends as $weekend) {
            $expectedStarts = [];

            foreach ($schedule as $item) {
                $parsedTime = Carbon::createFromFormat('h:i A', $item['time_12h']);
                $startsAt = $weekend->starts_at
                    ->copy()
                    ->addDays($item['day_offset'])
                    ->setTime($parsedTime->hour, $parsedTime->minute);

                $expectedStarts[] = $startsAt->format('Y-m-d H:i:s');

                Mass::query()->updateOrCreate(
                    [
                        'weekend_id' => $weekend->id,
                        'starts_at' => $startsAt->format('Y-m-d H:i:s'),
                    ],
                    [
                        'church_id' => $weekend->church_id,
                        'chapel_id' => null,
                        'name' => $item['label'],
                        'ends_at' => $startsAt->copy()->addHour()->format('Y-m-d H:i:s'),
                        'status' => $weekend->status,
                        'attendance_check_in_status' => $weekend->status,
                        'attendance_check_out_status' => $weekend->status,
                        'notes' => 'Misa programada de prueba por horario fijo.',
                        'created_by' => $superadmin->id,
                        'updated_by' => $superadmin->id,
                    ]
                );
            }

            Mass::query()
                ->where('weekend_id', $weekend->id)
                ->whereNotIn('starts_at', $expectedStarts)
                ->delete();
        }

        // Remove masses from the target parish that are not linked to seeded weekends.
        Mass::query()
            ->where('church_id', $church->id)
            ->whereNotIn('weekend_id', $weekends->pluck('id'))
            ->delete();

        $this->command?->info('Misas de prueba creadas exitosamente.');
    }
}
