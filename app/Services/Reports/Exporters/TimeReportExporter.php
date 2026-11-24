<?php

namespace App\Services\Reports\Exporters;

use App\Models\Report;
use App\Repositories\Contracts\ReportsRepositoryInterface;
use Carbon\CarbonImmutable;

class TimeReportExporter implements ReportExporterInterface
{
    public function __construct(private ReportsRepositoryInterface $reports) {}

    public function export(Report $report): string
    {
        $params = $report->params;
        $from = CarbonImmutable::parse((string) $params['start_date'])->startOfDay();
        $to = CarbonImmutable::parse((string) $params['end_date'])->endOfDay();
        $projectId = isset($params['project_id']) ? (int) $params['project_id'] : null;

        $rows = $this->reports->getTimeEntriesForExport($report->workspace_id, $from, $to, $projectId);

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        fputcsv($handle, ['ID', 'User', 'Project', 'Task', 'Started At', 'Ended At', 'Duration Seconds', 'Note']);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['id'],
                $row['user'],
                $row['project'],
                $row['task'],
                $row['started_at'],
                $row['ended_at'],
                $row['duration_seconds'],
                $row['note'],
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv !== false ? $csv : '';
    }
}
