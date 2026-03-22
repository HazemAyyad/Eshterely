<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ConfigController extends Controller
{
    private function imageUrl(?string $path): string
    {
        if (empty($path)) {
            return '';
        }
        return str_starts_with($path, 'http') ? $path : asset('storage/' . $path);
    }

    public function bootstrap(): JsonResponse
    {
        $theme = DB::table('theme_config')->first();
        $splash = DB::table('splash_config')->first();
        $onboarding = DB::table('onboarding_pages')->orderBy('sort_order')->get();
        $countries = DB::table('market_countries')->get();
        $storesQuery = DB::table('featured_stores');
        if (Schema::hasColumn('featured_stores', 'is_active')) {
            $storesQuery->where('is_active', true);
        } else {
            $storesQuery->where('is_featured', true);
        }
        $stores = $storesQuery->orderByDesc('is_featured')->get();

        // Only expose markets (countries) that actually have at least one featured store.
        $countriesWithStores = $countries->filter(function ($c) use ($stores) {
            return $stores->contains(fn ($s) => $s->country_code === $c->code);
        })->values();
        $promoBanners = DB::table('promo_banners')->where('is_active', true)->orderBy('sort_order')->get();
        $warehouses = DB::table('warehouses')->where('is_active', true)->orderBy('label')->get();

        $apiBaseUrl = null;
        $developmentMode = false;
        $appName = null;
        $appIconUrl = null;
        if (Schema::hasTable('app_config')) {
            $appConfig = DB::table('app_config')->first();
            if ($appConfig) {
                $apiBaseUrl = $appConfig->api_base_url ?? null;
                $developmentMode = (bool) ($appConfig->development_mode ?? false);
                if (Schema::hasColumn('app_config', 'app_name')) {
                    $appName = $appConfig->app_name ?? null;
                }
                if (Schema::hasColumn('app_config', 'app_icon_url')) {
                    $appIconUrl = !empty($appConfig->app_icon_url)
                        ? (str_starts_with($appConfig->app_icon_url, 'http')
                            ? $appConfig->app_icon_url
                            : asset('storage/' . $appConfig->app_icon_url))
                        : null;
                }
            }
        }

        $paymentGateways = [
            'default' => 'square',
            'enabled' => ['square'],
            'providers' => [
                'square' => [
                    'enabled' => true,
                    'environment' => 'sandbox',
                    'supports_web_checkout' => true,
                ],
                'stripe' => [
                    'enabled' => false,
                    'environment' => 'test',
                    'supports_web_checkout' => true,
                ],
            ],
        ];

        if (Schema::hasTable('payment_gateway_settings')) {
            $pg = DB::table('payment_gateway_settings')->first();
            if ($pg) {
                $enabled = [];
                if ((bool) ($pg->square_enabled ?? true)) {
                    $enabled[] = 'square';
                }
                if ((bool) ($pg->stripe_enabled ?? false)) {
                    $enabled[] = 'stripe';
                }
                if ($enabled === []) {
                    $enabled = ['square'];
                }

                $default = is_string($pg->default_gateway ?? null) ? (string) $pg->default_gateway : 'square';
                if (! in_array($default, $enabled, true)) {
                    $default = $enabled[0];
                }

                $paymentGateways = [
                    'default' => $default,
                    'enabled' => $enabled,
                    'providers' => [
                        'square' => [
                            'enabled' => in_array('square', $enabled, true),
                            'environment' => (string) ($pg->square_environment ?? 'sandbox'),
                            'supports_web_checkout' => true,
                        ],
                        'stripe' => [
                            'enabled' => in_array('stripe', $enabled, true),
                            'environment' => (string) ($pg->stripe_environment ?? 'test'),
                            'publishable_key' => !empty($pg->stripe_publishable_key)
                                ? (string) $pg->stripe_publishable_key
                                : (config('stripe.publishable_key') ?: ''),
                            'supports_web_checkout' => true,
                        ],
                    ],
                ];
            }
        }

        return response()->json([
            'theme' => [
                'primary_color' => $theme->primary_color ?? '1E66F5',
                'background_color' => $theme->background_color ?? 'FFFFFF',
                'text_color' => $theme->text_color ?? '0B1220',
                'muted_text_color' => $theme->muted_text_color ?? '6B7280',
            ],
            'splash' => [
                'logo_url' => $this->imageUrl($splash->logo_url ?? null),
                'title_en' => $splash->title_en ?? 'Zayer',
                'title_ar' => $splash->title_ar ?? 'زير',
                'subtitle_en' => $splash->subtitle_en ?? 'Shop globally, delivered locally',
                'subtitle_ar' => $splash->subtitle_ar ?? 'تسوق عالميًا، توصيل محلي',
                'progress_text_en' => $splash->progress_text_en ?? null,
                'progress_text_ar' => $splash->progress_text_ar ?? null,
            ],
            'onboarding' => $onboarding->map(fn ($p) => [
                'image_url' => $this->imageUrl($p->image_url ?? null),
                'title_en' => $p->title_en ?? '',
                'title_ar' => $p->title_ar ?? $p->title_en,
                'description_en' => $p->description_en ?? '',
                'description_ar' => $p->description_ar ?? $p->description_en,
            ])->toArray(),
            'markets' => [
                'title' => 'Explore Markets',
                'subtitle' => 'Shop directly from official stores worldwide',
                'countries' => $countriesWithStores->map(fn ($c) => [
                    'code' => $c->code,
                    'name' => $c->name,
                    'flag_emoji' => $c->flag_emoji ?? '',
                    'is_featured' => (bool) ($c->is_featured ?? false),
                ])->toArray(),
                'featured_stores' => $stores->map(function ($s) {
                    $categories = [];
                    if (isset($s->categories) && $s->categories !== null && $s->categories !== '') {
                        // Stored as comma-separated string in DB; normalize to trimmed array.
                        $categories = array_values(array_filter(array_map(
                            static fn ($c) => trim($c),
                            explode(',', (string) $s->categories)
                        )));
                    }

                    return [
                        'id' => $s->store_slug,
                        'name' => $s->name,
                        'description' => $s->description ?? '',
                        'logo_url' => $this->imageUrl($s->logo_url ?? null),
                        'country_code' => $s->country_code ?? '',
                        'store_url' => $s->store_url ?? '',
                        'is_featured' => (bool) $s->is_featured,
                        'categories' => $categories,
                    ];
                })->toArray(),
            ],
            'promo_banners' => $promoBanners->map(fn ($b) => [
                'id' => $b->id,
                'label' => $b->label ?? '',
                'title' => $b->title ?? '',
                'cta_text' => $b->cta_text ?? '',
                'image_url' => $this->imageUrl($b->image_url ?? null),
                'deep_link' => $b->deep_link ?? '',
            ])->toArray(),
            'warehouses' => $warehouses->map(fn ($w) => [
                'id' => $w->slug,
                'slug' => $w->slug,
                'label' => $w->label,
                'country_code' => $w->country_code ?? '',
            ])->toArray(),
            'api_base_url' => $apiBaseUrl,
            'development_mode' => $developmentMode,
            'app_name' => $appName,
            'app_icon_url' => $appIconUrl,
            'payment_gateways' => $paymentGateways,
        ]);
    }
}
