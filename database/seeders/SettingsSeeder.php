<?php

namespace Database\Seeders;

use App\Models\Settings\Setting;
use App\Models\Settings\SettingCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $superadmin = User::query()->where('email', 'superadmin@faithassistqr.test')->first();
        $badge = SettingCategory::query()->where('key', 'badge')->first();

        if (! $badge) {
            $this->command?->warn('Categoria badge no encontrada. Ejecuta SettingCategorySeeder primero.');

            return;
        }

        $settings = [
            [
                'key' => 'badge.logo_image',
                'name' => 'Logo del gafete',
                'description' => 'Imagen del logo (sello) que aparece en ambas páginas.',
                'type' => 'file',
                'default_value' => null,
                'meta' => [
                    'accept' => '.png,.jpg,.jpeg',
                    'max_size_kb' => 2048,
                    'storage_scope' => 'badge',
                ],
                'sort_order' => 1,
            ],
            [
                'key' => 'badge.background_1',
                'name' => 'Fondo página 1',
                'description' => 'Imagen de fondo de la primera página del gafete.',
                'type' => 'file',
                'default_value' => null,
                'meta' => [
                    'accept' => '.png,.jpg,.jpeg',
                    'max_size_kb' => 4096,
                    'storage_scope' => 'badge',
                ],
                'sort_order' => 2,
            ],
            [
                'key' => 'badge.background_2',
                'name' => 'Fondo página 2',
                'description' => 'Imagen de fondo de la segunda página (tabla de asistencias).',
                'type' => 'file',
                'default_value' => null,
                'meta' => [
                    'accept' => '.png,.jpg,.jpeg',
                    'max_size_kb' => 4096,
                    'storage_scope' => 'badge',
                ],
                'sort_order' => 3,
            ],
            [
                'key' => 'badge.title_text',
                'name' => 'Título del gafete',
                'description' => 'Texto dentro del recuadro dorado.',
                'type' => 'string',
                'default_value' => 'Gafete de catequesis',
                'meta' => [
                    'validation' => ['string', 'min:1', 'max:60'],
                    'help' => 'Máximo 60 caracteres.',
                    'placeholder' => 'Gafete de catequesis',
                ],
                'sort_order' => 4,
            ],
            [
                'key' => 'badge.footer_text',
                'name' => 'Nota del pie',
                'description' => 'Texto informativo debajo del código único.',
                'type' => 'text',
                'default_value' => 'Para registrar su asistencia dominical, será necesario presentar el código QR asignado al momento de su llegada. Sin este código, no podrá ser validada su participación.',
                'meta' => [
                    'validation' => ['string', 'max:300'],
                    'help' => 'Máximo 300 caracteres.',
                ],
                'sort_order' => 5,
            ],
            [
                'key' => 'badge.accent_color',
                'name' => 'Color de acento',
                'description' => 'Color del recuadro y título del gafete.',
                'type' => 'color',
                'default_value' => '#d4af37',
                'meta' => ['validation' => ['string', 'max:7'], 'help' => 'Formato hexadecimal, ej. #d4af37.'],
                'sort_order' => 6,
            ],
            [
                'key' => 'badge.body_text_color',
                'name' => 'Color de texto',
                'description' => 'Color del texto principal (nombre y datos del niño).',
                'type' => 'color',
                'default_value' => '#111827',
                'meta' => ['validation' => ['string', 'max:7'], 'help' => 'Formato hexadecimal.'],
                'sort_order' => 7,
            ],
            [
                'key' => 'badge.qr_border_color',
                'name' => 'Borde del QR',
                'description' => 'Color del borde del recuadro del código QR.',
                'type' => 'color',
                'default_value' => '#d1d5db',
                'meta' => ['validation' => ['string', 'max:7'], 'help' => 'Formato hexadecimal.'],
                'sort_order' => 8,
            ],
            [
                'key' => 'badge.chip_bg_color',
                'name' => 'Fondo del código',
                'description' => 'Color de fondo del chip con el código único.',
                'type' => 'color',
                'default_value' => '#111827',
                'meta' => ['validation' => ['string', 'max:7'], 'help' => 'Formato hexadecimal.'],
                'sort_order' => 9,
            ],
            [
                'key' => 'badge.chip_text_color',
                'name' => 'Texto del código',
                'description' => 'Color del texto dentro del chip del código único.',
                'type' => 'color',
                'default_value' => '#ffffff',
                'meta' => ['validation' => ['string', 'max:7'], 'help' => 'Formato hexadecimal.'],
                'sort_order' => 10,
            ],
            [
                'key' => 'badge.table_header_color',
                'name' => 'Encabezado de tabla',
                'description' => 'Color del encabezado de la tabla de asistencias.',
                'type' => 'color',
                'default_value' => '#d892ad',
                'meta' => ['validation' => ['string', 'max:7'], 'help' => 'Formato hexadecimal.'],
                'sort_order' => 11,
            ],
            [
                'key' => 'badge.table_border_color',
                'name' => 'Borde de tabla',
                'description' => 'Color de los bordes de la tabla de asistencias.',
                'type' => 'color',
                'default_value' => '#e5b7c6',
                'meta' => ['validation' => ['string', 'max:7'], 'help' => 'Formato hexadecimal.'],
                'sort_order' => 12,
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
