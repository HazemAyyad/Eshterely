<?php

namespace App\Models;

use App\Services\Shipping\ShippingPricingConfigService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    public const SOURCE_PASTE_LINK = 'paste_link';
    public const SOURCE_WEBVIEW = 'webview';
    public const SOURCE_IMPORTED = 'imported';

    public const REVIEW_STATUS_PENDING = 'pending_review';
    public const REVIEW_STATUS_REVIEWED = 'reviewed';
    public const REVIEW_STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id', 'imported_product_id', 'draft_order_id', 'product_url', 'name', 'unit_price', 'quantity', 'currency',
        'image_url', 'store_key', 'store_name', 'product_id', 'country',
        'weight', 'weight_unit', 'length', 'width', 'height', 'dimension_unit',
        'source', 'variation_text', 'review_status', 'shipping_cost',
        'pricing_snapshot', 'shipping_snapshot',
        'estimated', 'missing_fields', 'carrier', 'pricing_mode', 'needs_review',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'shipping_cost' => 'decimal:2',
            'weight' => 'decimal:4',
            'length' => 'decimal:2',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'pricing_snapshot' => 'array',
            'shipping_snapshot' => 'array',
            'estimated' => 'boolean',
            'missing_fields' => 'array',
            'needs_review' => 'boolean',
        ];
    }

    public function isImported(): bool
    {
        return $this->source === self::SOURCE_IMPORTED && $this->imported_product_id !== null;
    }

    /**
     * Source type for API: 'imported' when from imported product, otherwise existing source (e.g. paste_link, webview).
     */
    public function getSourceTypeAttribute(): string
    {
        return $this->isImported() ? 'imported' : ($this->source ?? 'paste_link');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function importedProduct(): BelongsTo
    {
        return $this->belongsTo(ImportedProduct::class);
    }

    public function draftOrder(): BelongsTo
    {
        return $this->belongsTo(DraftOrder::class);
    }

    /** Whether this item is attached to a draft order (excluded from active cart). */
    public function isAttachedToDraft(): bool
    {
        return $this->draft_order_id !== null;
    }

    public function lineSubtotal(): float
    {
        return round((float) $this->unit_price * (int) $this->quantity, 2);
    }

    /**
     * App fee percent for this line: snapshot at add-to-cart, else current admin default.
     */
    public function resolvedAppFeePercent(): float
    {
        $snap = $this->pricing_snapshot ?? [];
        if (is_array($snap) && isset($snap['app_fee_percent']) && is_numeric($snap['app_fee_percent'])) {
            return max(0.0, (float) $snap['app_fee_percent']);
        }

        return app(ShippingPricingConfigService::class)->appFeePercent();
    }

    public function appFeeAmount(): float
    {
        $line = $this->lineSubtotal();
        $pct = $this->resolvedAppFeePercent();

        return round($line * ($pct / 100.0), 2);
    }

    /** Product + app fee for this line (shipping not included). */
    public function payableNowTotal(): float
    {
        return round($this->lineSubtotal() + $this->appFeeAmount(), 2);
    }

    /** Display-only shipping estimate for the full line (uses stored per-unit shipping_cost × quantity). */
    public function shippingEstimateLineAmount(): ?float
    {
        if ($this->shipping_cost === null) {
            return null;
        }

        return round((float) $this->shipping_cost * (int) $this->quantity, 2);
    }
}
