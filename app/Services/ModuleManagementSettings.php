<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ModuleManagementSettings
{
    public function isLabManaged(): bool
    {
        $value = DB::table('module_management_settings')
            ->where('module_key', 'laboratory')
            ->value('is_managed');

        return $value === null ? true : (bool) $value;
    }

    public function setLabManaged(bool $managed, ?int $updatedBy): void
    {
        $values = [
                'is_managed' => $managed,
                'updated_by' => $updatedBy,
                'updated_at' => now(),
            ];
        $exists = DB::table('module_management_settings')->where('module_key', 'laboratory')->exists();
        if ($exists) {
            DB::table('module_management_settings')->where('module_key', 'laboratory')->update($values);
            return;
        }

        DB::table('module_management_settings')->insert([
            ...$values,
            'module_key' => 'laboratory',
            'created_at' => now(),
        ]);
    }
}
