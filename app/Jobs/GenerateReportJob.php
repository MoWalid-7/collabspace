<?php

namespace App\Jobs;

use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Models\Report;
use App\Repositories\Contracts\ReportsRepositoryInterface;
use App\Services\Reports\Exporters\ProjectsReportExporter;
use App\Services\Reports\Exporters\ReportExporterInterface;
use App\Services\Reports\Exporters\TasksReportExporter;
use App\Services\Reports\Exporters\TimeReportExporter;
use App\Services\Reports\Exporters\WorkersReportExporter;
use App\Services\Storage\Contracts\FileStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Report $report) {}

    public function handle(
        ReportsRepositoryInterface $reports,
        FileStorage $storage,
    ): void {
        $reports->updateReport($this->report, [
            'status' => ReportStatus::Processing->value,
        ]);

        try {
            $exporter = $this->resolveExporter($this->report->type, $reports);
            $csv = $exporter->export($this->report);

            $tempFile = tempnam(sys_get_temp_dir(), 'report_');
            if ($tempFile === false) {
                throw new \RuntimeException('Failed to create temporary report file.');
            }

            file_put_contents($tempFile, $csv);

            $uploadedFile = new UploadedFile(
                $tempFile,
                "report_{$this->report->id}.csv",
                'text/csv',
                null,
                true,
            );

            $directory = "workspaces/{$this->report->workspace_id}/reports";
            $stored = $storage->put($uploadedFile, $directory);

            @unlink($tempFile);

            $reports->updateReport($this->report, [
                'status' => ReportStatus::Ready->value,
                'file_path' => $stored->path,
                'generated_at' => now(),
            ]);
        } catch (Throwable $e) {
            $reports->updateReport($this->report, [
                'status' => ReportStatus::Failed->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveExporter(ReportType $type, ReportsRepositoryInterface $reports): ReportExporterInterface
    {
        return match ($type) {
            ReportType::Tasks => new TasksReportExporter($reports),
            ReportType::Projects => new ProjectsReportExporter($reports),
            ReportType::Time => new TimeReportExporter($reports),
            ReportType::Workers => new WorkersReportExporter($reports),
        };
    }
}
