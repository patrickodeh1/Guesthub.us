<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->route('user');
        $authUser = $this->user();

        // Users can update their own profile
        if ($authUser->id === $user->id) {
            return true;
        }

        // Admin can update anyone
        if ($authUser->hasRole('admin')) {
            return true;
        }

        // Company can update their owned users (owners and housekeepers)
        if ($authUser->hasRole('company') && !$authUser->hasRole('admin')) {
            return $user->owner_id === $authUser->id;
        }

        // Owner can only update their assigned housekeepers
        if ($authUser->hasRole('owner') && !$authUser->hasRole('admin') && !$authUser->hasRole('company')) {
            return $user->owner_id === $authUser->id;
        }

        return false;
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
            $user = $this->route('user');
            $email = strtolower(trim((string) $this->input('email')));
            if (empty($email)) {
                return;
            }

            $existingUser = User::where('email', $email)
                ->where('id', '<>', $user?->id)
                ->first();

            if ($existingUser && ! $existingUser->is_active) {
                $validator->errors()->add(
                    'email',
                    'This cleaner was previously added and later deactivated. Reactivation is required instead of creating a new account.'
                );
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
        $user = $this->route('user');
        $authUser = $this->user();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'profile_photo' => ['nullable', 'image'],
            'remove_profile_photo' => ['sometimes', 'boolean'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'owner_ids' => ['nullable', 'array'],
            'owner_ids.*' => ['exists:users,id'],
            'property_ids' => ['nullable', 'array'],
            'property_ids.*' => ['exists:properties,id'],
            'preferences' => ['nullable', 'array'],
            'preferences.required_instruction_views' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];

        // Admin can change any role
        if ($authUser->hasRole('admin') && $authUser->id !== $user->id) {
            $rules['role'] = ['nullable', Rule::in(['admin', 'owner', 'company', 'housekeeper'])];
        }

        // Company can assign owner or housekeeper roles
        if ($authUser->hasRole('company') && !$authUser->hasRole('admin') && $authUser->id !== $user->id) {
            if ($user->owner_id === $authUser->id) {
                $rules['role'] = ['nullable', Rule::in(['owner', 'housekeeper'])];
            }
        }

        // Owner can assign housekeeper role to their owned users
        if ($authUser->hasRole('owner') && !$authUser->hasRole('admin') && !$authUser->hasRole('company') && $authUser->id !== $user->id) {
            if ($user->owner_id === $authUser->id) {
                $rules['role'] = ['nullable', Rule::in(['housekeeper'])];
            }
        }

        // Password is optional on update
        if ($this->filled('password')) {
            $rules['password'] = ['required', 'string', 'min:8', 'confirmed'];
        }

        return $rules;
    }
}
