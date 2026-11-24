<?php

namespace App\Services\Reports\Exporters;

use App\Models\Report;
use App\Repositories\Contracts\ReportsRepositoryInterface;
use Carbon\CarbonImmutable;

class TasksReportExporter implements ReportExporterInterface
{
    public function __construct(private ReportsRepositoryInterface $reports) {}

    public function export(Report $report): string
    {
        $params = $report->params;
        $from = CarbonImmutable::parse((string) $params['start_date'])->startOfDay();
        $to = CarbonImmutable::parse((string) $params['end_date'])->endOfDay();
        $projectId = isset($params['project_id']) ? (int) $params['project_id'] : null;

        $rows = $this->reports->getTasksForExport($report->workspace_id, $from, $to, $projectId);

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        fputcsv($handle, ['ID', 'Project', 'Title', 'Status', 'Priority', 'Due Date', 'Created At']);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['id'],
                $row['project'],
                $row['title'],
                $row['status'],
                $row['priority'],
                $row['due_date'],
                $row['created_at'],
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv !== false ? $csv : '';
    }
}
