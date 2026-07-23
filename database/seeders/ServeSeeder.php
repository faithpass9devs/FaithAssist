<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ServeSeeder extends Seeder
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
            StateSeeder::class,
            DioceseSeeder::class,
            MunicipalitySeeder::class,
            CommunitySeeder::class,
            DeanerySeeder::class,
            ChurchSeeder::class,
            PeriodMovementTypeSeeder::class,
            IncidenceTypeSeeder::class,
            LevelSeeder::class,
        ]);
    }
}