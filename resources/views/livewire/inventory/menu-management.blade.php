<div>
    {{-- Flash messages --}}
    @if (session('success'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800">
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Menu</h1>
                <p class="text-sm text-gray-500 mt-0.5">Menu beserta resep bahan bakunya. Mencatat penjualan mengurangi stok secara FIFO.</p>
            </div>
            <button
                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors"
                wire:click="openCreate"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Tambah Menu
            </button>
        </div>

        {{-- Search --}}
        <div class="px-6 py-3 border-b border-gray-100">
            <input
                class="w-full sm:w-72 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                wire:model.live.debounce.300ms="search"
                type="text"
                placeholder="Cari menu…"
            />
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Menu</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bahan Baku (Resep)</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse ($menus as $menu)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $menu->name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">Rp {{ number_format((float) $menu->price, 0, ',', '.') }}</td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    @forelse ($menu->materials as $material)
                                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-indigo-50 text-indigo-700">
                                            {{ $material->name }}
                                            ({{ rtrim(rtrim(number_format((float) $material->pivot->quantity, 2), '0'), '.') }} {{ $material->unit->shortLabel() }})
                                        </span>
                                    @empty
                                        <span class="text-xs text-gray-400">Belum ada resep</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                @if ($menu->is_active)
                                    <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-700">Aktif</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-600">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button
                                        class="px-3 py-1.5 text-xs font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors"
                                        wire:click="openSell({{ $menu->id }})"
                                    >Catat Penjualan</button>
                                    <button
                                        class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                                        wire:click="openEdit({{ $menu->id }})"
                                    >Ubah</button>
                                    <button
                                        class="px-3 py-1.5 text-xs font-medium text-red-600 bg-white border border-red-200 rounded-lg hover:bg-red-50 transition-colors"
                                        wire:click="openDelete({{ $menu->id }})"
                                    >Hapus</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-6 py-12 text-center text-sm text-gray-500" colspan="5">
                                Tidak ada menu ditemukan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($menus->hasPages())
            <div class="px-6 py-4 border-t border-gray-100">
                {{ $menus->links() }}
            </div>
        @endif
    </div>

    {{-- Create / Edit Modal --}}
    @if ($showFormModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center" wire:keydown.escape="closeModals">
            <div class="absolute inset-0 bg-black/40" wire:click="closeModals"></div>

            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 p-6 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="text-lg font-semibold text-gray-900">
                        {{ $editingId ? 'Ubah Menu' : 'Tambah Menu' }}
                    </h2>
                    <button class="text-gray-400 hover:text-gray-600 transition-colors" wire:click="closeModals">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form class="space-y-4" wire:submit="save">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Menu</label>
                        <input
                            class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent @error('name') border-red-400 @else border-gray-300 @enderror"
                            wire:model="name"
                            type="text"
                            autocomplete="off"
                        />
                        @error('name')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi <span class="text-gray-400 font-normal">(opsional)</span></label>
                        <input
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            wire:model="description"
                            type="text"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Harga (Rp)</label>
                            <input
                                class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent @error('price') border-red-400 @else border-gray-300 @enderror"
                                wire:model="price"
                                type="number"
                                step="0.01"
                                min="0"
                            />
                            @error('price')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex items-end pb-2">
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" wire:model="isActive" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                Menu aktif
                            </label>
                        </div>
                    </div>

                    {{-- Recipe editor --}}
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-sm font-medium text-gray-700">Resep (Bahan Baku)</label>
                            <button
                                type="button"
                                class="text-xs font-medium text-indigo-600 hover:text-indigo-800"
                                wire:click="addRecipeRow"
                            >+ Tambah bahan</button>
                        </div>
                        @error('recipe')
                            <p class="mb-2 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                        <div class="space-y-2">
                            @foreach ($recipe as $index => $row)
                                <div class="flex gap-2 items-start">
                                    <div class="flex-1">
                                        <select
                                            class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent @error('recipe.' . $index . '.raw_material_id') border-red-400 @else border-gray-300 @enderror"
                                            wire:model="recipe.{{ $index }}.raw_material_id"
                                        >
                                            <option value="">Pilih bahan…</option>
                                            @foreach ($materials as $material)
                                                <option value="{{ $material->id }}">{{ $material->name }} ({{ $material->unit->shortLabel() }})</option>
                                            @endforeach
                                        </select>
                                        @error('recipe.' . $index . '.raw_material_id')
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="w-28">
                                        <input
                                            class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent @error('recipe.' . $index . '.quantity') border-red-400 @else border-gray-300 @enderror"
                                            wire:model="recipe.{{ $index }}.quantity"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            placeholder="Jumlah"
                                        />
                                        @error('recipe.' . $index . '.quantity')
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <button
                                        type="button"
                                        class="mt-1 p-2 text-gray-400 hover:text-red-600 transition-colors"
                                        wire:click="removeRecipeRow({{ $index }})"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                        <p class="mt-1 text-xs text-gray-400">Jumlah per satu porsi menu.</p>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                            type="button"
                            wire:click="closeModals"
                        >Batal</button>
                        <button
                            class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors"
                            type="submit"
                        >{{ $editingId ? 'Simpan Perubahan' : 'Buat Menu' }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Sell Modal --}}
    @if ($showSellModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center" wire:keydown.escape="closeModals">
            <div class="absolute inset-0 bg-black/40" wire:click="closeModals"></div>

            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-sm mx-4 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">Catat Penjualan</h2>
                    <button class="text-gray-400 hover:text-gray-600 transition-colors" wire:click="closeModals">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form wire:submit="confirmSell">
                    <p class="text-sm text-gray-600 mb-4">
                        Menjual <span class="font-medium text-gray-900">{{ $sellingName }}</span> akan mengurangi
                        stok bahan bakunya secara FIFO (batch terlama lebih dulu).
                    </p>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Porsi</label>
                        <input
                            class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent @error('portions') border-red-400 @else border-gray-300 @enderror"
                            wire:model="portions"
                            type="number"
                            step="1"
                            min="1"
                        />
                        @error('portions')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-end gap-3 mt-6">
                        <button
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                            type="button"
                            wire:click="closeModals"
                        >Batal</button>
                        <button
                            class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors"
                            type="submit"
                        >Catat Penjualan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Delete Confirmation Modal --}}
    @if ($showDeleteModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/40" wire:click="closeModals"></div>

            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-sm mx-4 p-6">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Hapus Menu</h2>
                        <p class="mt-1 text-sm text-gray-600">
                            Apakah Anda yakin ingin menghapus <span class="font-medium text-gray-900">{{ $deletingName }}</span>?
                            Tindakan ini tidak dapat dibatalkan.
                        </p>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                        wire:click="closeModals"
                    >Batal</button>
                    <button
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors"
                        wire:click="confirmDelete"
                    >Hapus</button>
                </div>
            </div>
        </div>
    @endif
</div>
