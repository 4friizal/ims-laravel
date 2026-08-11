<div>
    @php
        $needsRestock = $recommendations->filter(fn ($r) => $r['data']['needs_restock']);
        $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    @endphp

    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-gray-900">Prediksi & Rekomendasi Stok (AI)</h1>
        <p class="text-sm text-gray-500 mt-0.5">
            Prediksi kebutuhan bahan baku menggunakan regresi linear atas riwayat pemakaian harian.
        </p>
    </div>

    {{-- Summary tiles --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Bahan Baku</p>
            <p class="text-2xl font-semibold text-gray-900 mt-1">{{ $materials->count() }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Perlu Restock</p>
            <p class="text-2xl font-semibold text-amber-600 mt-1">{{ $needsRestock->count() }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Horizon Prediksi</p>
            <div class="flex items-center gap-2 mt-1">
                <input
                    class="w-20 px-2 py-1 text-lg font-semibold text-gray-900 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    wire:model.live.debounce.500ms="horizonDays"
                    type="number"
                    min="1"
                    max="30"
                />
                <span class="text-sm text-gray-500">hari ke depan</span>
            </div>
        </div>
    </div>

    {{-- Chart --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <div>
                <h2 class="text-base font-semibold text-gray-900">Riwayat & Perkiraan Pemakaian</h2>
                <p class="text-sm text-gray-500 mt-0.5">Garis padat = pemakaian aktual, garis putus-putus = perkiraan.</p>
            </div>
            <select
                class="w-full sm:w-56 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                wire:model.live="selectedMaterialId"
            >
                @foreach ($materials as $material)
                    <option value="{{ $material->id }}">{{ $material->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="p-6">
            <div wire:ignore>
                <div style="position: relative; height: 320px;">
                    <canvas id="usageChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Model evaluation --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Evaluasi Akurasi Model</h2>
            <p class="text-sm text-gray-500 mt-0.5">
                Model dilatih pada data lama, lalu diuji pada {{ $testDays }} hari terakhir yang tidak pernah dilihat saat pelatihan.
            </p>
        </div>

        @if ($evaluation && $evaluation['evaluated'])
            <div class="grid grid-cols-2 lg:grid-cols-4 divide-y sm:divide-y-0 lg:divide-x divide-gray-200 border-b border-gray-200">
                <div class="px-6 py-4">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">MAE</p>
                    <p class="text-2xl font-semibold text-gray-900 mt-1">{{ $fmt($evaluation['mae']) }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">{{ $selected->unit->shortLabel() }} rata-rata meleset</p>
                </div>
                <div class="px-6 py-4">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">RMSE</p>
                    <p class="text-2xl font-semibold text-gray-900 mt-1">{{ $fmt($evaluation['rmse']) }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">penalti error besar</p>
                </div>
                <div class="px-6 py-4">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">MAPE</p>
                    <p class="text-2xl font-semibold text-gray-900 mt-1">
                        {{ $evaluation['mape'] === null ? '—' : $fmt($evaluation['mape']) . '%' }}
                    </p>
                    <p class="text-xs text-gray-400 mt-0.5">error relatif</p>
                </div>
                <div class="px-6 py-4">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Data Latih / Uji</p>
                    <p class="text-2xl font-semibold text-gray-900 mt-1">
                        {{ $evaluation['train_size'] }} <span class="text-gray-400">/</span> {{ $evaluation['test_size'] }}
                    </p>
                    <p class="text-xs text-gray-400 mt-0.5">hari</p>
                </div>
            </div>

            <div class="px-6 py-3 bg-gray-50 border-b border-gray-200 text-xs text-gray-600">
                Persamaan model: <span class="font-mono text-gray-900">y = {{ $fmt($evaluation['intercept']) }} + {{ $fmt($evaluation['slope']) }}·x</span>
                &middot; R² latih {{ number_format((float) $evaluation['r2_train'] * 100, 0) }}%
                &middot; MAE baseline (rata-rata) {{ $fmt($evaluation['baseline_mae']) }}
                @if ($evaluation['baseline_mae'] > 0)
                    <span class="{{ $evaluation['mae'] < $evaluation['baseline_mae'] ? 'text-green-600' : 'text-amber-600' }} font-medium">
                        ({{ $evaluation['mae'] < $evaluation['baseline_mae'] ? 'model lebih baik' : 'baseline lebih baik' }})
                    </span>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aktual</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Prediksi</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Error</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">APE</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        @foreach ($evaluation['rows'] as $row)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-3 text-sm text-gray-900">
                                    {{ \Illuminate\Support\Carbon::parse($row['date'])->format('d M Y') }}
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-600 text-right">{{ $fmt($row['actual']) }}</td>
                                <td class="px-6 py-3 text-sm text-gray-600 text-right">{{ $fmt($row['predicted']) }}</td>
                                <td class="px-6 py-3 text-sm text-right {{ $row['error'] < 0 ? 'text-red-600' : 'text-gray-600' }}">
                                    {{ $row['error'] > 0 ? '+' : '' }}{{ $fmt($row['error']) }}
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-600 text-right">
                                    {{ $row['ape'] === null ? '—' : $fmt($row['ape']) . '%' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    @if ($overallEvaluation['evaluated'])
                        <tfoot class="bg-gray-50 border-t border-gray-200">
                            <tr>
                                <td class="px-6 py-3 text-xs text-gray-500" colspan="5">
                                    Seluruh sistem ({{ $overallEvaluation['materials'] }} bahan baku, {{ $overallEvaluation['test_size'] }} titik uji):
                                    MAE {{ $fmt($overallEvaluation['mae']) }} &middot;
                                    RMSE {{ $fmt($overallEvaluation['rmse']) }} &middot;
                                    MAPE {{ $overallEvaluation['mape'] === null ? '—' : $fmt($overallEvaluation['mape']) . '%' }}
                                </td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        @else
            <div class="px-6 py-12 text-center text-sm text-gray-500">
                Riwayat pemakaian belum cukup untuk evaluasi (minimal {{ $testDays + 2 }} hari data).
            </div>
        @endif
    </div>

    {{-- Recommendation table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Rekomendasi Restock</h2>
            <p class="text-sm text-gray-500 mt-0.5">
                Rekomendasi = perkiraan permintaan ({{ $horizonDays }} hari) + stok pengaman − stok saat ini.
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bahan Baku</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Stok Saat Ini</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Pemakaian/Hari</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Perkiraan {{ $horizonDays }} Hari</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Rekomendasi Restock</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Keyakinan (R²)</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse ($recommendations as $row)
                        @php $d = $row['data']; $unit = $row['material']->unit->shortLabel(); @endphp
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $row['material']->name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600 text-right">{{ $fmt($d['current_stock']) }} {{ $unit }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600 text-right">{{ $fmt($d['predicted_daily_usage']) }} {{ $unit }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600 text-right">{{ $fmt($d['projected_demand']) }} {{ $unit }}</td>
                            <td class="px-6 py-4 text-right">
                                @if ($d['needs_restock'])
                                    <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-amber-100 text-amber-700">
                                        + {{ $fmt($d['recommended_restock']) }} {{ $unit }}
                                    </span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-700">Cukup</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-24 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-indigo-500" style="width: {{ (float) $d['confidence'] * 100 }}%"></div>
                                    </div>
                                    <span class="text-xs text-gray-500">{{ number_format((float) $d['confidence'] * 100, 0) }}%</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-6 py-12 text-center text-sm text-gray-500" colspan="6">
                                Belum ada bahan baku untuk diprediksi.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @script
        <script>
            const buildConfig = (data) => ({
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: 'Pemakaian Aktual',
                            data: data.actual,
                            borderColor: '#4f46e5',
                            backgroundColor: 'rgba(79, 70, 229, 0.08)',
                            fill: true,
                            tension: 0.3,
                            spanGaps: false,
                            pointRadius: 2,
                        },
                        {
                            label: 'Perkiraan',
                            data: data.forecast,
                            borderColor: '#f59e0b',
                            borderDash: [6, 4],
                            fill: false,
                            tension: 0.3,
                            spanGaps: true,
                            pointRadius: 2,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        y: { beginAtZero: true, ticks: { color: '#6b7280' }, grid: { color: '#f3f4f6' } },
                        x: { ticks: { color: '#6b7280', maxTicksLimit: 12 }, grid: { display: false } },
                    },
                    plugins: {
                        legend: { labels: { color: '#374151', usePointStyle: true } },
                    },
                },
            });

            let chart = new Chart(document.getElementById('usageChart'), buildConfig(@js($chart)));

            $wire.on('chart-updated', (event) => {
                const data = event.series ?? (Array.isArray(event) ? event[0].series : event);
                chart.data.labels = data.labels;
                chart.data.datasets[0].data = data.actual;
                chart.data.datasets[1].data = data.forecast;
                chart.update();
            });
        </script>
    @endscript
</div>
