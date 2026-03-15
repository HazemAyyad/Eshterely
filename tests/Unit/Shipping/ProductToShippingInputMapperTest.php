<?php

namespace Tests\Unit\Shipping;

use App\Services\Shipping\ProductToShippingInputMapper;
use Tests\TestCase;

class ProductToShippingInputMapperTest extends TestCase
{
    private ProductToShippingInputMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new ProductToShippingInputMapper;
    }

    public function test_has_enough_data_with_weight_only(): void
    {
        $product = ['name' => 'X', 'price' => 10, 'weight' => 1.5];
        $this->assertTrue($this->mapper->hasEnoughDataForQuote($product));
    }

    public function test_has_enough_data_with_dimensions_only(): void
    {
        $product = ['name' => 'X', 'length' => 10, 'width' => 10, 'height' => 5];
        $this->assertTrue($this->mapper->hasEnoughDataForQuote($product));
    }

    public function test_has_enough_data_false_when_nothing(): void
    {
        $product = ['name' => 'X', 'price' => 10];
        $this->assertFalse($this->mapper->hasEnoughDataForQuote($product));
    }

    public function test_from_normalized_product_uses_fallbacks_and_marks_estimated(): void
    {
        $product = ['name' => 'X', 'price' => 10, 'weight' => 2.0];
        $result = $this->mapper->fromNormalizedProduct($product);

        $this->assertTrue($result['estimated']);
        $this->assertContains('length', $result['missing_fields']);
        $this->assertContains('width', $result['missing_fields']);
        $this->assertContains('height', $result['missing_fields']);
        $this->assertSame(2.0, $result['input']['weight']);
        $this->assertSame(10.0, $result['input']['length']);
        $this->assertSame(10.0, $result['input']['width']);
        $this->assertSame(10.0, $result['input']['height']);
    }

    public function test_from_normalized_product_with_overrides(): void
    {
        $product = ['name' => 'X', 'weight' => 1, 'length' => 5, 'width' => 5, 'height' => 5];
        $result = $this->mapper->fromNormalizedProduct($product, [
            'destination_country' => 'SA',
            'warehouse_mode' => true,
            'quantity' => 2,
        ]);

        $this->assertFalse($result['estimated']);
        $this->assertSame('SA', $result['input']['destination_country']);
        $this->assertTrue($result['input']['warehouse_mode']);
        $this->assertSame(2, $result['input']['quantity']);
    }
}
