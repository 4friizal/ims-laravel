# BAB IV — IMPLEMENTASI MODUL PREDIKSI KEBUTUHAN BAHAN BAKU

Dokumen ini merangkum implementasi modul prediksi pada Sistem Manajemen Inventori (IMS).
Seluruh angka pada bab ini diambil dari eksekusi nyata sistem terhadap basis data
`ims_restaurant` (7 bahan baku × 90 hari = 630 baris pemakaian harian), bukan angka ilustratif.

---

## 4.1 Metode yang Digunakan

Modul prediksi menggunakan **Regresi Linear Sederhana** dengan estimasi parameter
**Ordinary Least Squares (OLS)**. Metode ini termasuk *supervised learning* pada kategori
regresi: model mempelajari pola dari data historis berlabel (pemakaian harian yang sudah
terjadi), lalu menggunakan pola tersebut untuk memprediksi nilai yang belum terjadi.

Alasan pemilihan metode:

| Pertimbangan | Penjelasan |
|---|---|
| Ukuran data | Riwayat pemakaian per bahan baku hanya puluhan titik. Model kompleks (ARIMA, LSTM) berisiko *overfitting* pada data sekecil ini. |
| Interpretabilitas | Parameter `slope` langsung bermakna "kenaikan pemakaian per hari", mudah dipertanggungjawabkan ke pemilik usaha. |
| Biaya komputasi | Perhitungan bersifat *closed-form* O(n), dapat dijalankan langsung saat halaman dimuat tanpa infrastruktur tambahan. |
| Kemandirian sistem | Tidak bergantung pada layanan AI eksternal, sehingga sistem tetap berjalan tanpa koneksi internet dan tanpa biaya API. |

Model dirumuskan sebagai:

```
ŷ = a + b·x
```

dengan `ŷ` = prediksi pemakaian harian, `x` = indeks hari, `a` = intercept, `b` = slope.

Parameter dihitung dengan rumus OLS:

```
      n·Σ(xy) − Σx·Σy
b = ─────────────────────
       n·Σ(x²) − (Σx)²

      Σy − b·Σx
a = ─────────────
          n
```

---

## 4.2 Variabel yang Digunakan dalam Prediksi

### 4.2.1 Variabel Model

| Simbol | Nama | Peran | Sumber Data | Tipe |
|---|---|---|---|---|
| `x` | Indeks hari | Variabel bebas (*independent / feature*) | Urutan kronologis baris `material_usages` (0, 1, 2, …) | Integer |
| `y` | Kuantitas pemakaian harian | Variabel terikat (*dependent / target / label*) | Kolom `material_usages.quantity` | Decimal(12,2) |

### 4.2.2 Parameter Hasil Pelatihan

| Simbol | Nama | Makna |
|---|---|---|
| `b` (slope) | Koefisien kemiringan | Perubahan rata-rata pemakaian per hari. Positif = tren naik. |
| `a` (intercept) | Konstanta | Estimasi pemakaian pada hari ke-0 jendela pelatihan. |
| `R²` | Koefisien determinasi | Proporsi variasi data yang dapat dijelaskan model (0–1). Ditampilkan sebagai "Keyakinan". |

### 4.2.3 Hiperparameter Sistem

| Parameter | Nilai | Keterangan |
|---|---|---|
| `trainingWindow` | 60 hari | Jumlah hari terakhir yang dipakai melatih model produksi. |
| `testWindow` | 14 hari | Jumlah hari terakhir yang disisihkan sebagai data uji saat evaluasi. |
| `horizonDays` | 7 hari (1–30) | Jangka waktu prediksi ke depan, dapat diatur pengguna. |

### 4.2.4 Variabel Non-Model pada Rekomendasi

Variabel berikut tidak ikut dilatih, tetapi digunakan pada tahap pengambilan keputusan:

| Variabel | Sumber | Peran |
|---|---|---|
| `current_stock` | Sisa `stock_batches.remaining_quantity` (FIFO) | Stok fisik saat ini |
| `safety_stock` | Kolom `raw_materials.minimum_stock` | Stok pengaman |

---

## 4.3 Arsitektur Implementasi

| Berkas | Peran |
|---|---|
| `app/Services/StockPredictionService.php` | Inti model: pelatihan, prediksi, evaluasi, rekomendasi |
| `app/Livewire/Inventory/StockPrediction.php` | Komponen halaman: menerima input pengguna, memanggil service |
| `resources/views/livewire/inventory/stock-prediction.blade.php` | Antarmuka: grafik, kartu metrik, tabel uji, tabel rekomendasi |
| `app/Models/MaterialUsage.php` | Model data pemakaian harian (sumber dataset) |
| `tests/Unit/StockPredictionServiceTest.php` | Pengujian rumus regresi |
| `tests/Feature/StockPredictionServiceTest.php` | Pengujian prediksi, evaluasi, dan rekomendasi |

Metode utama pada `StockPredictionService`:

| Metode | Fungsi |
|---|---|
| `usageSeries()` | Mengambil deret pemakaian harian terurut dari basis data |
| `linearRegression()` | Menghitung `a`, `b`, dan `R²` dengan OLS |
| `fit()` | Membentuk dataset dan melatih model untuk satu bahan baku |
| `predictedDailyUsage()` | Prediksi pemakaian satu hari ke depan |
| `forecast()` | Total prediksi permintaan selama horizon |
| `evaluate()` | Pembagian data latih/uji + perhitungan MAE, RMSE, MAPE |
| `evaluateAll()` | Agregasi metrik seluruh bahan baku |
| `recommendRestock()` | Menghasilkan rekomendasi jumlah pembelian |

---

## 4.4 Alur Proses Prediksi

```
┌──────────────────────────────────────────────────────────────────┐
│ 1. PENGUMPULAN DATA                                              │
│    Transaksi stok keluar (pemakaian dapur / penjualan menu)      │
│    dicatat InventoryService ke tabel material_usages             │
│    (unique: raw_material_id + usage_date → 1 baris per hari)     │
└───────────────────────────┬──────────────────────────────────────┘
                            ▼
┌──────────────────────────────────────────────────────────────────┐
│ 2. PEMBENTUKAN DATASET — usageSeries()                           │
│    Ambil 60 hari terakhir, urut kronologis                       │
│    Ubah menjadi pasangan (x, y): x = indeks 0..59, y = quantity   │
└───────────────────────────┬──────────────────────────────────────┘
                            ▼
┌──────────────────────────────────────────────────────────────────┐
│ 3. PELATIHAN MODEL — fit() → linearRegression()                  │
│    Hitung Σx, Σy, Σxy, Σx²  →  b (slope), a (intercept), R²      │
└───────────────────────────┬──────────────────────────────────────┘
                            ▼
┌──────────────────────────────────────────────────────────────────┐
│ 4. PREDIKSI — forecast()                                         │
│    Untuk d = 1..H:  ŷ = max(0, a + b·(lastIndex + d))            │
│    Total permintaan = Σ ŷ                                        │
└───────────────────────────┬──────────────────────────────────────┘
                            ▼
┌──────────────────────────────────────────────────────────────────┐
│ 5. REKOMENDASI — recommendRestock()                              │
│    Restock = max(0, Prediksi + Safety Stock − Stok Saat Ini)     │
└───────────────────────────┬──────────────────────────────────────┘
                            ▼
┌──────────────────────────────────────────────────────────────────┐
│ 6. EVALUASI — evaluate()                                         │
│    Latih ulang pada 60 hari lama → uji pada 14 hari terakhir     │
│    Hitung MAE, RMSE, MAPE terhadap data yang tidak pernah dilihat│
└──────────────────────────────────────────────────────────────────┘
```

---

## 4.5 Contoh Perhitungan Pelatihan Model

Contoh diambil dari bahan baku **Cabai Merah** (satuan kg), jendela pelatihan 60 hari
(29 Mei 2026 – 27 Juli 2026).

### 4.5.1 Cuplikan Data Latih

| x | Tanggal | y (kg) |     | x | Tanggal | y (kg) |
|---|---|---|---|---|---|---|
| 0 | 2026-05-29 | 5.63 | … | 50 | 2026-07-18 | 6.47 |
| 1 | 2026-05-30 | 6.55 | … | 51 | 2026-07-19 | 6.78 |
| 2 | 2026-05-31 | 5.49 | … | 52 | 2026-07-20 | 5.67 |
| 3 | 2026-06-01 | 5.42 | … | 53 | 2026-07-21 | 5.00 |
| 4 | 2026-06-02 | 4.37 | … | 54 | 2026-07-22 | 5.70 |
| 5 | 2026-06-03 | 5.41 | … | 55 | 2026-07-23 | 6.18 |
| 6 | 2026-06-04 | 4.85 | … | 56 | 2026-07-24 | 6.88 |
| 7 | 2026-06-05 | 7.06 | … | 57 | 2026-07-25 | 6.47 |
| 8 | 2026-06-06 | 5.86 | … | 58 | 2026-07-26 | 7.36 |
| 9 | 2026-06-07 | 6.50 | … | 59 | 2026-07-27 | 5.21 |

### 4.5.2 Agregat Statistik

```
n     = 60
Σx    = 1.770,00
Σy    = 351,89
Σxy   = 10.594,35
Σx²   = 70.210,00
ȳ     = 351,89 / 60 = 5,8648
```

### 4.5.3 Perhitungan Parameter

**Penyebut:**
```
D = n·Σx² − (Σx)²
  = 60 × 70.210,00 − 1.770,00²
  = 4.212.600 − 3.132.900
  = 1.079.700
```

**Slope:**
```
b = (n·Σxy − Σx·Σy) / D
  = (60 × 10.594,35 − 1.770,00 × 351,89) / 1.079.700
  = (635.661,00 − 622.845,30) / 1.079.700
  = 12.815,70 / 1.079.700
  = 0,011870
```

**Intercept:**
```
a = (Σy − b·Σx) / n
  = (351,89 − 0,011870 × 1.770,00) / 60
  = (351,89 − 21,0099) / 60
  = 330,8801 / 60
  = 5,514668   (service menghitung 5,514678 dengan slope presisi penuh)
```

**Persamaan model hasil pelatihan:**
```
ŷ = 5,5147 + 0,01187·x
```

Interpretasi: pemakaian Cabai Merah diperkirakan naik rata-rata **0,0119 kg per hari**,
atau sekitar **0,08 kg per minggu**.

**Koefisien determinasi:** `R² = 0,0651`

---

## 4.6 Pembagian Data Latih dan Data Uji

Untuk menghindari evaluasi yang bias, sistem menerapkan **hold-out validation** dengan
pembagian berbasis waktu (*time-based split*) — bukan pembagian acak, karena data deret
waktu tidak boleh diacak (model tidak boleh "melihat masa depan").

| Kelompok | Jumlah | Periode (Cabai Merah) | Penggunaan |
|---|---|---|---|
| Data latih | 60 hari | 29 Mei – 27 Juli 2026 | Menghitung `a` dan `b` |
| Data uji | 14 hari | 28 Juli – 10 Agustus 2026 | Mengukur MAE, RMSE, MAPE |
| **Total** | **74 hari** | | |

Data uji **tidak pernah** dimasukkan ke perhitungan Σx, Σy, Σxy, Σx². Prediksi pada data uji
merupakan ekstrapolasi murni pada `x = 60` hingga `x = 73`.

---

## 4.7 Metrik Evaluasi

### 4.7.1 Rumus

**MAE (Mean Absolute Error)** — rata-rata besar kesalahan, satuan sama dengan data:
```
MAE = (1/n) · Σ |ŷᵢ − yᵢ|
```

**RMSE (Root Mean Squared Error)** — memberi penalti lebih besar pada kesalahan ekstrem:
```
RMSE = √[ (1/n) · Σ (ŷᵢ − yᵢ)² ]
```

**MAPE (Mean Absolute Percentage Error)** — kesalahan relatif, tidak bersatuan:
```
MAPE = (100%/n) · Σ |(ŷᵢ − yᵢ) / yᵢ|
```

Catatan implementasi: MAPE tidak terdefinisi saat `yᵢ = 0`, sehingga hari dengan pemakaian
nol dilewati dari perhitungan MAPE (tetap dihitung pada MAE dan RMSE).

### 4.7.2 Contoh Perhitungan pada Data Uji

Prediksi menggunakan `ŷ = 5,5147 + 0,01187·x` pada `x = 60..73`:

| x | Tanggal | Aktual (yᵢ) | Prediksi (ŷᵢ) | Error (ŷᵢ−yᵢ) | \|Error\| | Error² | APE (%) |
|---|---|---|---|---|---|---|---|
| 60 | 2026-07-28 | 5,11 | 6,23 | +1,12 | 1,12 | 1,2544 | 21,86 |
| 61 | 2026-07-29 | 5,59 | 6,24 | +0,65 | 0,65 | 0,4225 | 11,61 |
| 62 | 2026-07-30 | 5,31 | 6,25 | +0,94 | 0,94 | 0,8836 | 17,71 |
| 63 | 2026-07-31 | 7,03 | 6,26 | −0,77 | 0,77 | 0,5929 | 10,92 |
| 64 | 2026-08-01 | 8,02 | 6,27 | −1,75 | 1,75 | 3,0625 | 21,77 |
| 65 | 2026-08-02 | 7,22 | 6,29 | −0,93 | 0,93 | 0,8649 | 12,93 |
| 66 | 2026-08-03 | 6,04 | 6,30 | +0,26 | 0,26 | 0,0676 | 4,27 |
| 67 | 2026-08-04 | 5,09 | 6,31 | +1,22 | 1,22 | 1,4884 | 23,97 |
| 68 | 2026-08-05 | 6,67 | 6,32 | −0,35 | 0,35 | 0,1225 | 5,22 |
| 69 | 2026-08-06 | 6,09 | 6,33 | +0,24 | 0,24 | 0,0576 | 4,00 |
| 70 | 2026-08-07 | 7,94 | 6,35 | −1,59 | 1,59 | 2,5281 | 20,08 |
| 71 | 2026-08-08 | 7,96 | 6,36 | −1,60 | 1,60 | 2,5600 | 20,13 |
| 72 | 2026-08-09 | 8,75 | 6,37 | −2,38 | 2,38 | 5,6644 | 27,21 |
| 73 | 2026-08-10 | 6,29 | 6,38 | +0,09 | 0,09 | 0,0081 | 1,45 |
| | **Jumlah** | | | | **13,89** | **19,58** | **203,13** |

**Perhitungan akhir:**
```
MAE  = 13,89 / 14                = 0,9921 kg
RMSE = √(19,58 / 14) = √1,3986   = 1,1826 kg
MAPE = 203,13% / 14              = 14,51%
```

**Interpretasi:** prediksi pemakaian Cabai Merah rata-rata meleset **0,99 kg per hari**
(≈14,5% dari nilai aktual). RMSE (1,18) lebih besar dari MAE (0,99), menandakan terdapat
beberapa hari dengan kesalahan besar — yaitu akhir pekan, ketika pemakaian melonjak.

---

## 4.8 Hasil Evaluasi Seluruh Bahan Baku

Konfigurasi: 60 hari data latih, 14 hari data uji per bahan baku (total 98 titik uji).

| No | Bahan Baku | Satuan | Slope (b) | Intercept (a) | R² Latih | MAE | RMSE | MAPE (%) |
|---|---|---|---|---|---|---|---|---|
| 1 | Ayam | kg | 0,0310 | 8,328 | 0,13 | 1,142 | 1,385 | 10,80 |
| 2 | Bawang Merah | kg | 0,0116 | 3,009 | 0,08 | 0,341 | 0,378 | 9,74 |
| 3 | Bawang Putih | kg | 0,0085 | 2,360 | 0,12 | 0,292 | 0,352 | 10,55 |
| 4 | Cabai Merah | kg | 0,0119 | 5,515 | 0,07 | 0,992 | 1,183 | 14,51 |
| 5 | Daging Sapi | kg | 0,0265 | 7,091 | 0,17 | 0,975 | 1,247 | 10,01 |
| 6 | Kentang | kg | 0,0155 | 3,521 | 0,21 | 0,556 | 0,652 | 12,88 |
| 7 | Santan | L | 0,0186 | 10,100 | 0,13 | 1,257 | 1,729 | 9,53 |
| | **Rata-rata sistem** | | | | | **0,794** | **1,105** | **11,15** |

Seluruh bahan baku memiliki slope positif, konsisten dengan tren kenaikan permintaan pada
data operasional restoran.

### 4.8.1 Perbandingan terhadap Model Dasar (*Baseline*)

Sebagai pembanding digunakan *naive forecast*, yaitu memprediksi setiap hari uji dengan
rata-rata data latih. Model yang baik harus mengungguli baseline ini.

| Bahan Baku | MAE Model | MAE Baseline | Selisih | Keterangan |
|---|---|---|---|---|
| Ayam | 1,142 | 1,567 | −27,1% | Model lebih baik |
| Bawang Merah | 0,341 | 0,310 | +10,0% | Baseline lebih baik |
| Bawang Putih | 0,292 | 0,380 | −23,2% | Model lebih baik |
| Cabai Merah | 0,992 | 1,123 | −11,7% | Model lebih baik |
| Daging Sapi | 0,975 | 1,275 | −23,5% | Model lebih baik |
| Kentang | 0,556 | 0,642 | −13,4% | Model lebih baik |
| Santan | 1,257 | 1,687 | −25,5% | Model lebih baik |
| **Agregat** | **0,794** | **0,998** | **−20,4%** | **Model lebih baik** |

Model regresi linear mengungguli baseline pada **6 dari 7** bahan baku, dengan penurunan
MAE agregat sebesar **20,4%**. Pengecualian terjadi pada Bawang Merah, yang memiliki tren
paling lemah sehingga rata-rata sederhana sudah cukup akurat.

---

## 4.9 Proses Prediksi dan Rekomendasi Restock

### 4.9.1 Rumus Rekomendasi

```
Prediksi Permintaan  = Σ(d=1..H) max(0, a + b·(lastIndex + d))
Rekomendasi Restock  = max(0, Prediksi Permintaan + Safety Stock − Stok Saat Ini)
```

### 4.9.2 Contoh Perhitungan (Cabai Merah, horizon 7 hari)

Model produksi dilatih pada seluruh 60 hari terbaru (termasuk 14 hari yang tadi dijadikan
data uji), menghasilkan `ŷ = 5,5671 + 0,018877·x` dengan `lastIndex = 59`.

| d | x = 59+d | Prediksi ŷ (kg) |
|---|---|---|
| 1 | 60 | 6,6997 |
| 2 | 61 | 6,7186 |
| 3 | 62 | 6,7375 |
| 4 | 63 | 6,7564 |
| 5 | 64 | 6,7753 |
| 6 | 65 | 6,7941 |
| 7 | 66 | 6,8130 |
| | **Total permintaan 7 hari** | **47,29** |

```
Stok saat ini  = 27,58 kg
Safety stock   =  5,00 kg  (minimum_stock)

Rekomendasi = max(0; 47,29 + 5,00 − 27,58)
            = max(0; 24,71)
            = 24,71 kg
```

**Keputusan sistem:** Cabai Merah ditandai **perlu restock sebanyak 24,71 kg**.

### 4.9.3 Hasil Rekomendasi Seluruh Bahan Baku

| Bahan Baku | Stok Saat Ini | Prediksi/Hari | Permintaan 7 Hari | Safety Stock | Rekomendasi | Status |
|---|---|---|---|---|---|---|
| Ayam | 89,43 | 10,76 | 76,05 | 8,00 | 0,00 | Cukup |
| Bawang Merah | 18,93 | 3,72 | 26,21 | 3,00 | 10,28 | Perlu restock |
| Bawang Putih | 54,00 | 2,96 | 20,92 | 3,00 | 0,00 | Cukup |
| Cabai Merah | 27,58 | 6,70 | 47,29 | 5,00 | 24,71 | Perlu restock |
| Daging Sapi | 22,74 | 9,30 | 65,74 | 8,00 | 51,00 | Perlu restock |
| Kentang | 11,00 | 4,65 | 32,92 | 5,00 | 26,92 | Perlu restock |
| Santan | 168,47 | 12,09 | 85,26 | 10,00 | 0,00 | Cukup |

Sistem berhasil membedakan bahan baku yang stoknya masih aman (3 bahan) dari yang perlu
segera dibeli (4 bahan), tanpa aturan ambang batas manual.

---

## 4.10 Pengujian Perangkat Lunak

Modul diuji melalui 13 kasus uji otomatis (PHPUnit), dijalankan dengan `php artisan test`.

| No | Kasus Uji | Tujuan | Hasil |
|---|---|---|---|
| 1 | `linear_regression_fits_a_perfect_line` | Rumus OLS benar pada data y = 2x + 1 | Lulus |
| 2 | `linear_regression_handles_flat_data` | Slope = 0 pada data konstan | Lulus |
| 3 | `linear_regression_handles_empty_and_single_point` | Tidak error pada data kosong / 1 titik | Lulus |
| 4 | `r2_is_lower_for_noisy_data` | R² menurun saat data acak | Lulus |
| 5 | `forecast_projects_growing_trend` | Prediksi mengikuti tren naik | Lulus |
| 6 | `recommend_restock_when_stock_below_projected_demand` | Rekomendasi muncul saat stok kurang | Lulus |
| 7 | `no_restock_when_stock_is_ample` | Tidak merekomendasi saat stok cukup | Lulus |
| 8 | `chart_series_has_history_and_forecast` | Data grafik lengkap | Lulus |
| 9 | `evaluate_splits_train_and_test_windows` | Pembagian 26/14 sesuai konfigurasi | Lulus |
| 10 | `evaluate_reports_near_zero_error_on_a_perfect_trend` | MAE/RMSE/MAPE ≈ 0 pada tren sempurna | Lulus |
| 11 | `evaluate_penalises_a_model_that_misses_the_test_window` | Metrik naik saat model salah prediksi | Lulus |
| 12 | `evaluate_returns_unevaluated_when_history_is_too_short` | Penanganan data tidak cukup | Lulus |
| 13 | `evaluate_all_aggregates_across_materials` | Agregasi lintas bahan baku benar | Lulus |

Hasil eksekusi keseluruhan: **37 tes lulus, 108 asersi**.

---

## 4.11 Antarmuka Modul Prediksi

Halaman **Prediksi AI** (`/inventory/prediction`) menampilkan:

1. **Kartu ringkasan** — jumlah bahan baku, jumlah yang perlu restock, dan input horizon prediksi (1–30 hari).
2. **Grafik garis** — riwayat pemakaian aktual (garis padat) dan perkiraan ke depan (garis putus-putus), dibangun dengan Chart.js.
3. **Kartu evaluasi akurasi** — nilai MAE, RMSE, MAPE, ukuran data latih/uji, persamaan model, R², serta perbandingan terhadap baseline.
4. **Tabel data uji** — rincian per hari: aktual, prediksi, error, dan APE.
5. **Tabel rekomendasi restock** — seluruh bahan baku dengan status dan tingkat keyakinan (R²).

---

## 4.12 Analisis dan Keterbatasan

### 4.12.1 Analisis Hasil

Dengan MAPE agregat **11,15%**, model berada pada kategori *good forecasting* menurut
klasifikasi Lewis (1982): MAPE < 10% = sangat akurat, 10–20% = baik, 20–50% = cukup,
> 50% = tidak akurat. Model juga terbukti mengungguli baseline sebesar 20,4%, yang
menunjukkan bahwa komponen tren yang dipelajari model benar-benar memberi nilai tambah.

### 4.12.2 Keterbatasan

| Keterbatasan | Dampak | Bukti pada Data |
|---|---|---|
| **Nilai R² rendah (0,07–0,21)** | Model hanya menjelaskan sebagian kecil variasi data. Tren linear menangkap arah jangka panjang, tetapi tidak menjelaskan naik-turun harian. | Cabai Merah R² = 0,065 |
| **Tidak memodelkan musiman mingguan** | Kesalahan terbesar terjadi pada akhir pekan saat permintaan melonjak. | Error terbesar Cabai Merah pada 9 Agustus 2026 (Minggu): −2,38 kg |
| **Ekstrapolasi linear jangka panjang** | Semakin jauh horizon, semakin besar kemungkinan penyimpangan. | Prediksi divalidasi hanya sampai 14 hari |
| **Sumbu x berbasis indeks baris, bukan tanggal** | Bila terdapat hari tanpa transaksi, jarak waktu antar titik dianggap seragam sehingga slope sedikit bias. | Tidak berdampak pada dataset ini karena seluruh 90 hari memiliki catatan |
| **Model dihitung ulang setiap permintaan** | Tidak ada penyimpanan model terlatih; beban komputasi berulang. | Dapat diterima pada skala 7 bahan baku |

### 4.12.3 Saran Pengembangan

1. Menambahkan variabel *dummy* hari dalam minggu (Jumat–Minggu) untuk menangkap pola akhir pekan.
2. Mengganti sumbu `x` dengan selisih hari sebenarnya terhadap tanggal awal, dan mengisi hari tanpa pemakaian dengan nilai 0.
3. Menerapkan *rolling-origin cross-validation* agar evaluasi tidak bergantung pada satu potongan data uji.
4. Menyimpan parameter model terlatih ke basis data dan memperbaruinya secara terjadwal.
