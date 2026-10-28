<?php

namespace Database\Seeders;

use App\Models\Settings\Setting;
use App\Models\Settings\SettingCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class StaffBadgeSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $superadmin = User::query()->where('email', 'd.g.adrian28@gmail.com')->first();
        $badge = SettingCategory::query()->where('key', 'staff_badge')->first();

        if (! $badge) {
            $this->command?->warn('Categoria staff_badge no encontrada. Ejecuta SettingCategorySeeder primero.');

            return;
        }

        $settings = [
            [
                'key' => 'staff_badge.logo_image',
                'name' => 'Logo del gafete de personal',
                'description' => 'Imagen del logo para el gafete del personal.',
                'type' => 'file',
                'default_value' => null,
                'meta' => [
                    'accept' => '.png,.jpg,.jpeg',
                    'max_size_kb' => 2048,
                    'storage_scope' => 'badge',
                ],
                'sort_order' => 101,
            ],
            [
                'key' => 'staff_badge.background_1',
                'name' => 'Fondo del gafete de personal',
                'description' => 'Imagen de fondo de una página para gafete de personal.',
                'type' => 'file',
                'default_value' => null,
                'meta' => [
                    'accept' => '.png,.jpg,.jpeg',
                    'max_size_kb' => 4096,
                    'storage_scope' => 'badge',
                ],
                'sort_order' => 102,
            ],
            [
                'key' => 'staff_badge.title_text',
                'name' => 'Título del gafete de personal',
                'description' => 'Título principal mostrado en el gafete de personal.',
                'type' => 'string',
                'default_value' => 'Gafete de personal',
                'meta' => [
                    'validation' => ['string', 'min:1', 'max:60'],
                    'help' => 'Máximo 60 caracteres.',
                    'placeholder' => 'Gafete de personal',
                ],
                'sort_order' => 103,
            ],
            [
                'key' => 'staff_badge.accent_color',
                'name' => 'Color de acento del gafete de personal',
                'description' => 'Color de título y acentos del gafete de personal.',
                'type' => 'color',
                'default_value' => '#2563eb',
                'meta' => ['validation' => ['string', 'max:7'], 'help' => 'Formato hexadecimal, ej. #2563eb.'],
                'sort_order' => 104,
            ],
            [
                'key' => 'staff_badge.body_text_color',
                'name' => 'Color de texto del gafete de personal',
                'description' => 'Color del texto principal del gafete de personal.',
                'type' => 'color',
                'default_value' => '#111827',
                'meta' => ['validation' => ['string', 'max:7'], 'help' => 'Formato hexadecimal.'],
                'sort_order' => 105,
            ],
            [
                'key' => 'staff_badge.qr_border_color',
                'name' => 'Borde del QR del gafete de personal',
                'description' => 'Color del borde del recuadro del código QR.',
                'type' => 'color',
                'default_value' => '#d1d5db',
                'meta' => ['validation' => ['string', 'max:7'], 'help' => 'Formato hexadecimal.'],
                'sort_order' => 106,
            ],
            [
                'key' => 'staff_badge.chip_bg_color',
                'name' => 'Fondo del código del gafete de personal',
                'description' => 'Color de fondo del chip con el código único.',
                'type' => 'color',
                'default_value' => '#111827',
                'meta' => ['validation' => ['string', 'max:7'], 'help' => 'Formato hexadecimal.'],
                'sort_order' => 107,
            ],
            [
                'key' => 'staff_badge.chip_text_color',
                'name' => 'Texto del código del gafete de personal',
                'description' => 'Color del texto dentro del chip de código único.',
                'type' => 'color',
                'default_value' => '#ffffff',
                'meta' => ['validation' => ['string', 'max:7'], 'help' => 'Formato hexadecimal.'],
                'sort_order' => 108,
            ],
        ];

        foreach ($settings as $setting) {
            Setting::query()->updateOrCreate(
                ['key' => $setting['key']],
                [
                    'category_id' => $badge->id,
                    'name' => $setting['name'],
                    'description' => $setting['description'],
                    'type' => $setting['type'],
                    'default_value' => $setting['default_value'],
                    'meta' => $setting['meta'],
                    'sort_order' => $setting['sort_order'],
                    'is_active' => true,
                    'created_by' => $superadmin?->id,
                    'updated_by' => $superadmin?->id,
                ]
            );
        }
    }
}
