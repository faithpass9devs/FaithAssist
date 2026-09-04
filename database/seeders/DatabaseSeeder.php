<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            LadaSeeder::class,
            RolesSeeder::class,
            PermissionsSeeder::class,
            SyncRolePermissionsSeeder::class,
            UsersPerRoleSeeder::class,
            ProfileSeeder::class,
            ModuleSeeder::class,
            SettingCategorySeeder::class,
            SettingsSeeder::class,
            StateSeeder::class,
            DioceseSeeder::class,
            MunicipalitySeeder::class,
            CommunitySeeder::class,
            DeanerySeeder::class,
            ChurchSeeder::class,
            ChapelSeeder::class,
            PeriodMovementTypeSeeder::class,
            IncidenceTypeSeeder::class,
            LevelSeeder::class,
            PeriodSeeder::class,
            CatechismSeeder::class,
            MassWeekendSeeder::class,
            MassSeeder::class,
            MassAttendanceSeeder::class,
            MassAttendanceIncidentSeeder::class,
        ]);
    }
}
