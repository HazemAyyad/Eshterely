<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_settings', function (Blueprint $table) {
            $table->id();

            // Active/default gateway selection
            $table->string('default_gateway', 50)->default('square'); // square|stripe

            // Square settings
            $table->boolean('square_enabled')->default(true);
            $table->string('square_environment', 30)->default('sandbox'); // sandbox|production
            $table->string('square_application_id', 100)->nullable();
            $table->text('square_access_token')->nullable();
            $table->string('square_location_id', 100)->nullable();
            $table->text('square_webhook_signature_key')->nullable();
            $table->string('square_webhook_notification_url', 500)->nullable();

            // Stripe settings
            $table->boolean('stripe_enabled')->default(false);
            $table->string('stripe_environment', 20)->default('test'); // test|live
            $table->string('stripe_currency_default', 10)->nullable(); // optional
            $table->string('stripe_publishable_key', 200)->nullable();
            $table->text('stripe_secret_key')->nullable();
            $table->text('stripe_webhook_secret')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_settings');
    }
};

