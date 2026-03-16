<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fulfillment timeline events per shipment (carrier API–ready structure).
     */
    public function up(): void
    {
        Schema::create('order_shipment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_shipment_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 80);
            $table->string('event_label')->nullable();
            $table->timestamp('event_time')->nullable();
            $table->string('location')->nullable();
            $table->json('payload')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('order_shipment_events', function (Blueprint $table) {
            $table->index(['order_shipment_id', 'event_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_shipment_events');
    }
};
