<?php

namespace App\View\Composers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AdminBrandingComposer
{
    public const DEFAULT_APP_NAME = 'eshterely';

    public const DEFAULT_ICON_INITIALS = 'esh';

    /**
     * @return array{name: string, icon_url: ?string, has_icon: bool, fallback_initials: string}
     */
    public static function resolve(): array
    {
        $name = self::DEFAULT_APP_NAME;
        $iconUrl = null;

        if (Schema::hasTable('app_config')) {
            $row = DB::table('app_config')->first();
            if ($row) {
                if (Schema::hasColumn('app_config', 'app_name')) {
                    $configured = trim((string) ($row->app_name ?? ''));
                    if ($configured !== '') {
                        $name = $configured;
                    }
                }
                if (Schema::hasColumn('app_config', 'app_icon_url')) {
                    $path = $row->app_icon_url ?? null;
                    if (! empty($path)) {
                        $iconUrl = str_starts_with((string) $path, 'http')
                            ? (string) $path
                            : asset('storage/'.ltrim((string) $path, '/'));
                    }
                }
            }
        }

        return [
            'name' => $name,
            'icon_url' => $iconUrl,
            'has_icon' => $iconUrl !== null,
            'fallback_initials' => self::DEFAULT_ICON_INITIALS,
        ];
    }

    public function compose(View $view): void
    {
        $view->with('adminBrand', self::resolve());
    }
}
