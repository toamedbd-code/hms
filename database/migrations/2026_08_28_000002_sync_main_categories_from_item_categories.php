<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Main Categories are report headings and are managed independently
        // from Item Category/Category Type records.
    }

    public function down(): void
    {
        // Existing main categories are user data and are intentionally kept.
    }
};