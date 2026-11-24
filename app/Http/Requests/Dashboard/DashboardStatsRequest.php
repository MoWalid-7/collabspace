<?php

namespace App\Http\Requests\Dashboard;

class DashboardStatsRequest extends DashboardFiltersRequest
{
    public function rules(): array
    {
        return $this->commonRules();
    }
}
