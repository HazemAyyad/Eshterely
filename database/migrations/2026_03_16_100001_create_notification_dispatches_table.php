<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Notification send history: bulk, individual, system-event. Traceable and auditable.
     */
    public function up(): void
    {
        Schema::create('notification_dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40); // bulk | individual | system_event
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->string('target_scope', 100)->nullable(); // all_users | user_ids | tokens
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('shipment_id')->nullable();
            $table->string('send_status', 30)->default('pending'); // pending | sent | partial | failed
            $table->text('provider_response_summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->json('meta')->nullable(); // target_type, target_id, route_key, payload for deep-link
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_dispatches');
    }
};
