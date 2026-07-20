<?php

namespace Database\Seeders;

use App\Enums\MaterialUnit;
use App\Models\Category;
use App\Models\Menu;
use App\Models\RawMaterial;
use App\Services\InventoryService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class InventorySeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $service = app(InventoryService::class);

        // --- Categories ---
        $categories = [
            'santan' => Category::create(['name' => 'Santan & Kelapa', 'description' => 'Bahan berbasis kelapa']),
            'protein' => Category::create(['name' => 'Daging & Protein', 'description' => 'Daging sapi, ayam, dan protein lain']),
            'bumbu' => Category::create(['name' => 'Bumbu & Rempah', 'description' => 'Cabai, bawang, dan rempah']),
            'sayur' => Category::create(['name' => 'Sayur & Umbi', 'description' => 'Sayuran dan umbi-umbian']),
        ];

        // --- Raw materials ---
        // [key => [name, category, unit, minimum_stock, base daily usage, unit cost, reorder-at days, order-up-to days]]
        // The two day figures are the purchasing policy used by the simulation:
        // reorder once cover drops below the first, then top up to the second.
        // Staples are bought in bulk (well above the 7-day forecast horizon) while
        // perishables are bought little and often, so the prediction page ends with
        // a mix of healthy and needs-restock rows instead of one uniform verdict.
        $materialDefs = [
            'santan' => ['Santan', 'santan', MaterialUnit::Liter, 10, 8.0, 12000, 8, 16],
            'daging_sapi' => ['Daging Sapi', 'protein', MaterialUnit::Kilogram, 8, 6.0, 130000, 3, 6],
            'ayam' => ['Ayam', 'protein', MaterialUnit::Kilogram, 8, 7.0, 35000, 9, 18],
            'cabai_merah' => ['Cabai Merah', 'bumbu', MaterialUnit::Kilogram, 5, 4.5, 45000, 2, 5],
            'bawang_putih' => ['Bawang Putih', 'bumbu', MaterialUnit::Kilogram, 3, 2.0, 30000, 10, 20],
            'bawang_merah' => ['Bawang Merah', 'bumbu', MaterialUnit::Kilogram, 3, 2.5, 38000, 4, 9],
            'kentang' => ['Kentang', 'sayur', MaterialUnit::Kilogram, 5, 3.0, 18000, 3, 7],
        ];

        /** @var array<string, RawMaterial> $materials */
        $materials = [];
        foreach ($materialDefs as $key => [$name, $catKey, $unit, $min, $baseUsage, $unitCost, $reorderDays, $coverDays]) {
            $materials[$key] = RawMaterial::create([
                'category_id' => $categories[$catKey]->id,
                'name' => $name,
                'unit' => $unit,
                'minimum_stock' => $min,
            ]);
        }

        // --- 3 months of mock stock movements (in + out) ---
        // Each day is replayed through InventoryService so batches, movements and
        // the MaterialUsage rows the AI trains on all stay consistent with FIFO.
        $days = 90;

        foreach ($materialDefs as $key => [$name, $catKey, $unit, $min, $baseUsage, $unitCost, $reorderDays, $coverDays]) {
            $material = $materials[$key];
            // A gentle upward trend so regression predicts growth for most materials.
            $trendPerDay = $baseUsage * 0.004; // ~0.4% of base added per day

            // Opening stock, received the day before the window starts.
            $service->stockIn(
                $material,
                round($baseUsage * $coverDays, 2),
                round($unitCost * 0.92),
                Carbon::today()->subDays($days + 1),
                'Stok awal',
                'Stok awal',
            );

            for ($i = $days; $i >= 1; $i--) {
                $date = Carbon::today()->subDays($i);
                $elapsed = $days - $i;

                // Weekend uplift (Fri/Sat/Sun busier for a restaurant).
                $dow = $date->dayOfWeek;
                $seasonal = in_array($dow, [Carbon::FRIDAY, Carbon::SATURDAY, Carbon::SUNDAY], true) ? 1.25 : 1.0;
                $trend = $baseUsage + $trendPerDay * $elapsed;
                $noise = mt_rand(-15, 15) / 100; // +/-15%
                $quantity = max(0, round($trend * $seasonal * (1 + $noise), 2));

                // --- Stock in: top up to the target cover once stock runs low ---
                // Blended daily demand (weekends are ~1.11x an average day).
                $expectedDaily = $trend * 1.11;
                $onHand = $material->currentStock();

                if ($onHand < $expectedDaily * $reorderDays) {
                    $orderQty = round(($expectedDaily * $coverDays) - $onHand, 2);
                    // Supplier prices inflate ~0.18%/day with a little variance.
                    $purchaseCost = round($unitCost * (0.92 + 0.0018 * $elapsed) * (1 + mt_rand(-4, 4) / 100));

                    $service->stockIn($material, $orderQty, $purchaseCost, $date, 'Pembelian rutin', 'Pembelian');
                    $onHand += $orderQty;
                }

                // --- Stock out: the day's kitchen consumption (FIFO + usage row) ---
                $quantity = min($quantity, $onHand);
                if ($quantity > 0) {
                    $service->stockOut($material, $quantity, 'Pemakaian dapur', null, $date);
                }
            }
        }

        // --- Menus + recipes (raw materials with per-portion quantity) ---
        $menuDefs = [
            ['Rendang Daging', 'Rendang sapi khas Padang', 28000, [
                'daging_sapi' => 0.20, 'santan' => 0.30, 'cabai_merah' => 0.08, 'bawang_putih' => 0.03, 'bawang_merah' => 0.04,
            ]],
            ['Gulai Ayam', 'Gulai ayam kuah santan', 22000, [
                'ayam' => 0.25, 'santan' => 0.25, 'cabai_merah' => 0.06, 'bawang_merah' => 0.04,
            ]],
            ['Ayam Pop', 'Ayam pop goreng khas Padang', 24000, [
                'ayam' => 0.28, 'bawang_putih' => 0.03,
            ]],
            ['Dendeng Balado', 'Dendeng sapi sambal balado', 30000, [
                'daging_sapi' => 0.18, 'cabai_merah' => 0.10, 'bawang_merah' => 0.05,
            ]],
            ['Gulai Kentang', 'Gulai kentang santan', 12000, [
                'kentang' => 0.20, 'santan' => 0.15, 'cabai_merah' => 0.04, 'bawang_merah' => 0.03,
            ]],
        ];

        foreach ($menuDefs as [$name, $desc, $price, $recipe]) {
            $menu = Menu::create([
                'name' => $name,
                'description' => $desc,
                'price' => $price,
                'is_active' => true,
            ]);

            $attach = [];
            foreach ($recipe as $matKey => $qty) {
                $attach[$materials[$matKey]->id] = ['quantity' => $qty];
            }
            $menu->materials()->attach($attach);
        }
    }
}
