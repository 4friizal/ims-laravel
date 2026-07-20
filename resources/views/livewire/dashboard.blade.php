<div>
    @php $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.'); @endphp

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h1 class="text-xl font-semibold text-gray-900 mb-1">Dasbor</h1>
        <p class="text-sm text-gray-500">Selamat datang kembali, <span
                class="font-medium text-gray-700">{{ auth()->user()->name }}</span>!</p>
    </div>

    {{-- Stat tiles --}}
    <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Peran Anda</p>
            <p class="text-lg font-semibold text-indigo-900 mt-1">{{ auth()->user()->role->label() }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Bahan Baku</p>
            <p class="text-2xl font-semibold text-gray-900 mt-1">{{ $totalMaterials }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Menu</p>
            <p class="text-2xl font-semibold text-gray-900 mt-1">{{ $totalMenus }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Stok Rendah</p>
            <p class="text-2xl font-semibold {{ $lowStockCount > 0 ? 'text-red-600' : 'text-green-600' }} mt-1">{{ $lowStockCount }}</p>
        </div>
    </div>

    {{-- Restock recommendations --}}
    <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <div>
                <h2 class="text-base font-semibold text-gray-900">Rekomendasi Restock (AI)</h2>
                <p class="text-sm text-gray-500 mt-0.5">Prediksi bahan baku yang perlu dibeli dalam 7 hari ke depan.</p>
            </div>
            <a class="text-sm font-medium text-indigo-600 hover:text-indigo-800" href="{{ route('inventory.prediction') }}">
                Lihat semua →
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bahan Baku</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Stok Saat Ini</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Perkiraan 7 Hari</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Rekomendasi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse ($recommendations as $row)
                        @php $d = $row['data']; $unit = $row['material']->unit->shortLabel(); @endphp
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $row['material']->name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600 text-right">{{ $fmt($d['current_stock']) }} {{ $unit }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600 text-right">{{ $fmt($d['projected_demand']) }} {{ $unit }}</td>
                            <td class="px-6 py-4 text-right">
                                <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-amber-100 text-amber-700">
                                    + {{ $fmt($d['recommended_restock']) }} {{ $unit }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-6 py-12 text-center text-sm text-gray-500" colspan="4">
                                Semua stok mencukupi untuk 7 hari ke depan. 🎉
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
