<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('invoicedesigns', function (Blueprint $table) {
            if (!Schema::hasColumn('invoicedesigns', 'header_height')) {
                $table->unsignedSmallInteger('header_height')->default(115)->after('header_photo_path');
            }
            if (!Schema::hasColumn('invoicedesigns', 'footer_height')) {
                $table->unsignedSmallInteger('footer_height')->default(70)->after('footer_photo_path');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('invoicedesigns', function (Blueprint $table) {
            if (Schema::hasColumn('invoicedesigns', 'header_height')) {
                $table->dropColumn('header_height');
            }
            if (Schema::hasColumn('invoicedesigns', 'footer_height')) {
                $table->dropColumn('footer_height');
            }
        });
    }
};
