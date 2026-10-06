<?php

namespace Database\Seeders;

use App\Models\Settings\SettingCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class SettingCategorySeeder extends Seeder
{
    public function run(): void
    {
        $superadmin = User::query()->where('email', 'superadmin@faithassistqr.test')->first();

        $categories = [
            [
                'key' => 'badge',
                'name' => 'Gafete',
                'description' => 'Personalización del gafete de catequesis (imágenes, textos y colores).',
                'icon' => 'BadgeCheck',
                'sort_order' => 1,
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            SettingCategory::query()->updateOrCreate(
                ['key' => $category['key']],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'icon' => $category['icon'],
                    'sort_order' => $category['sort_order'],
                    'is_active' => $category['is_active'],
                    'created_by' => $superadmin?->id,
                    'updated_by' => $superadmin?->id,
                ]
            );
        }
    }
}
