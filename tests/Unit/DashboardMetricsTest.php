<?php

use App\Services\DashboardMetrics;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

afterEach(function (): void {
    Carbon::setTestNow();
});

it('calculates rounded percentage changes and returns null for a zero baseline', function () {
    $metrics = new DashboardMetrics;

    expect($metrics->changePercent(4, 3))->toBe(33)
        ->and($metrics->changePercent(1, 2))->toBe(-50)
        ->and($metrics->changePercent(0, 0))->toBeNull();
});

it('buckets timestamps into zero-filled days and months including boundary dates', function () {
    Carbon::setTestNow('2026-04-15 12:00:00');
    $metrics = new DashboardMetrics;

    $week = $metrics->bucketTimestamps(
        [
            CarbonImmutable::parse('2026-04-09 23:59:59'),
            CarbonImmutable::parse('2026-04-15 00:00:01'),
            CarbonImmutable::parse('2026-04-08 23:59:59'),
        ],
        CarbonImmutable::parse('2026-04-09 00:00:00'),
        CarbonImmutable::parse('2026-04-15 23:59:59'),
        'week',
    );
    $year = $metrics->bucketTimestamps(
        [
            CarbonImmutable::parse('2026-01-01 00:00:00'),
            CarbonImmutable::parse('2026-12-31 23:59:59'),
            CarbonImmutable::parse('2025-12-31 23:59:59'),
        ],
        CarbonImmutable::parse('2026-01-01 00:00:00'),
        CarbonImmutable::parse('2026-12-31 23:59:59'),
        'year',
    );

    expect($week)->toHaveCount(7)
        ->and(array_column($week, 'count'))->toBe([1, 0, 0, 0, 0, 0, 1])
        ->and($week[0]['label'])->toBe('Thu')
        ->and($week[6]['label'])->toBe('Wed')
        ->and($year)->toHaveCount(12)
        ->and($year[0])->toBe(['date' => '2026-01', 'label' => 'Jan', 'count' => 1])
        ->and($year[11])->toBe(['date' => '2026-12', 'label' => 'Dec', 'count' => 1]);
});
