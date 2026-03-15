<?php

namespace Tests\Unit\Shipping;

use App\Services\Shipping\PackageNormalizer;
use Tests\TestCase;

class PackageNormalizerWeightUnitTest extends TestCase
{
    private PackageNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = app(PackageNormalizer::class);
    }

    public function test_lbs_normalized_to_lb(): void
    {
        $this->assertSame('lb', $this->normalizer->normalizeWeightUnit('lbs'));
        $this->assertSame('lb', $this->normalizer->normalizeWeightUnit('LBS'));
        $this->assertSame('lb', $this->normalizer->normalizeWeightUnit(' lb '));
    }

    public function test_lb_remains_lb(): void
    {
        $this->assertSame('lb', $this->normalizer->normalizeWeightUnit('lb'));
    }

    public function test_kg_remains_kg(): void
    {
        $this->assertSame('kg', $this->normalizer->normalizeWeightUnit('kg'));
        $this->assertSame('kg', $this->normalizer->normalizeWeightUnit('KG'));
    }

    public function test_pound_aliases_normalized_to_lb(): void
    {
        $this->assertSame('lb', $this->normalizer->normalizeWeightUnit('pound'));
        $this->assertSame('lb', $this->normalizer->normalizeWeightUnit('pounds'));
    }

    public function test_unknown_unit_defaults_to_kg(): void
    {
        $this->assertSame('kg', $this->normalizer->normalizeWeightUnit('oz'));
        $this->assertSame('kg', $this->normalizer->normalizeWeightUnit(null));
        $this->assertSame('kg', $this->normalizer->normalizeWeightUnit(''));
    }
}
