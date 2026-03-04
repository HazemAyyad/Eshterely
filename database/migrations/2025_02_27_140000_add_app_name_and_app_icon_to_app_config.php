<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_config', function (Blueprint $table) {
            $table->string('app_name', 100)->nullable()->after('development_mode');
            $table->string('app_icon_url', 500)->nullable()->after('app_name');
        });
    }

    public function down(): void
    {
        Schema::table('app_config', function (Blueprint $table) {
            $table->dropColumn(['app_name', 'app_icon_url']);
        });
    }
};
