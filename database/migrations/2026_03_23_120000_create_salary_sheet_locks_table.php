<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('salary_sheet_locks', function (Blueprint $table) {
            $table->id();
            $table->string('month', 7)->unique();
            $table->boolean('is_locked')->default(false)->index();
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->string('lock_note')->nullable();
            $table->timestamps();

            $table->foreign('locked_by')
                ->references('id')
                ->on('admins')
                ->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('salary_sheet_locks');
    }
};
