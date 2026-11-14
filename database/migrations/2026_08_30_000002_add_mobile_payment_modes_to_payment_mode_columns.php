<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $tables = [
        'pathologies',
        'pharmacybills',
        'bloodissues',
        'bloodcomponentissues',
        'appoinments',
        'opdpatients',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }

            $current = $this->getEnumValues($table, 'payment_mode');
            if (empty($current)) {
                continue;
            }

            $toAdd = ['Bkash', 'Nagad'];
            $merged = array_unique(array_merge($current, $toAdd));

            $this->changeEnum($table, 'payment_mode', $merged);
        }
    }

    public function down(): void
    {
        // Removing enum values is unsafe if data exists; skip rollback.
    }

    private function tableExists(string $table): bool
    {
        return DB::getSchemaBuilder()->hasTable($table);
    }

    private function getEnumValues(string $table, string $column): array
    {
        $result = DB::selectOne(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?",
            [$table, $column]
        );

        if (!$result) {
            return [];
        }

        preg_match_all("/'([^']+)'/", $result->COLUMN_TYPE, $matches);

        return $matches[1] ?? [];
    }

    private function changeEnum(string $table, string $column, array $values): void
    {
        $escaped = implode(',', array_map(fn($v) => "'" . addslashes($v) . "'", $values));
        DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` ENUM({$escaped})");
    }
};
