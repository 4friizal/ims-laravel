<?php

namespace App\Livewire\Inventory;

use App\Enums\MovementType;
use App\Models\RawMaterial;
use App\Models\StockMovement;
use App\Services\InsufficientStockException;
use App\Services\InventoryService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class StockManagement extends Component
{
    use WithPagination;

    public string $typeFilter = '';

    // Modal / form state. mode = 'in' | 'out'
    public bool $showFormModal = false;

    public string $mode = 'in';

    public string $materialId = '';

    public string $quantity = '';

    public string $unitCost = '';

    public string $receivedAt = '';

    public string $reference = '';

    public string $note = '';

    public function mount(): void
    {
        $this->receivedAt = Carbon::today()->toDateString();
    }

    public function openIn(): void
    {
        $this->resetForm();
        $this->mode = 'in';
        $this->showFormModal = true;
    }

    public function openOut(): void
    {
        $this->resetForm();
        $this->mode = 'out';
        $this->showFormModal = true;
    }

    public function save(InventoryService $inventory): void
    {
        if ($this->mode === 'in') {
            $this->validate([
                'materialId' => 'required|exists:raw_materials,id',
                'quantity' => 'required|numeric|gt:0',
                'unitCost' => 'required|numeric|min:0',
                'receivedAt' => 'required|date',
            ], [
                'materialId.required' => 'Bahan baku wajib dipilih.',
                'quantity.required' => 'Jumlah wajib diisi.',
                'quantity.gt' => 'Jumlah harus lebih dari nol.',
                'unitCost.required' => 'Harga satuan wajib diisi.',
                'receivedAt.required' => 'Tanggal wajib diisi.',
            ]);

            $material = RawMaterial::findOrFail((int) $this->materialId);
            $inventory->stockIn(
                $material,
                (float) $this->quantity,
                (float) $this->unitCost,
                Carbon::parse($this->receivedAt),
                $this->note ?: null,
                $this->reference ?: null,
            );
            session()->flash('success', 'Stok masuk berhasil dicatat.');
        } else {
            $this->validate([
                'materialId' => 'required|exists:raw_materials,id',
                'quantity' => 'required|numeric|gt:0',
            ], [
                'materialId.required' => 'Bahan baku wajib dipilih.',
                'quantity.required' => 'Jumlah wajib diisi.',
                'quantity.gt' => 'Jumlah harus lebih dari nol.',
            ]);

            $material = RawMaterial::findOrFail((int) $this->materialId);

            try {
                $inventory->stockOut(
                    $material,
                    (float) $this->quantity,
                    $this->reference ?: null,
                );
                session()->flash('success', 'Stok keluar berhasil dicatat (FIFO).');
            } catch (InsufficientStockException $e) {
                $this->addError('quantity', $e->getMessage());

                return;
            }
        }

        $this->showFormModal = false;
        $this->resetForm();
    }

    public function closeModals(): void
    {
        $this->showFormModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->materialId = '';
        $this->quantity = '';
        $this->unitCost = '';
        $this->receivedAt = Carbon::today()->toDateString();
        $this->reference = '';
        $this->note = '';
        $this->resetErrorBag();
    }

    public function render()
    {
        $movements = StockMovement::query()
            ->with(['rawMaterial', 'menu'])
            ->when($this->typeFilter, fn ($q) => $q->where('type', $this->typeFilter))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(15);

        return view('livewire.inventory.stock-management', [
            'movements' => $movements,
            'materials' => RawMaterial::with('category')->orderBy('name')->get(),
            'types' => MovementType::cases(),
        ]);
    }
}
