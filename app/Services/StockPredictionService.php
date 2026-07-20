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
     * Fit regression on a material's recent daily usage.
     *
     * @return array{slope: float, intercept: float, r2: float, points: array<int, array{0: float, 1: float}>, lastIndex: int}
     */
    public function fit(RawMaterial $material): array
    {
        $usages = $material->usages()
            ->orderBy('usage_date')
            ->get(['usage_date', 'quantity'])
            ->reverse()
            ->take($this->trainingWindow)
            ->reverse()
            ->values();

        $points = [];
        foreach ($usages as $index => $usage) {
            $points[] = [(float) $index, (float) $usage->quantity];
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
     * History + forecast series for charting.
     *
     * @return array{labels: array<int, string>, actual: array<int, float|null>, forecast: array<int, float|null>}
     */
    public function historyAndForecastSeries(RawMaterial $material, int $horizonDays = 7): array
    {
        $usages = $material->usages()
            ->orderBy('usage_date')
            ->get(['usage_date', 'quantity'])
            ->reverse()
            ->take($this->trainingWindow)
            ->reverse()
            ->values();

        $fit = $this->fit($material);

        $labels = [];
        $actual = [];
        $forecast = [];

        foreach ($usages as $usage) {
            $labels[] = Carbon::parse($usage->usage_date)->format('d M');
            $actual[] = (float) $usage->quantity;
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
