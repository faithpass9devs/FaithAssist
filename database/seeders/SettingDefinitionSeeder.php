<?php

namespace Database\Seeders;

use App\Globals\SettingType;
use App\Globals\Status;
use App\Models\Settings\SettingDefinition;
use App\Models\User;
use Illuminate\Database\Seeder;

class SettingDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $superadmin = User::query()->where('email', 'superadmin@faithassistqr.test')->first();

        foreach ($this->definitions() as $definition) {
            SettingDefinition::query()->updateOrCreate(
                ['key' => $definition['key']],
                [
                    ...$definition,
                    'created_by' => $superadmin?->id,
                    'updated_by' => $superadmin?->id,
                ]
            );
        }
    }

    private function definitions(): array
    {
        return [
            [
                'key' => 'show_apps',
                'name' => 'Mostrar aplicaciones',
                'description' => 'Permite controlar si los usuarios de la parroquia pueden ver las aplicaciones disponibles.',
                'status' => Status::ACTIVE,
                'type_data' => SettingType::BOOLEAN,
                'meta' => [
                    'default' => true,
                ],
            ],
            [
                'key' => 'church_logo',
                'name' => 'Logo de la parroquia',
                'description' => 'Imagen que identifica visualmente a la parroquia dentro del sistema.',
                'status' => Status::ACTIVE,
                'type_data' => SettingType::FILE,
                'meta' => [
                    'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
                    'mimetypes' => ['image/jpeg', 'image/png', 'image/webp'],
                    'max_kb' => 2048,
                    'accept' => 'image/jpeg,image/png,image/webp',
                ],
            ],
        ];
    }
}
