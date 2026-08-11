<?php

namespace App\Services;

use App\Models\RawMaterial;
use Illuminate\Support\Carbon;

class StockPredictionService
{
    /**
     * Number of most-recent days of usage history to train on.
     */
    public int $trainingWindow = 60;

    /**
     * Number of most-recent days held out as test data during evaluation.
     */
    public int $testWindow = 14;

    /**
     * Least-squares linear regression over (x, y) points.
     *
     * @param  array<int, array{0: float, 1: float}>  $points  list of [x, y]
     * @return array{slope: float, intercept: float, r2: float}
     */
    public function linearRegression(array $points): array
    {
        $n = count($points);
        if ($n === 0) {
            return ['slope' => 0.0, 'intercept' => 0.0, 'r2' => 0.0];
        }
        if ($n === 1) {
            return ['slope' => 0.0, 'intercept' => (float) $points[0][1], 'r2' => 0.0];
        }

        $sumX = $sumY = $sumXY = $sumXX = 0.0;
        foreach ($points as [$x, $y]) {
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumXX += $x * $x;
        }

        $denominator = ($n * $sumXX) - ($sumX * $sumX);
        if ($denominator == 0.0) {
            return ['slope' => 0.0, 'intercept' => $sumY / $n, 'r2' => 0.0];
        }

        $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
        $intercept = ($sumY - ($slope * $sumX)) / $n;

        // Coefficient of determination (R²) as a confidence proxy.
        $meanY = $sumY / $n;
        $ssTot = $ssRes = 0.0;
        foreach ($points as [$x, $y]) {
            $predicted = $intercept + $slope * $x;
            $ssTot += ($y - $meanY) ** 2;
            $ssRes += ($y - $predicted) ** 2;
        }
        $r2 = $ssTot == 0.0 ? 0.0 : max(0.0, 1 - ($ssRes / $ssTot));

        return [
            'slope' => $slope,
            'intercept' => $intercept,
            'r2' => $r2,
        ];
    }

    /**
     * The most recent daily usage rows for a material, oldest first.
     *
     * @return array<int, array{date: string, quantity: float}>
     */
    public function usageSeries(RawMaterial $material, ?int $limit = null): array
    {
        $limit ??= $this->trainingWindow;

        return $material->usages()
            ->orderByDesc('usage_date')
            ->limit($limit)
            ->get(['usage_date', 'quantity'])
            ->reverse()
            ->values()
            ->map(fn ($usage) => [
                'date' => Carbon::parse($usage->usage_date)->toDateString(),
                'quantity' => (float) $usage->quantity,
            ])
            ->all();
    }

    /**
     * Fit regression on a material's recent daily usage.
     *
     * @return array{slope: float, intercept: float, r2: float, points: array<int, array{0: float, 1: float}>, lastIndex: int}
     */
    public function fit(RawMaterial $material): array
    {
        $points = [];
        foreach ($this->usageSeries($material) as $index => $row) {
            $points[] = [(float) $index, $row['quantity']];
        }

        $result = $this->linearRegression($points);
        $result['points'] = $points;
        $result['lastIndex'] = count($points) - 1;

        return $result;
    }

    /**
     * Predicted average daily usage for the next period (never negative).
     */
    public function predictedDailyUsage(RawMaterial $material): float
    {
        $fit = $this->fit($material);
        if ($fit['lastIndex'] < 0) {
            return 0.0;
        }

        // Predict at the next day index.
        $prediction = $fit['intercept'] + $fit['slope'] * ($fit['lastIndex'] + 1);

        return max(0.0, round($prediction, 2));
    }

    /**
     * Projected total demand over the coming horizon in days.
     */
    public function forecast(RawMaterial $material, int $horizonDays = 7): float
    {
        $fit = $this->fit($material);
        if ($fit['lastIndex'] < 0) {
            return 0.0;
        }

        $total = 0.0;
        for ($d = 1; $d <= $horizonDays; $d++) {
            $predicted = $fit['intercept'] + $fit['slope'] * ($fit['lastIndex'] + $d);
            $total += max(0.0, $predicted);
        }

        return round($total, 2);
    }

    /**
     * Restock recommendation for a material over a lead time.
     *
     * @return array{
     *     material_id: int,
     *     current_stock: float,
     *     predicted_daily_usage: float,
     *     projected_demand: float,
     *     safety_stock: float,
     *     recommended_restock: float,
     *     confidence: float,
     *     needs_restock: bool
     * }
     */
    public function recommendRestock(RawMaterial $material, int $leadTimeDays = 7): array
    {
        $fit = $this->fit($material);
        $currentStock = $material->currentStock();
        $projectedDemand = $this->forecast($material, $leadTimeDays);
        $safetyStock = (float) $material->minimum_stock;

        $recommended = max(0.0, round($projectedDemand + $safetyStock - $currentStock, 2));

        return [
            'material_id' => $material->id,
            'current_stock' => round($currentStock, 2),
            'predicted_daily_usage' => $this->predictedDailyUsage($material),
            'projected_demand' => $projectedDemand,
            'safety_stock' => round($safetyStock, 2),
            'recommended_restock' => $recommended,
            'confidence' => round($fit['r2'], 2),
            'needs_restock' => $recommended > 0,
        ];
    }

    /**
     * Hold-out evaluation: train on the older days, score on the newest ones.
     *
     * The last $testDays rows are never shown to the regression, so MAE/RMSE/MAPE
     * measure error on data the model has not seen.
     *
     * @return array{
     *     evaluated: bool,
     *     train_size: int,
     *     test_size: int,
     *     slope: float,
     *     intercept: float,
     *     r2_train: float,
     *     mae: float|null,
     *     rmse: float|null,
     *     mape: float|null,
     *     baseline_mae: float|null,
     *     rows: array<int, array{date: string, actual: float, predicted: float, error: float, ape: float|null}>
     * }
     */
    public function evaluate(RawMaterial $material, ?int $testDays = null): array
    {
        $testDays = max(1, $testDays ?? $this->testWindow);
        $series = $this->usageSeries($material, $this->trainingWindow + $testDays);
        $n = count($series);
        $trainSize = $n - $testDays;

        $empty = [
            'evaluated' => false,
            'train_size' => max(0, $trainSize),
            'test_size' => 0,
            'slope' => 0.0,
            'intercept' => 0.0,
            'r2_train' => 0.0,
            'mae' => null,
            'rmse' => null,
            'mape' => null,
            'baseline_mae' => null,
            'rows' => [],
        ];

        // Need at least two training points to define a line.
        if ($trainSize < 2) {
            return $empty;
        }

        $trainPoints = [];
        $trainSum = 0.0;
        for ($i = 0; $i < $trainSize; $i++) {
            $trainPoints[] = [(float) $i, $series[$i]['quantity']];
            $trainSum += $series[$i]['quantity'];
        }

        $fit = $this->linearRegression($trainPoints);
        $baseline = $trainSum / $trainSize; // naive forecast: mean of the training window

        $rows = [];
        $absError = $sqError = $baselineAbsError = 0.0;
        $apeSum = 0.0;
        $apeCount = 0;

        for ($i = $trainSize; $i < $n; $i++) {
            $actual = $series[$i]['quantity'];
            $predicted = max(0.0, $fit['intercept'] + $fit['slope'] * $i);
            $error = $predicted - $actual;

            $absError += abs($error);
            $sqError += $error ** 2;
            $baselineAbsError += abs($baseline - $actual);

            // MAPE is undefined when the actual value is zero; skip those days.
            $ape = $actual == 0.0 ? null : abs($error / $actual) * 100;
            if ($ape !== null) {
                $apeSum += $ape;
                $apeCount++;
            }

            $rows[] = [
                'date' => $series[$i]['date'],
                'actual' => round($actual, 2),
                'predicted' => round($predicted, 2),
                'error' => round($error, 2),
                'ape' => $ape === null ? null : round($ape, 2),
            ];
        }

        $testSize = count($rows);

        return [
            'evaluated' => true,
            'train_size' => $trainSize,
            'test_size' => $testSize,
            'slope' => round($fit['slope'], 4),
            'intercept' => round($fit['intercept'], 4),
            'r2_train' => round($fit['r2'], 4),
            'mae' => round($absError / $testSize, 3),
            'rmse' => round(sqrt($sqError / $testSize), 3),
            'mape' => $apeCount === 0 ? null : round($apeSum / $apeCount, 2),
            'baseline_mae' => round($baselineAbsError / $testSize, 3),
            'rows' => $rows,
        ];
    }

    /**
     * Weighted average of the per-material evaluation, for a system-wide score.
     *
     * @param  iterable<RawMaterial>  $materials
     * @return array{evaluated: bool, materials: int, test_size: int, mae: float|null, rmse: float|null, mape: float|null, baseline_mae: float|null}
     */
    public function evaluateAll(iterable $materials, ?int $testDays = null): array
    {
        $count = $totalTest = 0;
        $absError = $sqError = $baselineAbsError = 0.0;
        $apeSum = 0.0;
        $apeCount = 0;

        foreach ($materials as $material) {
            $result = $this->evaluate($material, $testDays);
            if (! $result['evaluated']) {
                continue;
            }

            $count++;
            $totalTest += $result['test_size'];
            $absError += $result['mae'] * $result['test_size'];
            $sqError += ($result['rmse'] ** 2) * $result['test_size'];
            $baselineAbsError += $result['baseline_mae'] * $result['test_size'];

            foreach ($result['rows'] as $row) {
                if ($row['ape'] !== null) {
                    $apeSum += $row['ape'];
                    $apeCount++;
                }
            }
        }

        if ($totalTest === 0) {
            return [
                'evaluated' => false,
                'materials' => 0,
                'test_size' => 0,
                'mae' => null,
                'rmse' => null,
                'mape' => null,
                'baseline_mae' => null,
            ];
        }

        return [
            'evaluated' => true,
            'materials' => $count,
            'test_size' => $totalTest,
            'mae' => round($absError / $totalTest, 3),
            'rmse' => round(sqrt($sqError / $totalTest), 3),
            'mape' => $apeCount === 0 ? null : round($apeSum / $apeCount, 2),
            'baseline_mae' => round($baselineAbsError / $totalTest, 3),
        ];
    }

    /**
     * History + forecast series for charting.
     *
     * @return array{labels: array<int, string>, actual: array<int, float|null>, forecast: array<int, float|null>}
     */
    public function historyAndForecastSeries(RawMaterial $material, int $horizonDays = 7): array
    {
        $series = $this->usageSeries($material);
        $fit = $this->fit($material);

        $labels = [];
        $actual = [];
        $forecast = [];

        foreach ($series as $row) {
            $labels[] = Carbon::parse($row['date'])->format('d M');
            $actual[] = $row['quantity'];
            $forecast[] = null;
        }

        // Connect the forecast line to the last actual point.
        if (! empty($actual)) {
            $forecast[count($forecast) - 1] = end($actual);
        }

        $lastIndex = $fit['lastIndex'];
        for ($d = 1; $d <= $horizonDays; $d++) {
            $labels[] = Carbon::today()->addDays($d)->format('d M');
            $actual[] = null;
            $predicted = $lastIndex < 0
                ? 0.0
                : max(0.0, round($fit['intercept'] + $fit['slope'] * ($lastIndex + $d), 2));
            $forecast[] = $predicted;
        }

        return [
            'labels' => $labels,
            'actual' => $actual,
            'forecast' => $forecast,
        ];
    }
}
