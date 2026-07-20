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
}
