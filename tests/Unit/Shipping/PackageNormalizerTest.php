<?php

namespace Tests\Unit\Shipping;

use App\Services\Shipping\PackageNormalizer;
use Tests\TestCase;

class PackageNormalizerTest extends TestCase
{
    private PackageNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = app(PackageNormalizer::class);
    }

    public function test_normalizes_kg_and_cm_to_internal_units(): void
    {
        $input = [
            'destination_country' => 'US',
            'weight' => 5,
            'weight_unit' => 'kg',
            'length' => 30,
            'width' => 20,
            'height' => 10,
            'dimension_unit' => 'cm',
            'quantity' => 1,
            'warehouse_mode' => false,
        ];
        $pkg = $this->normalizer->normalize($input);
        $this->assertEquals(5, $pkg->weightKg);
        $this->assertEquals(30, $pkg->lengthCm);
        $this->assertEquals(20, $pkg->widthCm);
        $this->assertEquals(10, $pkg->heightCm);
        $this->assertEquals('US', $pkg->destinationCountry);
        $this->assertFalse($pkg->warehouseMode);
        $this->assertEquals(1, $pkg->quantity);
    }

    public function test_converts_lb_to_kg(): void
    {
        $input = [
            'destination_country' => 'AE',
            'weight' => 2.20462,
            'weight_unit' => 'lb',
            'length' => 1,
            'width' => 1,
            'height' => 1,
        ];
        $pkg = $this->normalizer->normalize($input);
        $this->assertEqualsWithDelta(1, $pkg->weightKg, 0.0001);
    }

    public function test_converts_inches_to_cm(): void
    {
        $input = [
            'destination_country' => 'SA',
            'weight' => 1,
            'length' => 1,
            'width' => 1,
            'height' => 1,
            'dimension_unit' => 'in',
        ];
        $pkg = $this->normalizer->normalize($input);
        $this->assertEqualsWithDelta(2.54, $pkg->lengthCm, 0.0001);
        $this->assertEqualsWithDelta(2.54, $pkg->widthCm, 0.0001);
        $this->assertEqualsWithDelta(2.54, $pkg->heightCm, 0.0001);
    }

    public function test_default_quantity_is_one(): void
    {
        $input = [
            'destination_country' => 'US',
            'weight' => 1,
            'length' => 1,
            'width' => 1,
            'height' => 1,
        ];
        $pkg = $this->normalizer->normalize($input);
        $this->assertEquals(1, $pkg->quantity);
    }

    public function test_quantity_under_one_becomes_one(): void
    {
        $input = [
            'destination_country' => 'US',
            'weight' => 1,
            'length' => 1,
            'width' => 1,
            'height' => 1,
            'quantity' => 0,
        ];
        $pkg = $this->normalizer->normalize($input);
        $this->assertEquals(1, $pkg->quantity);
    }
}
