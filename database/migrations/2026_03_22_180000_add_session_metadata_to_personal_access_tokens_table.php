<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            if (! Schema::hasColumn('personal_access_tokens', 'device_type')) {
                $table->string('device_type', 40)->nullable()->after('name');
            }
            if (! Schema::hasColumn('personal_access_tokens', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('device_type');
            }
            if (! Schema::hasColumn('personal_access_tokens', 'location_label')) {
                $table->string('location_label', 191)->nullable()->after('ip_address');
            }
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            if (Schema::hasColumn('personal_access_tokens', 'location_label')) {
                $table->dropColumn('location_label');
            }
            if (Schema::hasColumn('personal_access_tokens', 'ip_address')) {
                $table->dropColumn('ip_address');
            }
            if (Schema::hasColumn('personal_access_tokens', 'device_type')) {
                $table->dropColumn('device_type');
            }
        });
    }
};
