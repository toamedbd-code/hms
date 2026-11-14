<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tests')
            ->whereNull('main_category_id')
            ->orderBy('id')
            ->get(['id', 'test_category_id'])
            ->each(function (object $test): void {
                $categoryName = DB::table('testcategories')
                    ->where('id', $test->test_category_id)
                    ->value('name');

                $categoryName = trim((string) $categoryName);
                if ($categoryName === '') {
                    return;
                }

                $mainCategory = DB::table('main_categories')
                    ->whereRaw('LOWER(name) = ?', [strtolower($categoryName)])
                    ->first();

                if ($mainCategory) {
                    if ($mainCategory->deleted_at !== null || $mainCategory->status !== 'Active') {
                        DB::table('main_categories')
                            ->where('id', $mainCategory->id)
                            ->update([
                                'status' => 'Active',
                                'deleted_at' => null,
                                'updated_at' => now(),
                            ]);
                    }
                } else {
                    $mainCategoryId = DB::table('main_categories')->insertGetId([
                        'name' => $categoryName,
                        'status' => 'Active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $mainCategory = (object) ['id' => $mainCategoryId];
                }

                DB::table('tests')
                    ->where('id', $test->id)
                    ->update([
                        'main_category_id' => $mainCategory->id,
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        // Keep the migrated relationships intact when rolling back schema changes.
    }
};
