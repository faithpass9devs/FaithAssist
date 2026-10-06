<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $modules = [
            'modulos' => 'security',
            'permisos' => 'security',
            'dispositivos_sesiones' => 'security',
            'moderacion_cuentas' => 'security',
            'estados' => 'regions',
            'municipios' => 'regions',
            'comunidades' => 'regions',
            'diocesis' => 'ecclesiastes',
            'decanato' => 'ecclesiastes',
            'parroquias' => 'ecclesiastes',
            'capillas' => 'ecclesiastes',
            'periodos' => 'operation',
            'periodo_movimientos' => 'operation',
            'tipos_movimientos_periodo' => 'operation',
            'niveles' => 'operation',
            'children' => 'catechism',
            'reinscripciones' => 'catechism',
            'externos' => 'catechism',
            'weekends' => 'masses',
            'masses' => 'masses',
            'mass_attendance' => 'masses',
            'asistencias_manuales' => 'masses',
            'tipos_incidencias' => 'masses',
            'incidencias_asistencia' => 'masses',
            'roles' => 'security',
            'usuarios' => 'security',
        ];

        $actions = ['create', 'read', 'update', 'delete', 'show'];

        foreach ($modules as $module => $moduleKey) {
            foreach ($actions as $action) {
                Permission::query()->updateOrCreate(
                    [
                        'name' => "{$module}.{$action}",
                        'guard_name' => 'web',
                    ],
                    [
                        'description' => "Permite {$action} en {$module}",
                        'module_key' => $moduleKey,
                        'arg' => null,
                    ]
                );
            }
        }

        foreach ([
            ['name' => 'estados.export', 'module_key' => 'regions', 'description' => 'Permite exportar estados a Excel'],
            ['name' => 'municipios.scope.all', 'module_key' => 'regions', 'description' => 'Permite ver todos los municipios'],
            ['name' => 'municipios.export', 'module_key' => 'regions', 'description' => 'Permite exportar municipios a Excel'],
            ['name' => 'comunidades.scope.all', 'module_key' => 'regions', 'description' => 'Permite ver todas las comunidades'],
            ['name' => 'comunidades.export', 'module_key' => 'regions', 'description' => 'Permite exportar comunidades a Excel'],
            ['name' => 'parroquias.scope.all', 'module_key' => 'ecclesiastes', 'description' => 'Permite ver todas las parroquias'],
            ['name' => 'capillas.scope.all', 'module_key' => 'ecclesiastes', 'description' => 'Permite ver todas las capillas'],
            ['name' => 'children.scope.all', 'module_key' => 'catechism', 'description' => 'Permite ver todos los niños'],
            ['name' => 'children.export', 'module_key' => 'catechism', 'description' => 'Permite exportar niños a Excel'],
            ['name' => 'reinscripciones.scope.all', 'module_key' => 'catechism', 'description' => 'Permite ver todas las reinscripciones'],
            ['name' => 'externos.scope.all', 'module_key' => 'catechism', 'description' => 'Permite ver todos los externos'],
            ['name' => 'externos.import', 'module_key' => 'catechism', 'description' => 'Permite importar externos al módulo de niños'],
            ['name' => 'externos.import_all', 'module_key' => 'catechism', 'description' => 'Permite importar masivamente externos al módulo de niños'],
            ['name' => 'weekends.scope.all', 'module_key' => 'masses', 'description' => 'Permite ver todos los fines de semana de misas'],
            ['name' => 'masses.scope.all', 'module_key' => 'masses', 'description' => 'Permite ver todas las misas'],
            ['name' => 'mass_attendance.scope.all', 'module_key' => 'masses', 'description' => 'Permite ver todas las asistencias a misas'],
            ['name' => 'mass_attendance.scan', 'module_key' => 'masses', 'description' => 'Permite capturar códigos QR de asistencia a misas'],
            ['name' => 'mass_attendance.manage', 'module_key' => 'masses', 'description' => 'Permite iniciar y terminar la captura de entrada y salida de asistencias a misas'],
            ['name' => 'asistencias_manuales.scope.all', 'module_key' => 'masses', 'description' => 'Permite ver todas las asistencias manuales'],
            ['name' => 'incidencias_asistencia.scope.all', 'module_key' => 'masses', 'description' => 'Permite ver todas las incidencias de asistencia'],
            ['name' => 'reinscripciones.export', 'module_key' => 'catechism', 'description' => 'Permite exportar reinscripciones a Excel'],
        ] as $permission) {
            Permission::query()->updateOrCreate(
                [
                    'name' => $permission['name'],
                    'guard_name' => 'web',
                ],
                [
                    'description' => $permission['description'],
                    'module_key' => $permission['module_key'],
                    'arg' => null,
                ]
            );
        }

        Permission::query()
            ->where('guard_name', 'web')
            ->where('name', 'like', 'whatsapp.%')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
