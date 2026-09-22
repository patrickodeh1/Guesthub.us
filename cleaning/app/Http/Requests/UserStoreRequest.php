<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        // Admin, owner, and company can create users
        return $user && ($user->hasRole('admin') || $user->hasRole('owner') || $user->hasRole('company'));
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim((string) $this->input('email'))),
            ]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $email = strtolower(trim((string) $this->input('email')));
            $phone = trim((string) $this->input('phone_number'));

            $query = User::query();

            if ($email && $phone) {
                $query->where(function ($q) use ($email, $phone) {
                    $q->where('email', $email)->orWhere('phone_number', $phone);
                });
            } elseif ($email) {
                $query->where('email', $email);
            } elseif ($phone) {
                $query->where('phone_number', $phone);
            } else {
                return;
            }

            $existingUsers = $query->get();

            foreach ($existingUsers as $existingUser) {
                if (! $existingUser->is_active) {
                    $message = 'This cleaner already exists but has been terminated. Reactivate the existing account instead of creating a new one.';
                    if ($email && $existingUser->email === $email) {
                        $validator->errors()->add('email', $message);
                    }
                    if ($phone && $existingUser->phone_number === $phone) {
                        $validator->errors()->add('phone_number', $message);
                    }
                } else {
                    if ($email && $existingUser->email === $email) {
                        $validator->errors()->add('email', 'The email has already been taken.');
                    }
                    if ($phone && $existingUser->phone_number === $phone) {
                        $validator->errors()->add('phone_number', 'The phone number has already been taken.');
                    }
                }
            }
        });
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $authUser = $this->user();
        $roleRules = ['required', Rule::in(['admin', 'owner', 'company', 'housekeeper'])];

        // Owners can only create housekeepers, Companies can create owners and housekeepers
        if ($authUser->hasRole('owner') && !$authUser->hasRole('admin') && !$authUser->hasRole('company')) {
            $roleRules = ['required', Rule::in(['housekeeper'])];
        } elseif ($authUser->hasRole('company') && !$authUser->hasRole('admin')) {
            $roleRules = ['required', Rule::in(['owner', 'housekeeper'])];
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'profile_photo' => ['nullable', 'image'],
            'role' => $roleRules,
            'owner_id' => ['nullable', 'exists:users,id'],
            'owner_ids' => ['nullable', 'array'],
            'owner_ids.*' => ['exists:users,id'],
            'property_ids' => ['nullable', 'array'],
            'property_ids.*' => ['exists:properties,id'],
        ];
    }
}
