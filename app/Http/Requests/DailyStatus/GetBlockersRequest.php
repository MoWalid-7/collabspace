<?php

declare(strict_types=1);

namespace App\Http\Requests\DailyStatus;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class GetBlockersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function fromDate(): CarbonImmutable
    {
        return $this->filled('from')
            ? CarbonImmutable::parse($this->input('from'))->startOfDay()
            : CarbonImmutable::now()->subDays(30)->startOfDay();
    }

    public function toDate(): CarbonImmutable
    {
        return $this->filled('to')
            ? CarbonImmutable::parse($this->input('to'))->endOfDay()
            : CarbonImmutable::now()->endOfDay();
    }
}
