<?php

namespace Database\Seeders;

use App\Enums\MaterialUnit;
use App\Models\Category;
use App\Models\MaterialUsage;
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

        // --- Raw materials --- [key => [name, category, unit, minimum_stock, base daily usage, unit cost]]
        $materialDefs = [
            'santan' => ['Santan', 'santan', MaterialUnit::Liter, 10, 8.0, 12000],
            'daging_sapi' => ['Daging Sapi', 'protein', MaterialUnit::Kilogram, 8, 6.0, 130000],
            'ayam' => ['Ayam', 'protein', MaterialUnit::Kilogram, 8, 7.0, 35000],
            'cabai_merah' => ['Cabai Merah', 'bumbu', MaterialUnit::Kilogram, 5, 4.5, 45000],
            'bawang_putih' => ['Bawang Putih', 'bumbu', MaterialUnit::Kilogram, 3, 2.0, 30000],
            'bawang_merah' => ['Bawang Merah', 'bumbu', MaterialUnit::Kilogram, 3, 2.5, 38000],
            'kentang' => ['Kentang', 'sayur', MaterialUnit::Kilogram, 5, 3.0, 18000],
        ];

        /** @var array<string, RawMaterial> $materials */
        $materials = [];
        foreach ($materialDefs as $key => [$name, $catKey, $unit, $min, $baseUsage, $unitCost]) {
            $materials[$key] = RawMaterial::create([
                'category_id' => $categories[$catKey]->id,
                'name' => $name,
                'unit' => $unit,
                'minimum_stock' => $min,
            ]);
        }

        // --- 90 days of mock usage (trend + weekly seasonality + noise) ---
        $days = 90;
        foreach ($materialDefs as $key => [$name, $catKey, $unit, $min, $baseUsage, $unitCost]) {
            $material = $materials[$key];
            // A gentle upward trend so regression predicts growth for most materials.
            $trendPerDay = $baseUsage * 0.004; // ~0.4% of base added per day

            for ($i = $days; $i >= 1; $i--) {
                $date = Carbon::today()->subDays($i);
                // Weekend uplift (Fri/Sat/Sun busier for a restaurant).
                $dow = $date->dayOfWeek;
                $seasonal = in_array($dow, [Carbon::FRIDAY, Carbon::SATURDAY, Carbon::SUNDAY], true) ? 1.25 : 1.0;
                $trend = $baseUsage + $trendPerDay * ($days - $i);
                $noise = mt_rand(-15, 15) / 100; // +/-15%
                $quantity = max(0, round($trend * $seasonal * (1 + $noise), 2));

                MaterialUsage::create([
                    'raw_material_id' => $material->id,
                    'usage_date' => $date,
                    'quantity' => $quantity,
                ]);
            }

            // --- Initial stock: a few FIFO batches at staggered dates/costs ---
            // Sized to ~7 days of usage so some materials fall below the forecast
            // horizon and surface a restock recommendation on the prediction page.
            $service->stockIn($material, $baseUsage * 4, $unitCost * 0.95, Carbon::today()->subDays(20), 'Stok awal', 'Stok awal');
            $service->stockIn($material, $baseUsage * 3, $unitCost, Carbon::today()->subDays(7), 'Pembelian rutin', 'Pembelian');
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
