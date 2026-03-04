<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

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
        $stores = DB::table('featured_stores')->where('is_featured', true)->get();
        $promoBanners = DB::table('promo_banners')->where('is_active', true)->orderBy('sort_order')->get();
        $warehouses = DB::table('warehouses')->where('is_active', true)->orderBy('label')->get();

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
                'countries' => $countries->map(fn ($c) => [
                    'code' => $c->code,
                    'name' => $c->name,
                    'flag_emoji' => $c->flag_emoji ?? '',
                    'is_featured' => (bool) ($c->is_featured ?? false),
                ])->toArray(),
                'featured_stores' => $stores->map(fn ($s) => [
                    'id' => $s->store_slug,
                    'name' => $s->name,
                    'description' => $s->description ?? '',
                    'logo_url' => $this->imageUrl($s->logo_url ?? null),
                    'country_code' => $s->country_code ?? '',
                    'store_url' => $s->store_url ?? '',
                    'is_featured' => (bool) $s->is_featured,
                ])->toArray(),
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
        ]);
    }
}
