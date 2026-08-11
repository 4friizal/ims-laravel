<?php

namespace App\Livewire\Inventory;

use App\Enums\MaterialUnit;
use App\Models\Category;
use App\Models\RawMaterial;
use App\Services\InventoryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class RawMaterialManagement extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showFormModal = false;

    public bool $showDeleteModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $categoryId = '';

    public string $unit = '';

    public string $minimumStock = '0';

    /** Opening stock recorded as the first FIFO batch (create only). */
    public string $initialStock = '0';

    /** Unit cost of the opening stock batch (create only). */
    public string $initialUnitCost = '0';

    /** Read-only current stock shown while editing. */
    public float $editingCurrentStock = 0.0;

    public ?int $deletingId = null;

    public string $deletingName = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function openEdit(int $materialId): void
    {
        $material = RawMaterial::findOrFail($materialId);
        $this->editingId = $material->id;
        $this->name = $material->name;
        $this->categoryId = (string) $material->category_id;
        $this->unit = $material->unit->value;
        $this->minimumStock = (string) $material->minimum_stock;
        $this->editingCurrentStock = $material->currentStock();
        $this->resetErrorBag();
        $this->showFormModal = true;
    }

    public function save(InventoryService $inventory): void
    {
        $isCreating = $this->editingId === null;

        $rules = [
            'name' => 'required|string|max:255',
            'categoryId' => 'required|exists:categories,id',
            'unit' => 'required|in:'.collect(MaterialUnit::cases())->map->value->implode(','),
            'minimumStock' => 'required|numeric|min:0',
        ];

        $messages = [
            'name.required' => 'Nama bahan baku wajib diisi.',
            'categoryId.required' => 'Kategori wajib dipilih.',
            'categoryId.exists' => 'Kategori tidak valid.',
            'unit.required' => 'Satuan wajib dipilih.',
            'minimumStock.required' => 'Stok minimum wajib diisi.',
            'minimumStock.numeric' => 'Stok minimum harus berupa angka.',
        ];

        // Opening stock is only accepted on create; afterwards stock changes
        // must go through stock in/out so every movement stays auditable.
        if ($isCreating) {
            $rules['initialStock'] = 'bail|required|numeric|min:0|gte:minimumStock';
            $rules['initialUnitCost'] = 'required|numeric|min:0';
            $messages['initialStock.required'] = 'Stok awal wajib diisi.';
            $messages['initialStock.numeric'] = 'Stok awal harus berupa angka.';
            $messages['initialStock.min'] = 'Stok awal tidak boleh negatif.';
            $messages['initialStock.gte'] = 'Stok awal harus sama dengan atau lebih besar dari stok minimum.';
            $messages['initialUnitCost.required'] = 'Harga satuan wajib diisi (isi 0 bila belum diketahui).';
            $messages['initialUnitCost.numeric'] = 'Harga satuan harus berupa angka.';
        }

        $this->validate($rules, $messages);

        $data = [
            'name' => $this->name,
            'category_id' => (int) $this->categoryId,
            'unit' => $this->unit,
            'minimum_stock' => (float) $this->minimumStock,
        ];

        if ($isCreating) {
            $openingStock = (float) $this->initialStock;

            DB::transaction(function () use ($data, $openingStock, $inventory) {
                $material = RawMaterial::create($data);

                if ($openingStock > 0) {
                    $inventory->stockIn(
                        $material,
                        $openingStock,
                        (float) $this->initialUnitCost,
                        Carbon::today(),
                        'Stok awal bahan baku',
                        'Stok awal',
                    );
                }
            });

            session()->flash('success', $openingStock > 0
                ? 'Bahan baku berhasil dibuat dengan stok awal '.$this->formatQuantity($openingStock).' '.MaterialUnit::from($this->unit)->shortLabel().'.'
                : 'Bahan baku berhasil dibuat.');
        } else {
            RawMaterial::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Bahan baku berhasil diperbarui.');
        }

        $this->showFormModal = false;
        $this->resetForm();
    }

    public function openDelete(int $materialId): void
    {
        $material = RawMaterial::findOrFail($materialId);
        $this->deletingId = $material->id;
        $this->deletingName = $material->name;
        $this->showDeleteModal = true;
    }

    public function confirmDelete(): void
    {
        RawMaterial::findOrFail($this->deletingId)->delete();
        session()->flash('success', 'Bahan baku berhasil dihapus.');
        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingName = '';
    }

    public function closeModals(): void
    {
        $this->showFormModal = false;
        $this->showDeleteModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->categoryId = '';
        $this->unit = '';
        $this->minimumStock = '0';
        $this->initialStock = '0';
        $this->initialUnitCost = '0';
        $this->editingCurrentStock = 0.0;
        $this->resetErrorBag();
    }

    private function formatQuantity(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }

    public function render()
    {
        $materials = RawMaterial::query()
            ->with('category')
            ->withSum('batches as current_stock', 'remaining_quantity')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.inventory.raw-material-management', [
            'materials' => $materials,
            'categories' => Category::orderBy('name')->get(),
            'units' => MaterialUnit::cases(),
        ]);
    }
}
