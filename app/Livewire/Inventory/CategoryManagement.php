<?php

namespace App\Livewire\Inventory;

use App\Models\Category;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class CategoryManagement extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showFormModal = false;

    public bool $showDeleteModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

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

    public function openEdit(int $categoryId): void
    {
        $category = Category::findOrFail($categoryId);
        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->description = (string) $category->description;
        $this->resetErrorBag();
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $isCreating = $this->editingId === null;

        $this->validate([
            'name' => 'required|string|max:255|unique:categories,name'.($isCreating ? '' : ','.$this->editingId),
            'description' => 'nullable|string|max:255',
        ], [
            'name.required' => 'Nama kategori wajib diisi.',
            'name.unique' => 'Kategori ini sudah ada.',
        ]);

        $data = [
            'name' => $this->name,
            'description' => $this->description ?: null,
        ];

        if ($isCreating) {
            Category::create($data);
            session()->flash('success', 'Kategori berhasil dibuat.');
        } else {
            Category::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Kategori berhasil diperbarui.');
        }

        $this->showFormModal = false;
        $this->resetForm();
    }

    public function openDelete(int $categoryId): void
    {
        $category = Category::findOrFail($categoryId);
        $this->deletingId = $category->id;
        $this->deletingName = $category->name;
        $this->showDeleteModal = true;
    }

    public function confirmDelete(): void
    {
        $category = Category::withCount('rawMaterials')->findOrFail($this->deletingId);

        if ($category->raw_materials_count > 0) {
            session()->flash('error', 'Tidak dapat menghapus kategori yang masih memiliki bahan baku.');
            $this->showDeleteModal = false;

            return;
        }

        $category->delete();
        session()->flash('success', 'Kategori berhasil dihapus.');
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
        $this->description = '';
        $this->resetErrorBag();
    }

    public function render()
    {
        $categories = Category::query()
            ->withCount('rawMaterials')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.inventory.category-management', [
            'categories' => $categories,
        ]);
    }
}
