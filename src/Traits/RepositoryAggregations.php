<?php

namespace Litepie\Repository\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

trait RepositoryAggregations
{
    /**
     * Perform aggregation operations.
     */
    public function aggregate(array $operations): array
    {
        $results = [];
        
        foreach ($operations as $operation => $field) {
            switch (strtolower($operation)) {
                case 'count':
                    $results[$operation] = $this->query->count($field);
                    break;
                case 'sum':
                    $results[$operation] = $this->query->sum($field);
                    break;
                case 'avg':
                case 'average':
                    $results[$operation] = $this->query->avg($field);
                    break;
                case 'min':
                    $results[$operation] = $this->query->min($field);
                    break;
                case 'max':
                    $results[$operation] = $this->query->max($field);
                    break;
                case 'median':
                    $results[$operation] = $this->calculateMedian($field);
                    break;
                case 'mode':
                    $results[$operation] = $this->calculateMode($field);
                    break;
                case 'variance':
                    $results[$operation] = $this->calculateVariance($field);
                    break;
                case 'stddev':
                    $results[$operation] = $this->calculateStandardDeviation($field);
                    break;
            }
        }
        
        return $results;
    }

    /**
     * Group by field with aggregations.
     */
    public function groupBy(string $field, array $aggregations = ['count' => '*']): Collection
    {
        $query = $this->query->groupBy($field)->select($field);
        
        foreach ($aggregations as $operation => $aggField) {
            switch (strtolower($operation)) {
                case 'count':
                    $query->selectRaw("COUNT({$aggField}) as {$operation}");
                    break;
                case 'sum':
                    $query->selectRaw("SUM({$aggField}) as {$operation}");
                    break;
                case 'avg':
                    $query->selectRaw("AVG({$aggField}) as {$operation}");
                    break;
                case 'min':
                    $query->selectRaw("MIN({$aggField}) as {$operation}");
                    break;
                case 'max':
                    $query->selectRaw("MAX({$aggField}) as {$operation}");
                    break;
            }
        }
        
        return $query->get();
    }

    /**
     * Create pivot table from data.
     */
    public function pivot(string $rows, string $cols, string $values, string $aggregation = 'sum'): array
    {
        $data = $this->query
            ->select($rows, $cols, $values)
            ->get()
            ->groupBy($rows);
        
        $pivot = [];
        $columns = $data->flatten(1)->pluck($cols)->unique()->sort()->values();
        
        foreach ($data as $rowKey => $rowData) {
            $pivot[$rowKey] = [];
            
            foreach ($columns as $col) {
                $filtered = $rowData->where($cols, $col);
                
                switch ($aggregation) {
                    case 'sum':
                        $pivot[$rowKey][$col] = $filtered->sum($values);
                        break;
                    case 'avg':
                        $pivot[$rowKey][$col] = $filtered->avg($values);
                        break;
                    case 'count':
                        $pivot[$rowKey][$col] = $filtered->count();
                        break;
                    case 'max':
                        $pivot[$rowKey][$col] = $filtered->max($values);
                        break;
                    case 'min':
                        $pivot[$rowKey][$col] = $filtered->min($values);
                        break;
                    default:
                        $pivot[$rowKey][$col] = $filtered->sum($values);
                }
            }
        }
        
        return [
            'data' => $pivot,
            'columns' => $columns->toArray(),
            'rows' => array_keys($pivot)
        ];
    }

    /**
     * Get trend data over time.
     */
    public function trend(string $dateField, string $interval = 'day', string $valueField = null, string $aggregation = 'count'): Collection
    {
        $format = $this->getDateFormat($interval);
        $selectRaw = "DATE_FORMAT({$dateField}, '{$format}') as period";
        
        $query = $this->query
            ->selectRaw($selectRaw)
            ->groupBy('period')
            ->orderBy('period');
        
        if ($valueField && $aggregation !== 'count') {
            $query->selectRaw(strtoupper($aggregation) . "({$valueField}) as value");
        } else {
            $query->selectRaw("COUNT(*) as value");
        }
        
        return $query->get();
    }

    /**
     * Calculate percentiles.
     */
    public function percentiles(string $field, array $percentiles = [25, 50, 75, 90, 95, 99]): array
    {
        $values = $this->query->pluck($field)->sort()->values();
        $count = $values->count();
        
        if ($count === 0) {
            return [];
        }
        
        $results = [];
        
        foreach ($percentiles as $percentile) {
            $index = ($percentile / 100) * ($count - 1);
            $lower = floor($index);
            $upper = ceil($index);
            
            if ($lower === $upper) {
                $results["p{$percentile}"] = $values[$lower];
            } else {
                $lowerValue = $values[$lower];
                $upperValue = $values[$upper];
                $fraction = $index - $lower;
                $results["p{$percentile}"] = $lowerValue + ($fraction * ($upperValue - $lowerValue));
            }
        }
        
        return $results;
    }

    /**
     * Calculate moving average.
     */
    public function movingAverage(string $field, int $window = 7, string $orderBy = 'created_at'): Collection
    {
        $data = $this->query->orderBy($orderBy)->get();
        $results = collect();
        
        for ($i = $window - 1; $i < $data->count(); $i++) {
            $windowData = $data->slice($i - $window + 1, $window);
            $average = $windowData->avg($field);
            
            $results->push([
                'period' => $data[$i]->$orderBy,
                'value' => $data[$i]->$field,
                'moving_average' => round($average, 2),
                'window_size' => $window
            ]);
        }
        
        return $results;
    }

    /**
     * Get histogram data.
     */
    public function histogram(string $field, int $bins = 10): array
    {
        $min = $this->query->min($field);
        $max = $this->query->max($field);
        $range = $max - $min;
        $binWidth = $range / $bins;
        
        $histogram = [];
        
        for ($i = 0; $i < $bins; $i++) {
            $start = $min + ($i * $binWidth);
            $end = $start + $binWidth;
            
            $count = $this->query
                ->where($field, '>=', $start)
                ->where($field, $i === $bins - 1 ? '<=' : '<', $end)
                ->count();
            
            $histogram[] = [
                'bin' => $i + 1,
                'range' => "{$start}-{$end}",
                'start' => $start,
                'end' => $end,
                'count' => $count,
                'percentage' => 0 // Will be calculated below
            ];
        }
        
        $total = array_sum(array_column($histogram, 'count'));
        
        foreach ($histogram as &$bin) {
            $bin['percentage'] = $total > 0 ? round(($bin['count'] / $total) * 100, 2) : 0;
        }
        
        return $histogram;
    }

    /**
     * Calculate correlation between two fields.
     */
    public function correlation(string $field1, string $field2): float
    {
        $data = $this->query->select($field1, $field2)->get();
        
        if ($data->count() < 2) {
            return 0;
        }
        
        $x = $data->pluck($field1);
        $y = $data->pluck($field2);
        
        $meanX = $x->avg();
        $meanY = $y->avg();
        
        $numerator = 0;
        $sumXSquared = 0;
        $sumYSquared = 0;
        
        for ($i = 0; $i < $data->count(); $i++) {
            $xDiff = $x[$i] - $meanX;
            $yDiff = $y[$i] - $meanY;
            
            $numerator += $xDiff * $yDiff;
            $sumXSquared += $xDiff * $xDiff;
            $sumYSquared += $yDiff * $yDiff;
        }
        
        $denominator = sqrt($sumXSquared * $sumYSquared);
        
        return $denominator > 0 ? $numerator / $denominator : 0;
    }

    /**
     * Calculate median value.
     */
    protected function calculateMedian(string $field): float
    {
        $values = $this->query->pluck($field)->sort()->values();
        $count = $values->count();
        
        if ($count === 0) {
            return 0;
        }
        
        if ($count % 2 === 0) {
            $mid1 = $values[($count / 2) - 1];
            $mid2 = $values[$count / 2];
            return ($mid1 + $mid2) / 2;
        }
        
        return $values[floor($count / 2)];
    }

    /**
     * Calculate mode value.
     */
    protected function calculateMode(string $field)
    {
        $values = $this->query->pluck($field);
        $frequencies = $values->countBy();
        
        return $frequencies->sortDesc()->keys()->first();
    }

    /**
     * Calculate variance.
     */
    protected function calculateVariance(string $field): float
    {
        $values = $this->query->pluck($field);
        $mean = $values->avg();
        
        $squaredDifferences = $values->map(function ($value) use ($mean) {
            return pow($value - $mean, 2);
        });
        
        return $squaredDifferences->avg();
    }

    /**
     * Calculate standard deviation.
     */
    protected function calculateStandardDeviation(string $field): float
    {
        return sqrt($this->calculateVariance($field));
    }

    /**
     * Get date format for different intervals.
     */
    protected function getDateFormat(string $interval): string
    {
        switch ($interval) {
            case 'minute':
                return '%Y-%m-%d %H:%i';
            case 'hour':
                return '%Y-%m-%d %H';
            case 'day':
                return '%Y-%m-%d';
            case 'week':
                return '%Y-%u';
            case 'month':
                return '%Y-%m';
            case 'quarter':
                return '%Y-Q%q';
            case 'year':
                return '%Y';
            default:
                return '%Y-%m-%d';
        }
    }

    /**
     * Get statistical summary.
     */
    public function statisticalSummary(string $field): array
    {
        return [
            'count' => $this->query->count(),
            'sum' => $this->query->sum($field),
            'avg' => $this->query->avg($field),
            'min' => $this->query->min($field),
            'max' => $this->query->max($field),
            'median' => $this->calculateMedian($field),
            'mode' => $this->calculateMode($field),
            'variance' => $this->calculateVariance($field),
            'std_dev' => $this->calculateStandardDeviation($field),
            'percentiles' => $this->percentiles($field)
        ];
    }
}
