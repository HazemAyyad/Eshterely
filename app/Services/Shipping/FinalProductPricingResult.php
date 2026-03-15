<?php

namespace App\Services\Shipping;

/**
 * Complete pricing breakdown for a product + shipping quote, ready for confirm screen / cart / order.
 */
final class FinalProductPricingResult
{
    public function __construct(
        public float $productPrice,
        public string $productCurrency,
        public float $shippingAmount,
        public string $shippingCurrency,
        public float $serviceFee,
        public float $markupAmount,
        public float $subtotal,
        public float $finalTotal,
        public ?string $carrier,
        public string $pricingMode,
        public bool $estimated,
        /** @var list<string> */
        public array $notes = [],
    ) {}

    public function toArray(): array
    {
        return [
            'product_price' => $this->productPrice,
            'product_currency' => $this->productCurrency,
            'shipping_amount' => $this->shippingAmount,
            'shipping_currency' => $this->shippingCurrency,
            'service_fee' => $this->serviceFee,
            'markup_amount' => $this->markupAmount,
            'subtotal' => $this->subtotal,
            'final_total' => $this->finalTotal,
            'carrier' => $this->carrier,
            'pricing_mode' => $this->pricingMode,
            'estimated' => $this->estimated,
            'notes' => $this->notes,
        ];
    }
}
