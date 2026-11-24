<?php

namespace App\Http\Requests\Dashboard;

use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Support\CurrentWorkspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

abstract class DashboardFiltersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $mine = $this->query('mine');

        if (is_string($mine) && in_array(Str::lower($mine), ['true', 'false'], true)) {
            $this->merge(['mine' => Str::lower($mine) === 'true']);
        }
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $projectId = $this->input('project_id');

                if ($projectId === null || $validator->errors()->has('project_id')) {
                    return;
                }

                if (! app(ProjectRepositoryInterface::class)->isVisibleToId(
                    (int) $projectId,
                    (int) $this->user()?->getAuthIdentifier(),
                    app(CurrentWorkspace::class)->requireId(),
                )) {
                    $validator->errors()->add('project_id', 'The selected project is not visible.');
                }
            },
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function commonRules(): array
    {
        return [
            'project_id' => ['sometimes', 'integer', 'min:1'],
            'mine' => ['sometimes', 'boolean'],
        ];
    }
}
