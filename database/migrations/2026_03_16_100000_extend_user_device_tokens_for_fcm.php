<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Production-ready FCM token fields: platform, device_name, app_version, last_seen_at, is_active.
     */
    public function up(): void
    {
        Schema::table('user_device_tokens', function (Blueprint $table) {
            $table->string('platform', 30)->nullable()->after('device_type');
            $table->string('device_name', 100)->nullable()->after('platform');
            $table->string('app_version', 50)->nullable()->after('device_name');
            $table->timestamp('last_seen_at')->nullable()->after('app_version');
            $table->boolean('is_active')->default(true)->after('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_device_tokens', function (Blueprint $table) {
            $table->dropColumn(['platform', 'device_name', 'app_version', 'last_seen_at', 'is_active']);
        });
    }
};
