<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('featured_stores', function (Blueprint $table) {
            if (! Schema::hasColumn('featured_stores', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('is_featured');
            }
        });
    }

    public function down(): void
    {
        Schema::table('featured_stores', function (Blueprint $table) {
            if (Schema::hasColumn('featured_stores', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};
