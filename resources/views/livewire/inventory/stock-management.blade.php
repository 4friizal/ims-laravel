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
                <h1 class="text-xl font-semibold text-gray-900">Stok Masuk & Keluar</h1>
                <p class="text-sm text-gray-500 mt-0.5">Catat pembelian (masuk) dan pemakaian (keluar).</p>
            </div>
            <div class="flex gap-2">
                <button
                    class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors"
                    wire:click="openIn"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Stok Masuk
                </button>
                <button
                    class="inline-flex items-center gap-2 px-4 py-2 bg-amber-600 text-white text-sm font-medium rounded-lg hover:bg-amber-700 transition-colors"
                    wire:click="openOut"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                    </svg>
                    Stok Keluar
                </button>
            </div>
        </div>

        {{-- Filter --}}
        <div class="px-6 py-3 border-b border-gray-100">
            <select
                class="w-full sm:w-52 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                wire:model.live="typeFilter"
            >
                <option value="">Semua pergerakan</option>
                @foreach ($types as $t)
                    <option value="{{ $t->value }}">{{ $t->label() }}</option>
                @endforeach
            </select>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bahan Baku</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipe</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Harga/Satuan</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse ($movements as $movement)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $movement->occurred_at->format('d M Y') }}</td>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $movement->rawMaterial?->name ?? '—' }}</td>
                            <td class="px-6 py-4">
                                @if ($movement->type === \App\Enums\MovementType::In)
                                    <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-700">Masuk</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-amber-100 text-amber-700">Keluar</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900 text-right">
                                {{ rtrim(rtrim(number_format((float) $movement->quantity, 2), '0'), '.') }}
                                {{ $movement->rawMaterial?->unit->shortLabel() }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600 text-right">
                                {{ $movement->unit_cost !== null ? 'Rp ' . number_format((float) $movement->unit_cost, 0, ',', '.') : '—' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $movement->reference ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-6 py-12 text-center text-sm text-gray-500" colspan="6">
                                Belum ada pergerakan stok.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($movements->hasPages())
            <div class="px-6 py-4 border-t border-gray-100">
                {{ $movements->links() }}
            </div>
        @endif
    </div>

    {{-- Stock In / Out Modal --}}
    @if ($showFormModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center" wire:keydown.escape="closeModals">
            <div class="absolute inset-0 bg-black/40" wire:click="closeModals"></div>

            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="text-lg font-semibold text-gray-900">
                        {{ $mode === 'in' ? 'Catat Stok Masuk' : 'Catat Stok Keluar' }}
                    </h2>
                    <button class="text-gray-400 hover:text-gray-600 transition-colors" wire:click="closeModals">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form class="space-y-4" wire:submit="save">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bahan Baku</label>
                        <select
                            class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent @error('materialId') border-red-400 @else border-gray-300 @enderror"
                            wire:model="materialId"
                        >
                            <option value="">Pilih bahan baku…</option>
                            @foreach ($materials as $material)
                                <option value="{{ $material->id }}">{{ $material->name }} ({{ $material->unit->shortLabel() }})</option>
                            @endforeach
                        </select>
                        @error('materialId')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah</label>
                        <input
                            class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent @error('quantity') border-red-400 @else border-gray-300 @enderror"
                            wire:model="quantity"
                            type="number"
                            step="0.01"
                            min="0"
                        />
                        @error('quantity')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    @if ($mode === 'in')
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Harga / Satuan</label>
                                <input
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent @error('unitCost') border-red-400 @else border-gray-300 @enderror"
                                    wire:model="unitCost"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                />
                                @error('unitCost')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Diterima</label>
                                <input
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent @error('receivedAt') border-red-400 @else border-gray-300 @enderror"
                                    wire:model="receivedAt"
                                    type="date"
                                />
                                @error('receivedAt')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan <span class="text-gray-400 font-normal">(opsional)</span></label>
                        <input
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            wire:model="reference"
                            type="text"
                            placeholder="{{ $mode === 'in' ? 'mis. Pembelian dari pemasok' : 'mis. Pemakaian dapur' }}"
                        />
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                            type="button"
                            wire:click="closeModals"
                        >Batal</button>
                        <button
                            class="px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors {{ $mode === 'in' ? 'bg-green-600 hover:bg-green-700' : 'bg-amber-600 hover:bg-amber-700' }}"
                            type="submit"
                        >{{ $mode === 'in' ? 'Catat Masuk' : 'Catat Keluar' }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
