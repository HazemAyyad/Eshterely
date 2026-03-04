<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('countries')) {
            return;
        }
        if (Schema::hasColumn('countries', 'dial_code')) {
            return;
        }
        Schema::table('countries', function (Blueprint $table) {
            if (Schema::hasColumn('countries', 'flag_emoji')) {
                $table->string('dial_code', 10)->nullable()->after('flag_emoji');
            } else {
                $table->string('dial_code', 10)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('countries') || !Schema::hasColumn('countries', 'dial_code')) {
            return;
        }
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn('dial_code');
        });
    }
};
