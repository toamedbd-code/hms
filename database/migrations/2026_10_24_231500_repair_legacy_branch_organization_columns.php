<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('branches')) {
            return;
        }

        Schema::table('branches', function (Blueprint $table) {
            if (!Schema::hasColumn('branches', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('branches', 'timezone')) {
                $table->string('timezone', 64)->default('Asia/Dhaka')->after('name');
            }
            if (!Schema::hasColumn('branches', 'currency_code')) {
                $table->string('currency_code', 10)->default('BDT')->after('timezone');
            }
            if (!Schema::hasColumn('branches', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        $companyIdColumn = collect(Schema::getColumns('branches'))
            ->firstWhere('name', 'company_id');
        if ($companyIdColumn && !$companyIdColumn['nullable']) {
            Schema::table('branches', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->change();
            });
        }

        $companyId = DB::table('companies')->orderBy('id')->value('id');
        if ($companyId) {
            DB::table('branches')
                ->whereNull('company_id')
                ->update(['company_id' => $companyId]);
        }

        if (!Schema::hasIndex('branches', 'branches_company_id_index')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->index('company_id', 'branches_company_id_index');
            });
        }

        $hasCompanyForeignKey = collect(Schema::getForeignKeys('branches'))
            ->contains(fn (array $foreignKey) => $foreignKey['name'] === 'branches_company_id_foreign');

        if (!$hasCompanyForeignKey) {
            Schema::table('branches', function (Blueprint $table) {
                $table->foreign('company_id', 'branches_company_id_foreign')
                    ->references('id')
                    ->on('companies')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('branches') || !Schema::hasColumn('branches', 'company_id')) {
            return;
        }

        $hasCompanyForeignKey = collect(Schema::getForeignKeys('branches'))
            ->contains(fn (array $foreignKey) => $foreignKey['name'] === 'branches_company_id_foreign');

        if ($hasCompanyForeignKey) {
            Schema::table('branches', function (Blueprint $table) {
                $table->dropForeign('branches_company_id_foreign');
            });
        }

        if (Schema::hasIndex('branches', 'branches_company_id_index')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->dropIndex('branches_company_id_index');
            });
        }

        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('company_id');
        });
    }
};
