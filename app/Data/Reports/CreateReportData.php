<?php

namespace App\Data\Reports;

use App\Enums\ReportType;
use Carbon\CarbonImmutable;

readonly class CreateReportData
{
    public function __construct(
        public ReportType $type,
        public CarbonImmutable $startDate,
        public CarbonImmutable $endDate,
        public ?int $projectId = null,
        public ?string $note = null,
    ) {}
}
