<?php

namespace App\Services\Reports\Exporters;

use App\Models\Report;

interface ReportExporterInterface
{
    /**
     * Generates CSV string contents.
     */
    public function export(Report $report): string;
}
