<?php

namespace App\Http\Requests\Dashboard;

class ProjectOverviewRequest extends DashboardFiltersRequest
{
    public function rules(): array
    {
        return [
            ...$this->commonRules(),
            'range' => ['sometimes', 'in:week,year'],
        ];
    }
}
