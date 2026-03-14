<?php

namespace Tests\Unit\Shipping;

use App\Services\Shipping\DimensionConverter;
use PHPUnit\Framework\TestCase;

class DimensionConverterTest extends TestCase
{
    public function test_cm_to_in(): void
    {
        $this->assertEqualsWithDelta(1, DimensionConverter::cmToIn(2.54), 0.0001);
        $this->assertEqualsWithDelta(0, DimensionConverter::cmToIn(0), 0.0001);
        $this->assertEqualsWithDelta(10, DimensionConverter::cmToIn(25.4), 0.001);
    }

    public function test_in_to_cm(): void
    {
        $this->assertEqualsWithDelta(2.54, DimensionConverter::inToCm(1), 0.0001);
        $this->assertEqualsWithDelta(0, DimensionConverter::inToCm(0), 0.0001);
        $this->assertEqualsWithDelta(25.4, DimensionConverter::inToCm(10), 0.001);
    }

    public function test_round_trip(): void
    {
        $cm = 100.0;
        $inches = DimensionConverter::cmToIn($cm);
        $this->assertEqualsWithDelta($cm, DimensionConverter::inToCm($inches), 0.0001);
    }
}
