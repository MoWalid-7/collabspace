<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class DashboardMetrics
{
    /**
     * Calculates ((current - previous) / previous) * 100, rounded to an integer.
     * A zero previous period has no defined percentage change and returns null.
     */
    public function changePercent(int $current, int $previous): ?int
    {
        if ($previous === 0) {
            return null;
        }

        return (int) round(($current - $previous) / $previous * 100);
    }

    /**
     * @param  iterable<CarbonInterface|string>  $timestamps
     * @return list<array{date: string, label: string, count: int}>
     */
    public function bucketTimestamps(
        iterable $timestamps,
        CarbonImmutable $start,
        CarbonImmutable $end,
        string $range,
    ): array {
        $bucketDates = [];
        $cursor = $range === 'year' ? $start->startOfYear() : $start->startOfDay();
        $lastBucket = $range === 'year' ? $end->startOfMonth() : $end->startOfDay();

        while ($cursor <= $lastBucket) {
            $date = $range === 'year' ? $cursor->format('Y-m') : $cursor->toDateString();
            $bucketDates[$date] = [
                'date' => $date,
                'label' => $range === 'year' ? $cursor->format('M') : $cursor->format('D'),
                'count' => 0,
            ];
            $cursor = $range === 'year' ? $cursor->addMonth() : $cursor->addDay();
        }

        foreach ($timestamps as $timestamp) {
            $date = $timestamp instanceof CarbonInterface
                ? CarbonImmutable::instance($timestamp)
                : CarbonImmutable::parse($timestamp);
            $key = $range === 'year' ? $date->format('Y-m') : $date->toDateString();

            if (isset($bucketDates[$key])) {
                $bucketDates[$key]['count']++;
            }
        }

        return array_values($bucketDates);
    }
}
