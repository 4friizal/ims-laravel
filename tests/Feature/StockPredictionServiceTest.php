<?php

namespace Tests\Feature;

use App\Enums\MaterialUnit;
use App\Models\Category;
use App\Models\MaterialUsage;
use App\Models\RawMaterial;
use App\Models\StockBatch;
use App\Services\StockPredictionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StockPredictionServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockPredictionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StockPredictionService;
    }

    private function materialWithUsage(array $dailyQuantities): RawMaterial
    {
        $material = RawMaterial::create([
            'category_id' => Category::create(['name' => 'Bumbu'])->id,
            'name' => 'Cabai',
            'unit' => MaterialUnit::Kilogram,
            'minimum_stock' => 5,
        ]);

        $start = Carbon::today()->subDays(count($dailyQuantities));
        foreach ($dailyQuantities as $i => $qty) {
            MaterialUsage::create([
                'raw_material_id' => $material->id,
                'usage_date' => $start->copy()->addDays($i),
                'quantity' => $qty,
            ]);
        }

        return $material;
    }

    public function test_forecast_projects_growing_trend(): void
    {
        // Steady +1/day growth: 10, 11, 12, ... 19.
        $material = $this->materialWithUsage(range(10, 19));

        $daily = $this->service->predictedDailyUsage($material);
        // Next day after 19 should be ~20.
        $this->assertEqualsWithDelta(20.0, $daily, 0.5);

        // 7-day forecast should be well above 7 * last value floor.
        $forecast = $this->service->forecast($material, 7);
        $this->assertGreaterThan(140, $forecast);
    }

    public function test_recommend_restock_when_stock_below_projected_demand(): void
    {
        $material = $this->materialWithUsage(array_fill(0, 30, 10)); // flat 10/day
        // Only 20 in stock, but 7-day demand ~70 + safety 5.
        StockBatch::create([
            'raw_material_id' => $material->id,
            'quantity' => 20,
            'remaining_quantity' => 20,
            'unit_cost' => 1000,
            'received_at' => Carbon::today(),
        ]);

        $rec = $this->service->recommendRestock($material, 7);

        $this->assertTrue($rec['needs_restock']);
        $this->assertEqualsWithDelta(70.0, $rec['projected_demand'], 1.0);
        // recommended = demand(70) + safety(5) - stock(20) = ~55.
        $this->assertEqualsWithDelta(55.0, $rec['recommended_restock'], 1.0);
    }

    public function test_no_restock_when_stock_is_ample(): void
    {
        $material = $this->materialWithUsage(array_fill(0, 30, 10));
        StockBatch::create([
            'raw_material_id' => $material->id,
            'quantity' => 500,
            'remaining_quantity' => 500,
            'unit_cost' => 1000,
            'received_at' => Carbon::today(),
        ]);

        $rec = $this->service->recommendRestock($material, 7);

        $this->assertFalse($rec['needs_restock']);
        $this->assertSame(0.0, $rec['recommended_restock']);
    }

    public function test_chart_series_has_history_and_forecast(): void
    {
        $material = $this->materialWithUsage(range(10, 19));

        $series = $this->service->historyAndForecastSeries($material, 7);

        $this->assertCount(17, $series['labels']); // 10 history + 7 forecast
        $this->assertCount(17, $series['actual']);
        $this->assertCount(17, $series['forecast']);
        // Last history forecast point is bridged to the actual value.
        $this->assertNotNull($series['forecast'][9]);
        // Forecast tail is populated.
        $this->assertNotNull($series['forecast'][16]);
        $this->assertNull($series['actual'][16]);
    }

    public function test_evaluate_splits_train_and_test_windows(): void
    {
        // 40 days of history, last 14 held out as test data.
        $material = $this->materialWithUsage(range(10, 49));

        $result = $this->service->evaluate($material, 14);

        $this->assertTrue($result['evaluated']);
        $this->assertSame(26, $result['train_size']);
        $this->assertSame(14, $result['test_size']);
        $this->assertCount(14, $result['rows']);
    }

    public function test_evaluate_reports_near_zero_error_on_a_perfect_trend(): void
    {
        // y = index + 10 exactly, so a line fitted on the training half
        // extrapolates onto the test half without error.
        $material = $this->materialWithUsage(range(10, 49));

        $result = $this->service->evaluate($material, 14);

        $this->assertEqualsWithDelta(0.0, $result['mae'], 0.01);
        $this->assertEqualsWithDelta(0.0, $result['rmse'], 0.01);
        $this->assertEqualsWithDelta(0.0, $result['mape'], 0.01);
        // The naive mean-of-training baseline is far worse on a trending series.
        $this->assertGreaterThan(10.0, $result['baseline_mae']);
    }

    public function test_evaluate_penalises_a_model_that_misses_the_test_window(): void
    {
        // Flat 10/day for 30 days, then a sudden jump to 40/day for 14 days.
        $quantities = array_merge(array_fill(0, 30, 10), array_fill(0, 14, 40));
        $material = $this->materialWithUsage($quantities);

        $result = $this->service->evaluate($material, 14);

        // Trained only on the flat part, the model predicts ~10 against actual 40.
        $this->assertEqualsWithDelta(30.0, $result['mae'], 1.0);
        $this->assertEqualsWithDelta(75.0, $result['mape'], 3.0);
        $this->assertGreaterThanOrEqual($result['mae'], $result['rmse']);
    }

    public function test_evaluate_returns_unevaluated_when_history_is_too_short(): void
    {
        $material = $this->materialWithUsage([10, 11, 12]);

        $result = $this->service->evaluate($material, 14);

        $this->assertFalse($result['evaluated']);
        $this->assertNull($result['mae']);
        $this->assertSame(0, $result['test_size']);
    }

    public function test_evaluate_all_aggregates_across_materials(): void
    {
        $this->materialWithUsage(range(10, 49));
        $this->materialWithUsage(range(20, 59));

        $result = $this->service->evaluateAll(RawMaterial::all(), 14);

        $this->assertTrue($result['evaluated']);
        $this->assertSame(2, $result['materials']);
        $this->assertSame(28, $result['test_size']);
        $this->assertEqualsWithDelta(0.0, $result['mae'], 0.01);
    }
}
