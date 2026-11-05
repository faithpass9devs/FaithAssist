<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Unifica los nombres de roles heredados con los nombres canónicos usados
     * por la aplicación. Si el rol canónico ya existe, se combinan usuarios y
     * permisos para evitar duplicados y pérdida de asignaciones.
     */
    public function up(): void
    {
        $roleTable = config('permission.table_names.roles', 'roles');
        $modelHasRolesTable = config('permission.table_names.model_has_roles', 'model_has_roles');
        $roleHasPermissionsTable = config('permission.table_names.role_has_permissions', 'role_has_permissions');
        $rolePivotKey = config('permission.column_names.role_pivot_key') ?: 'role_id';
        $permissionPivotKey = config('permission.column_names.permission_pivot_key') ?: 'permission_id';
        $modelMorphKey = config('permission.column_names.model_morph_key', 'model_id');

        $legacyRoles = [
            'SUPER-ADMIN' => 'Superadmin',
            'COORDINADOR DE PARROQUIA' => 'Coordinador de Parroquia',
            'CAPTURISTA' => 'Capturista',
        ];

        foreach ($legacyRoles as $legacyName => $canonicalName) {
            $legacyRole = DB::table($roleTable)
                ->where('guard_name', 'web')
                ->where('name', $legacyName)
                ->first();

            if (! $legacyRole) {
                continue;
            }

            $canonicalRole = DB::table($roleTable)
                ->where('guard_name', 'web')
                ->where('name', $canonicalName)
                ->first();

            if (! $canonicalRole || (int) $canonicalRole->id === (int) $legacyRole->id) {
                DB::table($roleTable)
                    ->where('id', $legacyRole->id)
                    ->update([
                        'name' => $canonicalName,
                        'updated_at' => now(),
                    ]);

                continue;
            }

            $assignments = DB::table($modelHasRolesTable)
                ->where($rolePivotKey, $legacyRole->id)
                ->get();

            foreach ($assignments as $assignment) {
                DB::table($modelHasRolesTable)->insertOrIgnore([
                    $rolePivotKey => $canonicalRole->id,
                    'model_type' => $assignment->model_type,
                    $modelMorphKey => $assignment->{$modelMorphKey},
                ]);
            }

            $permissionIds = DB::table($roleHasPermissionsTable)
                ->where($rolePivotKey, $legacyRole->id)
                ->pluck($permissionPivotKey);

            foreach ($permissionIds as $permissionId) {
                DB::table($roleHasPermissionsTable)->insertOrIgnore([
                    $permissionPivotKey => $permissionId,
                    $rolePivotKey => $canonicalRole->id,
                ]);
            }

            DB::table($modelHasRolesTable)
                ->where($rolePivotKey, $legacyRole->id)
                ->delete();

            DB::table($roleHasPermissionsTable)
                ->where($rolePivotKey, $legacyRole->id)
                ->delete();

            DB::table($roleTable)
                ->where('id', $legacyRole->id)
                ->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Esta migración de datos no se revierte para no recrear roles duplicados.
     */
    public function down(): void
    {
        //
    }
};
