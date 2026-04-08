<?php

namespace App\Support;

use App\Models\User;

/**
 * Admin list / detail display helpers for customer identity.
 */
class AdminUserDisplay
{
    public static function primaryName(?User $user): string
    {
        if (! $user) {
            return '—';
        }
        $name = trim((string) ($user->full_name ?: $user->display_name ?: $user->name));
        if ($name !== '') {
            return $name;
        }

        return $user->email ?? $user->phone ?? ('#'.$user->id);
    }
}
