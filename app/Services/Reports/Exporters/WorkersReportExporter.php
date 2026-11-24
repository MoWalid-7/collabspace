<?php

namespace App\Services\Reports\Exporters;

use App\Models\Report;
use App\Repositories\Contracts\ReportsRepositoryInterface;
use Carbon\CarbonImmutable;

class WorkersReportExporter implements ReportExporterInterface
{
    public function __construct(private ReportsRepositoryInterface $reports) {}

    public function export(Report $report): string
    {
        $params = $report->params;
        $from = CarbonImmutable::parse((string) $params['start_date'])->startOfDay();
        $to = CarbonImmutable::parse((string) $params['end_date'])->endOfDay();

        $rows = $this->reports->getWorkersForExport($report->workspace_id, $from, $to);

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        fputcsv($handle, ['ID', 'Name', 'Email', 'Completed Tasks', 'Hours Logged', 'Activities Count']);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['id'],
                $row['name'],
                $row['email'],
                $row['tasks_completed'],
                $row['hours_logged'],
                $row['activities_count'],
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv !== false ? $csv : '';
    }
}
