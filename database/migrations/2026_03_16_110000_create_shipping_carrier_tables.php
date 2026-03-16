<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // In case a previous failed migration left partial tables, drop them first.
        Schema::dropIfExists('shipping_carrier_rates');
        Schema::dropIfExists('shipping_carrier_zones');

        Schema::create('shipping_carrier_zones', function (Blueprint $table) {
            $table->id();
            $table->string('carrier', 20); // dhl, ups, fedex, etc.
            $table->string('origin_country', 2)->nullable();
            $table->string('destination_country', 2);
            $table->string('zone_code', 50);
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->unsignedBigInteger('updated_by_admin_id')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['carrier', 'destination_country'], 'scz_carrier_dest_idx');
            $table->index(['carrier', 'origin_country', 'destination_country'], 'scz_carrier_origin_dest_idx');
        });

        Schema::create('shipping_carrier_rates', function (Blueprint $table) {
            $table->id();
            $table->string('carrier', 20);
            $table->string('zone_code', 50);
            $table->string('pricing_mode', 20)->default('direct'); // warehouse | direct
            $table->decimal('weight_min_kg', 10, 3);
            $table->decimal('weight_max_kg', 10, 3)->nullable();
            $table->decimal('base_rate', 10, 2);
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->unsignedBigInteger('updated_by_admin_id')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['carrier', 'zone_code'], 'scr_carrier_zone_idx');
            $table->index(['carrier', 'zone_code', 'pricing_mode'], 'scr_carrier_zone_mode_idx');
            $table->index(['carrier', 'zone_code', 'weight_min_kg', 'weight_max_kg'], 'scr_carrier_zone_weight_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_carrier_rates');
        Schema::dropIfExists('shipping_carrier_zones');
    }
};

