<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('featured_stores', function (Blueprint $table) {
            if (!Schema::hasColumn('featured_stores', 'categories')) {
                $table->text('categories')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('featured_stores', function (Blueprint $table) {
            if (Schema::hasColumn('featured_stores', 'categories')) {
                $table->dropColumn('categories');
            }
        });
    }
};

