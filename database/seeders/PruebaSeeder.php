<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PruebaSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SettingCategorySeeder::class,
            SettingsSeeder::class,
            PermissionsSeeder::class,
            SyncRolePermissionsSeeder::class,
            IxtapanSalSeeder::class,
            AssignPeriodsToCoatepecSeeder::class,
        ]);
    }
}
