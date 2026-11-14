<?php

namespace App\Http\Requests\Finance;

use App\Enums\FeeFrequency;
use App\Http\Requests\Concerns\ValidatesSchoolRecords;
use App\Services\Finance\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FeeItemRequest extends FormRequest
{
    use ValidatesSchoolRecords;

    public function authorize(): bool
    {
        return $this->user()->can($this->route('fee_item') ? 'fees.edit' : 'fees.create');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', $this->existsInSchool('academic_years')],
            'school_class_id' => ['nullable', 'integer', $this->existsInSchool('school_classes')],
            'name' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'frequency' => ['required', Rule::enum(FeeFrequency::class)],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'academic_year_id' => $this->integer('academic_year_id'),
            'school_class_id' => $this->filled('school_class_id') ? $this->integer('school_class_id') : null,
            'name' => $this->string('name')->toString(),
            'amount' => Money::toCents($this->string('amount')->toString()),
            'frequency' => $this->string('frequency')->toString(),
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
