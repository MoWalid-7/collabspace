<?php

namespace App\Services\Reports\Exporters;

use App\Models\Report;
use App\Repositories\Contracts\ReportsRepositoryInterface;

class ProjectsReportExporter implements ReportExporterInterface
{
    public function __construct(private ReportsRepositoryInterface $reports) {}

    public function export(Report $report): string
    {
        $params = $report->params;
        $projectId = isset($params['project_id']) ? (int) $params['project_id'] : null;

        $rows = $this->reports->getProjectsForExport($report->workspace_id, $projectId);

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        fputcsv($handle, ['ID', 'Name', 'Status', 'Priority', 'Deadline', 'Total Tasks', 'Completed Tasks']);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['id'],
                $row['name'],
                $row['status'],
                $row['priority'],
                $row['deadline'],
                $row['tasks_total'],
                $row['tasks_completed'],
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv !== false ? $csv : '';
    }
}
