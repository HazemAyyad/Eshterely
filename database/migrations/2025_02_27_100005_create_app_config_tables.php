<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theme_config', function (Blueprint $table) {
            $table->id();
            $table->string('primary_color', 10)->default('1E66F5');
            $table->string('background_color', 10)->default('FFFFFF');
            $table->string('text_color', 10)->default('0B1220');
            $table->string('muted_text_color', 10)->default('6B7280');
            $table->timestamps();
        });

        Schema::create('splash_config', function (Blueprint $table) {
            $table->id();
            $table->string('logo_url')->nullable();
            $table->string('title_en')->nullable();
            $table->string('title_ar')->nullable();
            $table->string('subtitle_en')->nullable();
            $table->string('subtitle_ar')->nullable();
            $table->string('progress_text_en')->nullable();
            $table->string('progress_text_ar')->nullable();
            $table->timestamps();
        });

        Schema::create('onboarding_pages', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('image_url')->nullable();
            $table->string('title_en')->nullable();
            $table->string('title_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->timestamps();
        });

        Schema::create('market_countries', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10);
            $table->string('name');
            $table->string('flag_emoji', 10)->nullable();
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
        });

        Schema::create('featured_stores', function (Blueprint $table) {
            $table->id();
            $table->string('store_slug', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('country_code', 10)->nullable();
            $table->string('store_url')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
        });

        Schema::create('promo_banners', function (Blueprint $table) {
            $table->id();
            $table->string('label')->nullable();
            $table->string('title')->nullable();
            $table->string('cta_text')->nullable();
            $table->string('image_url')->nullable();
            $table->string('deep_link')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->timestamps();
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('label');
            $table->string('country_code', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('promo_banners');
        Schema::dropIfExists('featured_stores');
        Schema::dropIfExists('market_countries');
        Schema::dropIfExists('onboarding_pages');
        Schema::dropIfExists('splash_config');
        Schema::dropIfExists('theme_config');
    }
};
