<?php

namespace App\Services\Shipping;

/**
 * Builds final pricing breakdown from normalized product data and shipping quote.
 * All business values (service fee, markup, minimum order fee) come from admin config.
 * Used for confirm product screen, cart insertion, and order creation.
 */
class FinalProductPricingService
{
    public function __construct(
        private ShippingPricingConfigService $config
    ) {}

    /**
     * Build complete pricing breakdown.
     *
     * @param  array<string, mixed>  $normalizedProduct  Product data (price, currency, etc.)
     * @param  array{amount: float, currency: string, estimated?: bool, carrier?: string|null, pricing_mode?: string, notes?: array}  $shippingQuote  Result from ProductImportShippingQuoteService::quoteFromProduct
     * @param  int  $quantity  Quantity (defaults to 1 from product if not provided)
     * @return FinalProductPricingResult|null  Null if product price is missing or invalid
     */
    public function build(
        array $normalizedProduct,
        array $shippingQuote,
        int $quantity = 1
    ): ?FinalProductPricingResult {
        $quantity = $quantity < 1 ? 1 : $quantity;
        $productPrice = $this->extractProductPrice($normalizedProduct);
        $productCurrency = $this->extractProductCurrency($normalizedProduct);
        $shippingAmount = (float) ($shippingQuote['amount'] ?? 0);
        $shippingCurrency = (string) ($shippingQuote['currency'] ?? $this->config->defaultCurrency());
        if ($shippingCurrency === '') {
            $shippingCurrency = $this->config->defaultCurrency();
        }

        $lineTotal = $productPrice * $quantity;
        $subtotalBeforeFees = $lineTotal + $shippingAmount;

        $serviceFee = $this->config->serviceFee();
        $markupPercent = $this->config->platformMarkupPercent();
        $markupAmount = $subtotalBeforeFees * ($markupPercent / 100.0);
        $threshold = $this->config->minimumOrderThreshold();
        $minimumOrderFee = ($threshold > 0 && $subtotalBeforeFees < $threshold)
            ? $this->config->minimumOrderFee()
            : 0.0;

        $subtotal = $subtotalBeforeFees;
        $finalTotal = $subtotal + $serviceFee + $markupAmount + $minimumOrderFee;
        $finalTotal = round($finalTotal, 2);

        $carrier = isset($shippingQuote['carrier']) ? (string) $shippingQuote['carrier'] : null;
        $pricingMode = (string) ($shippingQuote['pricing_mode'] ?? 'default');
        $estimated = (bool) ($shippingQuote['estimated'] ?? false);
        $notes = array_values($shippingQuote['notes'] ?? []);
        if ($estimated) {
            $notes[] = 'Pricing is estimated due to fallback weight or dimensions; confirm at checkout.';
        }

        return new FinalProductPricingResult(
            productPrice: $productPrice,
            productCurrency: $productCurrency,
            shippingAmount: $shippingAmount,
            shippingCurrency: $shippingCurrency,
            serviceFee: $serviceFee,
            markupAmount: round($markupAmount, 2),
            subtotal: round($subtotal, 2),
            finalTotal: $finalTotal,
            carrier: $carrier,
            pricingMode: $pricingMode,
            estimated: $estimated,
            notes: $notes,
        );
    }

    private function extractProductPrice(array $product): float
    {
        $v = $product['price'] ?? $product['unit_price'] ?? null;
        if ($v === null || $v === '') {
            return 0.0;
        }
        return (float) $v;
    }

    private function extractProductCurrency(array $product): string
    {
        $v = $product['currency'] ?? null;
        if ($v === null || $v === '') {
            return $this->config->defaultCurrency();
        }
        return strtoupper(trim((string) $v));
    }
}
