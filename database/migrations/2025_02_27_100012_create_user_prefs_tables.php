<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_prefs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('push_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->boolean('sms_enabled')->default(false);
            $table->boolean('live_status_updates')->default(true);
            $table->boolean('smart_filter')->default(true);
            $table->boolean('duty_tax_payments')->default(true);
            $table->boolean('document_requests')->default(true);
            $table->boolean('payment_failed')->default(true);
            $table->boolean('mute_all_marketing')->default(false);
            $table->boolean('quiet_hours_enabled')->default(true);
            $table->string('quiet_hours_from', 5)->default('22:00');
            $table->string('quiet_hours_to', 5)->default('07:00');
            $table->timestamps();
        });

        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('language_code', 10)->default('en');
            $table->string('currency_code', 10)->default('USD');
            $table->string('default_warehouse_id')->nullable();
            $table->string('default_warehouse_label')->nullable();
            $table->boolean('smart_consolidation_enabled')->default(true);
            $table->boolean('auto_insurance_enabled')->default(false);
            $table->string('server_region')->nullable();
            $table->timestamps();
        });

        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_name')->nullable();
            $table->string('location')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->string('client_info')->nullable();
            $table->string('token_hash')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
        Schema::dropIfExists('user_settings');
        Schema::dropIfExists('notification_prefs');
    }
};
