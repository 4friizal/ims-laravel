<?php

namespace App\Livewire\Inventory;

use App\Models\RawMaterial;
use App\Services\StockPredictionService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class StockPrediction extends Component
{
    public ?int $horizonDays = 7;

    public ?int $selectedMaterialId = null;

    /** Days of the newest history held out as test data during evaluation. */
    public int $testDays = 14;

    public function mount(): void
    {
        $this->selectedMaterialId = RawMaterial::orderBy('name')->value('id');
    }

    public function updatedHorizonDays(StockPredictionService $prediction): void
    {
        $this->horizonDays = max(1, min(30, (int) $this->horizonDays));
        $this->pushChart($prediction);
    }

    public function updatedSelectedMaterialId(StockPredictionService $prediction): void
    {
        $this->pushChart($prediction);
    }

    private function pushChart(StockPredictionService $prediction): void
    {
        $material = RawMaterial::find($this->selectedMaterialId);
        if ($material) {
            $this->dispatch('chart-updated', series: $prediction->historyAndForecastSeries($material, $this->horizonDays));
        }
    }

    public function render(StockPredictionService $prediction)
    {
        $materials = RawMaterial::with('category')->orderBy('name')->get();

        $recommendations = $materials->map(function (RawMaterial $material) use ($prediction) {
            return [
                'material' => $material,
                'data' => $prediction->recommendRestock($material, $this->horizonDays),
            ];
        });

        $selected = $materials->firstWhere('id', $this->selectedMaterialId) ?? $materials->first();
        $chart = $selected
            ? $prediction->historyAndForecastSeries($selected, $this->horizonDays)
            : ['labels' => [], 'actual' => [], 'forecast' => []];

        return view('livewire.inventory.stock-prediction', [
            'materials' => $materials,
            'recommendations' => $recommendations,
            'selected' => $selected,
            'chart' => $chart,
            'evaluation' => $selected ? $prediction->evaluate($selected, $this->testDays) : null,
            'overallEvaluation' => $prediction->evaluateAll($materials, $this->testDays),
        ]);
    }
}
