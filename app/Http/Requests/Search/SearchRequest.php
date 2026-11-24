<?php

namespace App\Http\Requests\Search;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $query = $this->query('q');

        if (is_string($query)) {
            $this->merge(['q' => Str::trim($query)]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'types' => ['sometimes', 'array', 'max:6'],
            'types.*' => ['string', 'distinct', Rule::in(['projects', 'tasks', 'files', 'teams', 'members', 'messages'])],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ];
    }
}
