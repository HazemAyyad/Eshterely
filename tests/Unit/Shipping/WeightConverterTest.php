<?php

namespace Tests\Unit\Shipping;

use App\Services\Shipping\WeightConverter;
use PHPUnit\Framework\TestCase;

class WeightConverterTest extends TestCase
{
    public function test_kg_to_lb(): void
    {
        $this->assertEqualsWithDelta(2.20462, WeightConverter::kgToLb(1), 0.00001);
        $this->assertEqualsWithDelta(0, WeightConverter::kgToLb(0), 0.00001);
        $this->assertEqualsWithDelta(22.0462, WeightConverter::kgToLb(10), 0.001);
    }

    public function test_lb_to_kg(): void
    {
        $this->assertEqualsWithDelta(1, WeightConverter::lbToKg(2.20462), 0.00001);
        $this->assertEqualsWithDelta(0, WeightConverter::lbToKg(0), 0.00001);
        $this->assertEqualsWithDelta(10, WeightConverter::lbToKg(22.0462), 0.001);
    }

    public function test_round_trip(): void
    {
        $kg = 5.5;
        $lb = WeightConverter::kgToLb($kg);
        $this->assertEqualsWithDelta($kg, WeightConverter::lbToKg($lb), 0.0001);
    }
}
