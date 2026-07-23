<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = [
            [
                'name' => 'Superadmin',
                'description' => 'Acceso total al sistema',
            ],
            [
                'name' => 'Coordinador de Parroquia',
                'description' => 'Gestion operativa de parroquias y capillas',
            ],
            // [
            //     'name' => 'Catequista',
            //     'description' => 'Gestion operativa de capillas',
            // ],
            [
                'name' => 'Capturista',
                'description' => 'Registrar nuevos niños',
            ],
        ];

        foreach ($roles as $roleData) {
            Role::query()->updateOrCreate(
                [
                    'name' => $roleData['name'],
                    'guard_name' => 'web',
                ],
                [
                    'description' => $roleData['description'],
                ]
            );
        }
    }
}
