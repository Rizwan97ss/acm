<?php

namespace App\Http\Requests;

use App\Models\ContactInquiry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ContactInquiryRequest extends FormRequest
{
    /**
     * The landing page form is public.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9\s\-()]{6,32}$/'],
            'organization' => ['nullable', 'string', 'max:150'],
            'role' => ['nullable', 'string', 'max:60'],
            'school_size' => ['nullable', Rule::in(array_keys(ContactInquiry::SCHOOL_SIZES))],
            'interest' => ['required', Rule::in(array_keys(ContactInquiry::INTERESTS))],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => __('Enter a phone number using digits, spaces, dashes or brackets.'),
            'message.min' => __('Tell us a little more (at least 10 characters).'),
        ];
    }

    /**
     * Bots fill the hidden "website" field; people never see it.
     */
    public function isSpam(): bool
    {
        return $this->filled('website');
    }
}
