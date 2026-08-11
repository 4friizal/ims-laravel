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
                <h1 class="text-xl font-semibold text-gray-900">Bahan Baku</h1>
                <p class="text-sm text-gray-500 mt-0.5">Daftar bahan baku beserta stok saat ini.</p>
            </div>
            <button
                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors"
                wire:click="openCreate"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Tambah Bahan Baku
            </button>
        </div>

        {{-- Search --}}
        <div class="px-6 py-3 border-b border-gray-100">
            <input
                class="w-full sm:w-72 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                wire:model.live.debounce.300ms="search"
                type="text"
                placeholder="Cari bahan baku…"
            />
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Satuan</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Stok Saat Ini</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Stok Min.</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse ($materials as $material)
                        @php
                            $stock = (float) ($material->current_stock ?? 0);
                            $isLow = $stock <= (float) $material->minimum_stock;
                        @endphp
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $material->name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $material->category?->name ?? '—' }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $material->unit->shortLabel() }}</td>
                            <td class="px-6 py-4 text-right">
                                @if ($isLow)
                                    <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-red-100 text-red-700">
                                        {{ rtrim(rtrim(number_format($stock, 2), '0'), '.') }} {{ $material->unit->shortLabel() }} · rendah
                                    </span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-700">
                                        {{ rtrim(rtrim(number_format($stock, 2), '0'), '.') }} {{ $material->unit->shortLabel() }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600 text-right">
                                {{ rtrim(rtrim(number_format((float) $material->minimum_stock, 2), '0'), '.') }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button
                                        class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                                        wire:click="openEdit({{ $material->id }})"
                                    >Ubah</button>
                                    <button
                                        class="px-3 py-1.5 text-xs font-medium text-red-600 bg-white border border-red-200 rounded-lg hover:bg-red-50 transition-colors"
                                        wire:click="openDelete({{ $material->id }})"
                                    >Hapus</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-6 py-12 text-center text-sm text-gray-500" colspan="6">
                                Tidak ada bahan baku ditemukan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($materials->hasPages())
            <div class="px-6 py-4 border-t border-gray-100">
                {{ $materials->links() }}
            </div>
        @endif
    </div>

    {{-- Create / Edit Modal --}}
    @if ($showFormModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center" wire:keydown.escape="closeModals">
            <div class="absolute inset-0 bg-black/40" wire:click="closeModals"></div>

            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="text-lg font-semibold text-gray-900">
                        {{ $editingId ? 'Ubah Bahan Baku' : 'Tambah Bahan Baku' }}
                    </h2>
                    <button class="text-gray-400 hover:text-gray-600 transition-colors" wire:click="closeModals">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form class="space-y-4" wire:submit="save">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama</label>
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
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kategori</label>
                        <select
                            class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent @error('categoryId') border-red-400 @else border-gray-300 @enderror"
                            wire:model="categoryId"
                        >
                            <option value="">Pilih kategori…</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('categoryId')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Satuan</label>
                            <select
                                class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent @error('unit') border-red-400 @else border-gray-300 @enderror"
                                wire:model="unit"
                            >
                                <option value="">Pilih…</option>
                                @foreach ($units as $u)
                                    <option value="{{ $u->value }}">{{ $u->label() }}</option>
                                @endforeach
                            </select>
                            @error('unit')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Stok Minimum</label>
                            <input
                                class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent @error('minimumStock') border-red-400 @else border-gray-300 @enderror"
                                wire:model="minimumStock"
                                type="number"
                                step="0.01"
                                min="0"
                            />
                            <p class="mt-1 text-xs text-gray-500">Batas stok pengaman untuk peringatan stok rendah.</p>
                            @error('minimumStock')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    @if ($editingId)
                        {{-- Stock is never edited here: it only moves through stok masuk/keluar. --}}
                        <div class="rounded-lg bg-gray-50 border border-gray-200 px-3 py-2.5">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Stok saat ini</span>
                                <span class="text-sm font-semibold text-gray-900">
                                    {{ rtrim(rtrim(number_format($editingCurrentStock, 2), '0'), '.') }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">
                                Perubahan stok dicatat lewat menu <span class="font-medium">Stok Masuk / Stok Keluar</span> agar riwayatnya terlacak.
                            </p>
                        </div>
                    @else
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Stok Awal</label>
                                <input
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent @error('initialStock') border-red-400 @else border-gray-300 @enderror"
                                    wire:model="initialStock"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                />
                                <p class="mt-1 text-xs text-gray-500">Minimal sama dengan stok minimum, langsung tercatat sebagai stok saat ini.</p>
                                @error('initialStock')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Harga Satuan (Rp)</label>
                                <input
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent @error('initialUnitCost') border-red-400 @else border-gray-300 @enderror"
                                    wire:model="initialUnitCost"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                />
                                <p class="mt-1 text-xs text-gray-500">Harga beli per satuan untuk stok awal.</p>
                                @error('initialUnitCost')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    @endif

                    <div class="flex justify-end gap-3 pt-2">
                        <button
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                            type="button"
                            wire:click="closeModals"
                        >Batal</button>
                        <button
                            class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors"
                            type="submit"
                        >{{ $editingId ? 'Simpan Perubahan' : 'Buat Bahan Baku' }}</button>
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
                        <h2 class="text-base font-semibold text-gray-900">Hapus Bahan Baku</h2>
                        <p class="mt-1 text-sm text-gray-600">
                            Apakah Anda yakin ingin menghapus <span class="font-medium text-gray-900">{{ $deletingName }}</span>?
                            Semua batch dan riwayat terkait akan ikut terhapus.
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
