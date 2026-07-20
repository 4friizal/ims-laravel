<?php

namespace Tests\Feature;

use App\Enums\MaterialUnit;
use App\Enums\MovementType;
use App\Models\Category;
use App\Models\MaterialUsage;
use App\Models\Menu;
use App\Models\RawMaterial;
use App\Services\InsufficientStockException;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InventoryService;
    }

    private function material(float $minimumStock = 0): RawMaterial
    {
        return RawMaterial::create([
            'category_id' => Category::create(['name' => 'Bumbu'])->id,
            'name' => 'Cabai Merah',
            'unit' => MaterialUnit::Kilogram,
            'minimum_stock' => $minimumStock,
        ]);
    }

    public function test_stock_in_creates_batch_and_movement(): void
    {
        $material = $this->material();

        $this->service->stockIn($material, 10, 45000, Carbon::parse('2026-07-01'));

        $this->assertSame(10.0, $material->currentStock());
        $this->assertDatabaseHas('stock_movements', [
            'raw_material_id' => $material->id,
            'type' => MovementType::In->value,
            'quantity' => 10,
        ]);
    }

    public function test_stock_out_consumes_batches_fifo_and_computes_weighted_cost(): void
    {
        $material = $this->material();
        // Oldest batch cheaper, newer batch pricier.
        $this->service->stockIn($material, 5, 10000, Carbon::parse('2026-07-01'));
        $this->service->stockIn($material, 5, 20000, Carbon::parse('2026-07-05'));

        // Take 8: 5 from first batch (@10k) + 3 from second (@20k) = 110000 total.
        $movement = $this->service->stockOut($material, 8, 'Test');

        $this->assertSame(2.0, $material->currentStock());
        $this->assertSame('110000.00', $movement->total_cost);
        // Weighted unit cost = 110000 / 8 = 13750.
        $this->assertSame('13750.00', $movement->unit_cost);
        // FIFO breakdown recorded across two batches.
        $this->assertCount(2, $movement->batches);
    }

    public function test_stock_out_throws_when_insufficient(): void
    {
        $material = $this->material();
        $this->service->stockIn($material, 3, 10000);

        $this->expectException(InsufficientStockException::class);
        $this->service->stockOut($material, 5, 'Test');
    }

    public function test_stock_out_records_daily_usage(): void
    {
        $material = $this->material();
        $this->service->stockIn($material, 10, 10000);

        $this->service->stockOut($material, 2, 'A', null, Carbon::parse('2026-07-10 09:00'));
        $this->service->stockOut($material, 3, 'B', null, Carbon::parse('2026-07-10 18:00'));

        // Two outs same day aggregate into one usage row.
        $usage = MaterialUsage::where('raw_material_id', $material->id)
            ->whereDate('usage_date', '2026-07-10')
            ->first();

        $this->assertNotNull($usage);
        $this->assertSame('5.00', $usage->quantity);
        $this->assertSame(1, MaterialUsage::where('raw_material_id', $material->id)->count());
    }

    public function test_sell_menu_deducts_all_recipe_materials(): void
    {
        $category = Category::create(['name' => 'Umum']);
        $santan = RawMaterial::create(['category_id' => $category->id, 'name' => 'Santan', 'unit' => MaterialUnit::Liter, 'minimum_stock' => 0]);
        $daging = RawMaterial::create(['category_id' => $category->id, 'name' => 'Daging', 'unit' => MaterialUnit::Kilogram, 'minimum_stock' => 0]);
        $this->service->stockIn($santan, 10, 12000);
        $this->service->stockIn($daging, 10, 130000);

        $menu = Menu::create(['name' => 'Rendang', 'price' => 28000]);
        $menu->materials()->attach([
            $santan->id => ['quantity' => 0.3],
            $daging->id => ['quantity' => 0.2],
        ]);

        $movements = $this->service->sellMenu($menu, 2);

        $this->assertCount(2, $movements);
        $this->assertSame(9.4, $santan->currentStock()); // 10 - 0.3*2
        $this->assertSame(9.6, $daging->currentStock()); // 10 - 0.2*2
    }

    public function test_sell_menu_is_all_or_nothing_when_one_material_short(): void
    {
        $category = Category::create(['name' => 'Umum']);
        $santan = RawMaterial::create(['category_id' => $category->id, 'name' => 'Santan', 'unit' => MaterialUnit::Liter, 'minimum_stock' => 0]);
        $daging = RawMaterial::create(['category_id' => $category->id, 'name' => 'Daging', 'unit' => MaterialUnit::Kilogram, 'minimum_stock' => 0]);
        $this->service->stockIn($santan, 10, 12000);
        $this->service->stockIn($daging, 0.1, 130000); // not enough

        $menu = Menu::create(['name' => 'Rendang', 'price' => 28000]);
        $menu->materials()->attach([
            $santan->id => ['quantity' => 0.3],
            $daging->id => ['quantity' => 0.2],
        ]);

        try {
            $this->service->sellMenu($menu, 1);
            $this->fail('Expected InsufficientStockException');
        } catch (InsufficientStockException) {
            // Santan must NOT have been deducted (transaction rolled back).
            $this->assertSame(10.0, $santan->fresh()->currentStock());
        }
    }
}
