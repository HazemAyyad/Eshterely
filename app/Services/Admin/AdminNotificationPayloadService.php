<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\Config;

/**
 * Builds structured metadata and action_route for admin-sent notifications.
 * Uses config/notifications.php for allowlisted route_key, target_type, action_label.
 */
class AdminNotificationPayloadService
{
    /**
     * Allowed route_key values from config.
     *
     * @return array<string, string> key => label
     */
    public function routeKeys(): array
    {
        return Config::get('notifications.route_keys', []);
    }

    /**
     * Allowed target_type values from config.
     *
     * @return array<string, string> key => label
     */
    public function targetTypes(): array
    {
        return Config::get('notifications.target_types', []);
    }

    /**
     * Action label presets from config.
     *
     * @return array<string, string> value => label
     */
    public function actionLabelPresets(): array
    {
        return Config::get('notifications.action_labels', []);
    }

    /**
     * Generate action_route from route_key and target_id when applicable.
     * Returns null if route_key is empty or pattern has no {id} and target is set.
     */
    public function buildActionRoute(?string $routeKey, ?string $targetType, ?string $targetId): ?string
    {
        if ($routeKey === null || $routeKey === '') {
            return null;
        }

        $patterns = Config::get('notifications.route_patterns', []);
        $pattern = $patterns[$routeKey] ?? null;

        if ($pattern === null) {
            return null;
        }

        $targetId = trim((string) $targetId);
        if (str_contains($pattern, '{id}')) {
            return $targetId !== '' ? str_replace('{id}', $targetId, $pattern) : null;
        }

        return $pattern;
    }

    /**
     * Build clean metadata array for FCM and in-app notification.
     * Only includes non-empty values. Suitable for FCM data payload (string values).
     *
     * @return array<string, string>
     */
    public function buildMeta(
        ?string $routeKey = null,
        ?string $targetType = null,
        ?string $targetId = null,
        ?string $actionLabel = null,
        ?string $actionRoute = null
    ): array {
        $meta = [];

        if ($routeKey !== null && trim($routeKey) !== '') {
            $meta['route_key'] = trim($routeKey);
        }
        if ($targetType !== null && trim($targetType) !== '' && trim($targetType) !== 'none') {
            $meta['target_type'] = trim($targetType);
        }
        if ($targetId !== null && trim($targetId) !== '') {
            $meta['target_id'] = trim($targetId);
        }
        if ($actionLabel !== null && trim($actionLabel) !== '') {
            $meta['action_label'] = trim($actionLabel);
        }
        if ($actionRoute !== null && trim($actionRoute) !== '') {
            $meta['action_route'] = trim($actionRoute);
        }

        return $meta;
    }

    /**
     * Resolve action_route: use override if provided, otherwise generate from route_key/target.
     */
    public function resolveActionRoute(
        ?string $routeKey,
        ?string $targetType,
        ?string $targetId,
        ?string $actionRouteOverride
    ): ?string {
        if ($actionRouteOverride !== null && trim($actionRouteOverride) !== '') {
            return trim($actionRouteOverride);
        }
        return $this->buildActionRoute($routeKey, $targetType, $targetId);
    }

    /**
     * Validation: allowed route_key values (empty string allowed for "no selection").
     *
     * @return array<int, string>
     */
    public function routeKeyRules(): array
    {
        $keys = array_keys($this->routeKeys());
        return ['nullable', 'string', 'max:100', \Illuminate\Validation\Rule::in(array_merge([''], $keys))];
    }

    /**
     * Validation: allowed target_type values (empty string allowed).
     *
     * @return array<int, string>
     */
    public function targetTypeRules(): array
    {
        $types = array_keys($this->targetTypes());
        return ['nullable', 'string', 'max:50', \Illuminate\Validation\Rule::in(array_merge([''], $types))];
    }

}
