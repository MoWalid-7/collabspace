<?php

namespace App\Http\Requests\Dashboard;

class DashboardItemsRequest extends DashboardFiltersRequest
{
    public function rules(): array
    {
        return [
            ...$this->commonRules(),
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
