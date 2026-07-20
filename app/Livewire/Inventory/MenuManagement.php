<?php

namespace App\Livewire\Inventory;

use App\Models\Menu;
use App\Models\RawMaterial;
use App\Services\InsufficientStockException;
use App\Services\InventoryService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class MenuManagement extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showFormModal = false;

    public bool $showDeleteModal = false;

    public bool $showSellModal = false;

    // Menu form
    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public string $price = '0';

    public bool $isActive = true;

    /** @var array<int, array{raw_material_id: string, quantity: string}> */
    public array $recipe = [];

    // Delete
    public ?int $deletingId = null;

    public string $deletingName = '';

    // Sell
    public ?int $sellingId = null;

    public string $sellingName = '';

    public string $portions = '1';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->recipe = [['raw_material_id' => '', 'quantity' => '']];
        $this->showFormModal = true;
    }

    public function openEdit(int $menuId): void
    {
        $menu = Menu::with('materials')->findOrFail($menuId);
        $this->editingId = $menu->id;
        $this->name = $menu->name;
        $this->description = (string) $menu->description;
        $this->price = (string) $menu->price;
        $this->isActive = (bool) $menu->is_active;
        $this->recipe = $menu->materials->map(fn ($m) => [
            'raw_material_id' => (string) $m->id,
            'quantity' => (string) $m->pivot->quantity,
        ])->values()->all();
        if (empty($this->recipe)) {
            $this->recipe = [['raw_material_id' => '', 'quantity' => '']];
        }
        $this->resetErrorBag();
        $this->showFormModal = true;
    }

    public function addRecipeRow(): void
    {
        $this->recipe[] = ['raw_material_id' => '', 'quantity' => ''];
    }

    public function removeRecipeRow(int $index): void
    {
        unset($this->recipe[$index]);
        $this->recipe = array_values($this->recipe);
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'recipe' => 'required|array|min:1',
            'recipe.*.raw_material_id' => 'required|exists:raw_materials,id',
            'recipe.*.quantity' => 'required|numeric|gt:0',
        ], [
            'name.required' => 'Nama menu wajib diisi.',
            'price.required' => 'Harga wajib diisi.',
            'recipe.required' => 'Menu harus memiliki minimal satu bahan baku.',
            'recipe.min' => 'Menu harus memiliki minimal satu bahan baku.',
            'recipe.*.raw_material_id.required' => 'Bahan baku wajib dipilih.',
            'recipe.*.quantity.required' => 'Jumlah wajib diisi.',
            'recipe.*.quantity.gt' => 'Jumlah harus lebih dari nol.',
        ]);

        $data = [
            'name' => $this->name,
            'description' => $this->description ?: null,
            'price' => (float) $this->price,
            'is_active' => $this->isActive,
        ];

        $menu = $this->editingId
            ? tap(Menu::findOrFail($this->editingId))->update($data)
            : Menu::create($data);

        // Sync recipe (last quantity wins if the same material appears twice).
        $sync = [];
        foreach ($this->recipe as $row) {
            $sync[(int) $row['raw_material_id']] = ['quantity' => (float) $row['quantity']];
        }
        $menu->materials()->sync($sync);

        session()->flash('success', $this->editingId ? 'Menu berhasil diperbarui.' : 'Menu berhasil dibuat.');
        $this->showFormModal = false;
        $this->resetForm();
    }

    public function openDelete(int $menuId): void
    {
        $menu = Menu::findOrFail($menuId);
        $this->deletingId = $menu->id;
        $this->deletingName = $menu->name;
        $this->showDeleteModal = true;
    }

    public function confirmDelete(): void
    {
        Menu::findOrFail($this->deletingId)->delete();
        session()->flash('success', 'Menu berhasil dihapus.');
        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingName = '';
    }

    public function openSell(int $menuId): void
    {
        $menu = Menu::findOrFail($menuId);
        $this->sellingId = $menu->id;
        $this->sellingName = $menu->name;
        $this->portions = '1';
        $this->resetErrorBag();
        $this->showSellModal = true;
    }

    public function confirmSell(InventoryService $inventory): void
    {
        $this->validate([
            'portions' => 'required|numeric|gt:0',
        ], [
            'portions.required' => 'Jumlah porsi wajib diisi.',
            'portions.gt' => 'Jumlah porsi harus lebih dari nol.',
        ]);

        $menu = Menu::with('materials')->findOrFail($this->sellingId);

        try {
            $inventory->sellMenu($menu, (float) $this->portions);
            session()->flash('success', "Penjualan {$menu->name} dicatat. Bahan baku dikurangi secara FIFO.");
        } catch (InsufficientStockException $e) {
            $this->addError('portions', $e->getMessage());

            return;
        }

        $this->showSellModal = false;
        $this->sellingId = null;
        $this->sellingName = '';
    }

    public function closeModals(): void
    {
        $this->showFormModal = false;
        $this->showDeleteModal = false;
        $this->showSellModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->description = '';
        $this->price = '0';
        $this->isActive = true;
        $this->recipe = [];
        $this->resetErrorBag();
    }

    public function render()
    {
        $menus = Menu::query()
            ->with('materials')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.inventory.menu-management', [
            'menus' => $menus,
            'materials' => RawMaterial::orderBy('name')->get(),
        ]);
    }
}
