<?php

namespace App\Livewire;

use App\Models\Menu;
use App\Models\RawMaterial;
use App\Services\StockPredictionService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public function render(StockPredictionService $prediction)
    {
        $materials = RawMaterial::with('category')
            ->withSum('batches as current_stock', 'remaining_quantity')
            ->get();

        $lowStock = $materials->filter(
            fn (RawMaterial $m) => (float) ($m->current_stock ?? 0) <= (float) $m->minimum_stock
        );

        // Top restock recommendations over the next 7 days.
        $recommendations = $materials
            ->map(fn (RawMaterial $m) => [
                'material' => $m,
                'data' => $prediction->recommendRestock($m, 7),
            ])
            ->filter(fn ($row) => $row['data']['needs_restock'])
            ->sortByDesc(fn ($row) => $row['data']['recommended_restock'])
            ->take(5)
            ->values();

        return view('livewire.dashboard', [
            'totalMaterials' => $materials->count(),
            'totalMenus' => Menu::count(),
            'lowStockCount' => $lowStock->count(),
            'recommendations' => $recommendations,
        ]);
    }
}
