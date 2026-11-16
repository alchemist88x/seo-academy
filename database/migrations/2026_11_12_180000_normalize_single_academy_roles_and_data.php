<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $rolesTable = config('permission.table_names.roles') ?: 'roles';
        $modelHasRolesTable = config('permission.table_names.model_has_roles') ?: 'model_has_roles';
        $rolePivotKey = config('permission.column_names.role_pivot_key') ?: 'role_id';
        $modelMorphKey = config('permission.column_names.model_morph_key') ?: 'model_id';

        if (Schema::hasTable($rolesTable)) {
            foreach (['admin', 'trainer', 'support_staff', 'student'] as $roleName) {
                $exists = DB::table($rolesTable)
                    ->where('name', $roleName)
                    ->where('guard_name', 'web')
                    ->exists();

                if (! $exists) {
                    DB::table($rolesTable)->insert([
                        'name' => $roleName,
                        'guard_name' => 'web',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            $adminRoleId = DB::table($rolesTable)
                ->where('name', 'admin')
                ->where('guard_name', 'web')
                ->value('id');

            $legacyRoleIds = DB::table($rolesTable)
                ->whereIn('name', ['platform_admin', 'academy_admin'])
                ->where('guard_name', 'web')
                ->pluck('id');

            if ($adminRoleId !== null && $legacyRoleIds->isNotEmpty() && Schema::hasTable($modelHasRolesTable)) {
                $legacyAssignments = DB::table($modelHasRolesTable)
                    ->whereIn($rolePivotKey, $legacyRoleIds)
                    ->get();

                foreach ($legacyAssignments as $assignment) {
                    $alreadyHasAdmin = DB::table($modelHasRolesTable)
                        ->where($rolePivotKey, $adminRoleId)
                        ->where('model_type', $assignment->model_type)
                        ->where($modelMorphKey, $assignment->{$modelMorphKey})
                        ->exists();

                    if (! $alreadyHasAdmin) {
                        DB::table($modelHasRolesTable)->insert([
                            $rolePivotKey => $adminRoleId,
                            'model_type' => $assignment->model_type,
                            $modelMorphKey => $assignment->{$modelMorphKey},
                        ]);
                    }
                }

                DB::table($modelHasRolesTable)
                    ->whereIn($rolePivotKey, $legacyRoleIds)
                    ->delete();
            }

            DB::table($rolesTable)
                ->whereIn('name', ['platform_admin', 'academy_admin'])
                ->delete();
        }

        if (Schema::hasTable('invitations')) {
            DB::table('invitations')
                ->whereIn('role', ['academy_admin', 'platform_admin'])
                ->update(['role' => 'admin']);
        }

        if (Schema::hasTable('academy_users')) {
            DB::table('academy_users')
                ->whereIn('role', ['academy_admin', 'platform_admin'])
                ->update(['role' => 'admin']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Non-destructive normalization migration: intentionally a no-op on rollback.
    }
};
