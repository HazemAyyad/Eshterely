<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AppConfigSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('theme_config')->count() === 0) {
            DB::table('theme_config')->insert([
                'primary_color' => '1E66F5',
                'background_color' => 'FFFFFF',
                'text_color' => '0B1220',
                'muted_text_color' => '6B7280',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (DB::table('splash_config')->count() === 0) {
            DB::table('splash_config')->insert([
                'logo_url' => '',
                'title_en' => 'Zayer',
                'title_ar' => 'زير',
                'subtitle_en' => 'Shop globally, delivered locally',
                'subtitle_ar' => 'تسوق عالميًا، توصيل محلي',
                'progress_text_en' => null,
                'progress_text_ar' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $onboarding = [
            [
                'sort_order' => 0,
                'image_url' => '',
                'title_en' => 'Shop from Global Stores',
                'title_ar' => 'تسوق من متاجر العالم',
                'description_en' => "Access millions of products from the world's best markets directly through Zayer.",
                'description_ar' => 'الوصول إلى ملايين المنتجات من أفضل أسواق العالم مباشرة عبر زير.',
            ],
            [
                'sort_order' => 1,
                'image_url' => '',
                'title_en' => 'Combine & Save',
                'title_ar' => 'اجمع ووفر',
                'description_en' => 'We group your purchases by origin country to minimize shipping costs and maximize your savings.',
                'description_ar' => 'نجمع مشترياتك حسب دولة المنشأ لتقليل تكاليف الشحن وزيادة توفيرك.',
            ],
            [
                'sort_order' => 2,
                'image_url' => '',
                'title_en' => 'Transparent Tracking',
                'title_ar' => 'تتبع شفاف',
                'description_en' => 'No hidden fees. Track your consolidated shipments from the warehouse to your doorstep in real-time.',
                'description_ar' => 'بدون رسوم خفية. تتبع شحناتك المجمعة من المستودع إلى عتبة بابك في الوقت الفعلي.',
            ],
        ];

        if (DB::table('onboarding_pages')->count() === 0) {
            foreach ($onboarding as $p) {
                DB::table('onboarding_pages')->insert(array_merge($p, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }

        $marketCountries = [
            ['code' => 'ALL', 'name' => 'All Markets', 'flag_emoji' => '', 'is_featured' => false],
            ['code' => 'US', 'name' => 'USA', 'flag_emoji' => '🇺🇸', 'is_featured' => true],
            ['code' => 'TR', 'name' => 'Turkey', 'flag_emoji' => '🇹🇷', 'is_featured' => true],
            ['code' => 'UK', 'name' => 'UK', 'flag_emoji' => '🇬🇧', 'is_featured' => true],
            ['code' => 'FR', 'name' => 'France', 'flag_emoji' => '🇫🇷', 'is_featured' => false],
            ['code' => 'AE', 'name' => 'UAE', 'flag_emoji' => '🇦🇪', 'is_featured' => true],
        ];

        foreach ($marketCountries as $c) {
            if (DB::table('market_countries')->where('code', $c['code'])->doesntExist()) {
                DB::table('market_countries')->insert(array_merge($c, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }

        $stores = [
            ['store_slug' => 'amazon_us', 'name' => 'Amazon US', 'description' => 'Global marketplace for tech & electronics', 'country_code' => 'US', 'store_url' => 'https://www.amazon.com', 'is_featured' => true],
            ['store_slug' => 'trendyol', 'name' => 'Trendyol', 'description' => "Turkey's largest fashion & beauty hub", 'country_code' => 'TR', 'store_url' => 'https://www.trendyol.com', 'is_featured' => true],
            ['store_slug' => 'asos_uk', 'name' => 'ASOS UK', 'description' => 'British fashion destination with 850+ brands', 'country_code' => 'UK', 'store_url' => 'https://www.asos.com', 'is_featured' => true],
        ];

        foreach ($stores as $s) {
            if (DB::table('featured_stores')->where('store_slug', $s['store_slug'])->doesntExist()) {
                DB::table('featured_stores')->insert(array_merge($s, [
                    'logo_url' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }

        $warehouses = [
            ['slug' => 'delaware_us', 'label' => 'Delaware, US', 'country_code' => 'US'],
            ['slug' => 'turkey_istanbul', 'label' => 'Istanbul, Turkey', 'country_code' => 'TR'],
            ['slug' => 'uk_london', 'label' => 'London, UK', 'country_code' => 'UK'],
            ['slug' => 'uae_dubai', 'label' => 'Dubai, UAE', 'country_code' => 'AE'],
        ];

        foreach ($warehouses as $w) {
            if (DB::table('warehouses')->where('slug', $w['slug'])->doesntExist()) {
                DB::table('warehouses')->insert(array_merge($w, [
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }

        $promoBanners = [
            [
                'label' => 'Welcome',
                'title' => 'Shop Globally',
                'cta_text' => 'Explore',
                'image_url' => '',
                'deep_link' => '',
                'sort_order' => 0,
                'is_active' => true,
            ],
            [
                'label' => 'Sale',
                'title' => 'Special Offers',
                'cta_text' => 'Shop Now',
                'image_url' => '',
                'deep_link' => '',
                'sort_order' => 1,
                'is_active' => true,
            ],
        ];

        if (DB::table('promo_banners')->count() === 0) {
            foreach ($promoBanners as $b) {
                DB::table('promo_banners')->insert(array_merge($b, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }

        $countries = [
            ['code' => 'us', 'name' => 'United States', 'flag_emoji' => null],
            ['code' => 'ae', 'name' => 'United Arab Emirates', 'flag_emoji' => null],
            ['code' => 'sa', 'name' => 'Saudi Arabia', 'flag_emoji' => null],
            ['code' => 'eg', 'name' => 'Egypt', 'flag_emoji' => null],
            ['code' => 'tr', 'name' => 'Turkey', 'flag_emoji' => null],
            ['code' => 'gb', 'name' => 'United Kingdom', 'flag_emoji' => null],
        ];
        foreach ($countries as $c) {
            if (DB::table('countries')->where('code', $c['code'])->doesntExist()) {
                DB::table('countries')->insert(array_merge($c, ['created_at' => now(), 'updated_at' => now()]));
            }
        }

        $us = DB::table('countries')->where('code', 'us')->first();
        $ae = DB::table('countries')->where('code', 'ae')->first();
        $tr = DB::table('countries')->where('code', 'tr')->first();
        $cityData = [];
        if ($us && DB::table('cities')->where('country_id', $us->id)->where('code', 'ny')->doesntExist()) {
            $cityData[] = ['country_id' => $us->id, 'name' => 'New York', 'code' => 'ny', 'created_at' => now(), 'updated_at' => now()];
        }
        if ($us && DB::table('cities')->where('country_id', $us->id)->where('code', 'la')->doesntExist()) {
            $cityData[] = ['country_id' => $us->id, 'name' => 'Los Angeles', 'code' => 'la', 'created_at' => now(), 'updated_at' => now()];
        }
        if ($ae && DB::table('cities')->where('country_id', $ae->id)->where('code', 'dxb')->doesntExist()) {
            $cityData[] = ['country_id' => $ae->id, 'name' => 'Dubai', 'code' => 'dxb', 'created_at' => now(), 'updated_at' => now()];
        }
        if ($ae && DB::table('cities')->where('country_id', $ae->id)->where('code', 'auh')->doesntExist()) {
            $cityData[] = ['country_id' => $ae->id, 'name' => 'Abu Dhabi', 'code' => 'auh', 'created_at' => now(), 'updated_at' => now()];
        }
        if ($tr && DB::table('cities')->where('country_id', $tr->id)->where('code', 'ist')->doesntExist()) {
            $cityData[] = ['country_id' => $tr->id, 'name' => 'Istanbul', 'code' => 'ist', 'created_at' => now(), 'updated_at' => now()];
        }
        if ($tr && DB::table('cities')->where('country_id', $tr->id)->where('code', 'ank')->doesntExist()) {
            $cityData[] = ['country_id' => $tr->id, 'name' => 'Ankara', 'code' => 'ank', 'created_at' => now(), 'updated_at' => now()];
        }
        if (!empty($cityData)) {
            DB::table('cities')->insert($cityData);
        }
    }
}
