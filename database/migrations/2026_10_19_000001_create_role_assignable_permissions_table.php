<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        if (empty($tableNames)) {
            throw new RuntimeException('Spatie permission table names are not configured.');
        }

        if (!Schema::hasTable('role_assignable_permissions')) {
            Schema::create('role_assignable_permissions', function (Blueprint $table) use ($tableNames) {
                $table->unsignedBigInteger('role_id');
                $table->unsignedBigInteger('permission_id');

                $table->foreign('role_id')
                    ->references('id')
                    ->on($tableNames['roles'])
                    ->onDelete('cascade');
                $table->foreign('permission_id')
                    ->references('id')
                    ->on($tableNames['permissions'])
                    ->onDelete('cascade');
                $table->primary(['role_id', 'permission_id']);
            });
        }

        $rolePivot = config('permission.column_names.role_pivot_key') ?: 'role_id';
        $permissionPivot = config('permission.column_names.permission_pivot_key') ?: 'permission_id';

        $validRolePermissions = DB::table($tableNames['role_has_permissions'] . ' as role_permissions')
            ->join($tableNames['roles'] . ' as roles', 'roles.id', '=', 'role_permissions.' . $rolePivot)
            ->join($tableNames['permissions'] . ' as permissions', 'permissions.id', '=', 'role_permissions.' . $permissionPivot)
            ->select([
                'role_permissions.' . $rolePivot . ' as role_id',
                'role_permissions.' . $permissionPivot . ' as permission_id',
            ]);

        DB::table('role_assignable_permissions')->insertOrIgnoreUsing(
            ['role_id', 'permission_id'],
            $validRolePermissions
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('role_assignable_permissions');
    }
};
