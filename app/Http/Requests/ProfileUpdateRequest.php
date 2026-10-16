<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->normalizePhoneInput();

        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower($this->input('email')),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'phone_number' => ['nullable', 'string', 'regex:/^\+[1-9]\d{7,14}$/'],
            'profile_photo' => ['nullable', 'image'],
            'remove_profile_photo' => ['sometimes', 'boolean'],
        ];
    }

    protected function normalizePhoneInput(): void
    {
        $raw = trim((string) $this->input('phone_number', ''));
        if ($raw === '') {
            $this->merge(['phone_number' => null]);
            return;
        }

        $code = (string) $this->input('phone_country_code', '+1');
        if (str_starts_with($raw, '+')) {
            $normalized = '+'.preg_replace('/\D+/', '', $raw);
        } else {
            $digits = preg_replace('/\D+/', '', $raw);
            if ($code !== '+1') {
                $digits = ltrim($digits, '0');
            }
            $normalized = \App\Support\PhoneFormatter::normalizeForStorage($digits, $code);
        }

        $this->merge(['phone_number' => $normalized]);
    }

    public function messages(): array
    {
        return ['phone_number.regex' => 'Enter a valid phone number with its country code, for example +234 801 234 5678.'];
    }
}
