<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_setting_audits', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->timestamps();

            $table->index(['key', 'created_at']);
            $table->index('admin_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_setting_audits');
    }
};

