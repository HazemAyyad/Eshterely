<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->unique()->after('id'); // Required for new users
            $table->string('full_name')->nullable()->after('name');
            $table->string('display_name')->nullable()->after('full_name');
            $table->date('date_of_birth')->nullable()->after('display_name');
            $table->string('avatar_url')->nullable()->after('date_of_birth');
            $table->boolean('verified')->default(false)->after('avatar_url');
            $table->timestamp('last_verified_at')->nullable()->after('verified');
            $table->boolean('two_factor_enabled')->default(false)->after('last_verified_at');
            $table->string('two_factor_secret')->nullable()->after('two_factor_enabled');
            $table->string('locale', 10)->default('en')->after('two_factor_secret');
        });

    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone', 'full_name', 'display_name', 'date_of_birth',
                'avatar_url', 'verified', 'last_verified_at',
                'two_factor_enabled', 'two_factor_secret', 'locale'
            ]);
        });
    }
};
