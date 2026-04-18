<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_code_settings')) {
            Schema::create('customer_code_settings', function (Blueprint $table) {
                $table->id();
                $table->string('prefix', 16)->default('ESH');
                $table->unsignedTinyInteger('numeric_padding')->default(5);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('customer_code_settings') && DB::table('customer_code_settings')->count() === 0) {
            DB::table('customer_code_settings')->insert([
                'prefix' => 'ESH',
                'numeric_padding' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_code_settings');
    }
};
