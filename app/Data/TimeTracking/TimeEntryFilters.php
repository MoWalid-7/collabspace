<?php

namespace App\Data\TimeTracking;

use Carbon\CarbonImmutable;

readonly class TimeEntryFilters
{
    public function __construct(
        public ?int $userId = null,
        public ?int $projectId = null,
        public ?int $taskId = null,
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
        public int $page = 1,
        public int $perPage = 15,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data, ?int $forcedUserId = null): self
    {
        return new self(
            userId: $forcedUserId ?? (isset($data['user_id']) ? (int) $data['user_id'] : null),
            projectId: isset($data['project_id']) ? (int) $data['project_id'] : null,
            taskId: isset($data['task_id']) ? (int) $data['task_id'] : null,
            from: isset($data['from']) ? CarbonImmutable::parse((string) $data['from'])->startOfDay() : null,
            to: isset($data['to']) ? CarbonImmutable::parse((string) $data['to'])->endOfDay() : null,
            page: isset($data['page']) ? max(1, (int) $data['page']) : 1,
            perPage: isset($data['per_page']) ? min(100, max(1, (int) $data['per_page'])) : 15,
        );
    }
}
