<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MassesSeeder extends Seeder
{
	public function run(): void
	{
		$this->call([
			MassWeekendSeeder::class,
			MassSeeder::class,
			MassAttendanceSeeder::class,
			MassAttendanceIncidentSeeder::class,
		]);

		$this->command?->info('Seeders del modulo de misas ejecutados exitosamente.');
	}
}
