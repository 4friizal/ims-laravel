<?php

namespace Tests\Unit;

use App\Services\StockPredictionService;
use PHPUnit\Framework\TestCase;

class StockPredictionServiceTest extends TestCase
{
    private StockPredictionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StockPredictionService;
    }

    public function test_linear_regression_fits_a_perfect_line(): void
    {
        // y = 2x + 1
        $points = [[0, 1], [1, 3], [2, 5], [3, 7]];

        $result = $this->service->linearRegression($points);

        $this->assertEqualsWithDelta(2.0, $result['slope'], 0.0001);
        $this->assertEqualsWithDelta(1.0, $result['intercept'], 0.0001);
        $this->assertEqualsWithDelta(1.0, $result['r2'], 0.0001);
    }

    public function test_linear_regression_handles_flat_data(): void
    {
        $points = [[0, 5], [1, 5], [2, 5], [3, 5]];

        $result = $this->service->linearRegression($points);

        $this->assertEqualsWithDelta(0.0, $result['slope'], 0.0001);
        $this->assertEqualsWithDelta(5.0, $result['intercept'], 0.0001);
    }

    public function test_linear_regression_handles_empty_and_single_point(): void
    {
        $empty = $this->service->linearRegression([]);
        $this->assertSame(0.0, $empty['slope']);
        $this->assertSame(0.0, $empty['intercept']);

        $single = $this->service->linearRegression([[0, 9]]);
        $this->assertSame(0.0, $single['slope']);
        $this->assertSame(9.0, $single['intercept']);
    }

    public function test_r2_is_lower_for_noisy_data(): void
    {
        $noisy = [[0, 1], [1, 5], [2, 2], [3, 8], [4, 3]];

        $result = $this->service->linearRegression($noisy);

        $this->assertLessThan(1.0, $result['r2']);
        $this->assertGreaterThanOrEqual(0.0, $result['r2']);
    }
}
