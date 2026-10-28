<?php

namespace Database\Seeders;

use App\Models\Ecclesiastes\Church;
use App\Models\Settings\Setting;
use App\Models\Settings\SettingValue;
use App\Models\User;
use App\Services\Masses\AttendanceReportService;
use Illuminate\Database\Seeder;

/**
 * Fija el límite de faltas por parroquia. El valor global (3) aplica a
 * Coatepec Harinas; Ixtapan de la Sal permite 4 faltas.
 */
class AttendanceAbsenceLimitSeeder extends Seeder
{
    /** @var array<string, int> Municipio (o fragmento del nombre de la parroquia) => límite de faltas. */
    private const CHURCH_LIMITS = [
        'IXTAPAN DE LA SAL' => 4,
    ];

    public function run(): void
    {
        $setting = Setting::query()->where('key', AttendanceReportService::MAX_ABSENCES_SETTING)->first();

        if (! $setting) {
            $this->command?->warn('Ajuste de límite de faltas no encontrado. Ejecuta SettingsSeeder primero.');

            return;
        }

        $superadmin = User::query()->where('email', 'd.g.adrian28@gmail.com')->first();

        foreach (self::CHURCH_LIMITS as $fragment => $limit) {
            $churches = Church::query()
                ->where(function ($query) use ($fragment): void {
                    $query->whereHas('municipality', fn ($municipality) => $municipality->where('name', 'like', "%{$fragment}%"))
                        ->orWhere('name', 'like', "%{$fragment}%");
                })
                ->get();

            if ($churches->isEmpty()) {
                $this->command?->warn("No se encontró una parroquia que coincida con «{$fragment}».");

                continue;
            }

            foreach ($churches as $church) {
                SettingValue::query()->updateOrCreate(
                    [
                        'setting_id' => $setting->id,
                        'scope' => SettingValue::SCOPE_CHURCH,
                        'scope_id' => $church->id,
                    ],
                    [
                        'value' => $limit,
                        'created_by' => $superadmin?->id,
                        'updated_by' => $superadmin?->id,
                    ]
                );

                $this->command?->info("Límite de faltas de {$church->name} fijado en {$limit}.");
            }
        }
    }
}
