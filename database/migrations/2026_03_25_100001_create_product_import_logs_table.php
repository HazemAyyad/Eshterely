<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_import_logs', function (Blueprint $table) {
            $table->id();
            $table->string('url', 2048);
            $table->string('store_key', 64)->index();
            $table->string('provider', 64)->default('pipeline');
            $table->unsignedTinyInteger('attempt_index')->default(0);
            $table->boolean('success')->default(false)->index();
            $table->boolean('partial_success')->default(false);
            $table->float('confidence')->nullable();
            $table->json('missing_fields')->nullable();
            $table->json('response_snapshot')->nullable();
            $table->string('error_message', 500)->nullable();
            $table->boolean('used_paid_provider')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_import_logs');
    }
};
