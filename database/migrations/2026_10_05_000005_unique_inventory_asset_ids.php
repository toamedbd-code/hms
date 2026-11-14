<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('store_item_lots')) {
            return;
        }

        Schema::table('store_item_lots', function (Blueprint $table) {
            if (!Schema::hasIndex('store_item_lots', 'store_item_lots_serial_number_unique')) {
                $table->unique('serial_number');
            }
            if (!Schema::hasIndex('store_item_lots', 'store_item_lots_asset_tag_unique')) {
                $table->unique('asset_tag');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('store_item_lots')) {
            return;
        }

        Schema::table('store_item_lots', function (Blueprint $table) {
            $table->dropUnique('store_item_lots_serial_number_unique');
            $table->dropUnique('store_item_lots_asset_tag_unique');
        });
    }
};