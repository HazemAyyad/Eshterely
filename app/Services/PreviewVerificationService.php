<?php

namespace App\Services;

/**
 * Placeholder for future preview verification (confirm trust model).
 *
 * Planned evolution:
 * - preview_token: reference to a server-issued token from import/preview flow
 * - Server-side preview cache: store preview payload keyed by token/id for verification
 * - Signed preview identifiers: sign preview_id so confirm can verify payload was not tampered
 *
 * Confirm endpoint will remain backward compatible: it accepts client payload today.
 * When verification is implemented, confirm can optionally validate preview_token/preview_id
 * against cache or signature before persisting the snapshot.
 */
class PreviewVerificationService
{
    /**
     * Verify preview reference (future: check cache or signature).
     * For now returns true so confirm flow is unchanged.
     */
    public function verifyPreviewReference(?string $previewToken, ?string $previewId): bool
    {
        if ($previewToken === null && $previewId === null) {
            return true;
        }
        // Future: resolve preview from cache, verify signature
        return true;
    }
}
