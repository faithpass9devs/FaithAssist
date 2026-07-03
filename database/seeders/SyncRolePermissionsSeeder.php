<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SyncRolePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $allActions = ['create', 'read', 'update', 'delete', 'show'];
        $readAndShowActions = ['read', 'show'];

        $superadmin = Role::query()->firstOrCreate([
            'name' => 'Superadmin',
            'guard_name' => 'web',
        ]);

        $coordinador = Role::query()->firstOrCreate([
            'name' => 'Coordinador',
            'guard_name' => 'web',
        ]);

        $catequista = Role::query()->firstOrCreate([
            'name' => 'Catequista',
            'guard_name' => 'web',
        ]);

        $capturista = Role::query()->firstOrCreate([
            'name' => 'Capturista',
            'guard_name' => 'web',
        ]);

        $superadmin->syncPermissions(
            Permission::query()
                ->whereNotIn('name', [
                    'estados.export',
                    'municipios.export',
                    'comunidades.export',
                ])
                ->get()
        );

        $coordinador->syncPermissions(array_merge(
            $this->permissionsForModules(['municipios'], $readAndShowActions),
            $this->permissionsForModules([
                'comunidades',
                'parroquias',
                'capillas',
                'periodos',
                'periodo_movimientos',
                'tipos_movimientos_periodo',
                'niveles',
                'children',
                'usuarios',
            ], $allActions),
            [
                'municipios.scope.all',
                'comunidades.scope.all',
                'parroquias.scope.all',
                'capillas.scope.all',
            ]
        ));

        $catequista->syncPermissions(
            $this->permissionsForModules(['parroquias', 'capillas', 'niveles', 'children'], $readAndShowActions)
        );

        $capturista->syncPermissions(
            $this->permissionsForModules(['capillas', 'niveles', 'children'], $readAndShowActions)
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return array<int, string>
     */
    private function permissionsForModules(array $modules, array $actions): array
    {
        $permissions = [];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                $permissions[] = "{$module}.{$action}";
            }
        }

        return $permissions;
    }
}
