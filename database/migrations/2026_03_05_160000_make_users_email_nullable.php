<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Allow phone-only registration: email is optional.
     * Skipped on SQLite (e.g. in-memory tests) to avoid driver-specific ALTER.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }
        DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NULL UNIQUE');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }
        DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NOT NULL UNIQUE');
    }
};
