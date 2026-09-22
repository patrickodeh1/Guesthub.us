<x-guest-layout>
    <x-auth-card>
        <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Your account is currently using a temporary password. For your security, you must create a new password before continuing to the application.') }}
        </div>

        <x-auth-validation-errors class="mb-4" :errors="$errors" />

        <form method="POST" action="{{ route('password.force-change.store') }}" id="password-change-form">
            @csrf

            <div class="grid gap-6">
                <!-- Password -->
                <div class="space-y-2">
                    <x-form.label for="password" :value="__('New Password')" />
                    
                    <x-form.input-with-icon-wrapper>
                        <x-slot name="icon">
                            <x-heroicon-o-lock-closed aria-hidden="true" class="w-5 h-5" />
                        </x-slot>

                        <x-form.input
                            withicon
                            id="password"
                            class="block w-full"
                            type="password"
                            name="password"
                            required
                            autocomplete="new-password"
                            placeholder="{{ __('New Password') }}"
                        />
                    </x-form.input-with-icon-wrapper>
                </div>

                <!-- Confirm Password -->
                <div class="space-y-2">
                    <x-form.label for="password_confirmation" :value="__('Confirm New Password')" />
                    
                    <x-form.input-with-icon-wrapper>
                        <x-slot name="icon">
                            <x-heroicon-o-lock-closed aria-hidden="true" class="w-5 h-5" />
                        </x-slot>

                        <x-form.input
                            withicon
                            id="password_confirmation"
                            class="block w-full"
                            type="password"
                            name="password_confirmation"
                            required
                            autocomplete="new-password"
                            placeholder="{{ __('Confirm New Password') }}"
                        />
                    </x-form.input-with-icon-wrapper>
                </div>

                <div class="flex items-center justify-end mt-4">
                    <button type="submit" form="logout-form" class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800 mr-4">
                        {{ __('Log Out') }}
                    </button>

                    <x-button class="justify-center gap-2">
                        <span>{{ __('Update Password') }}</span>
                    </x-button>
                </div>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}" id="logout-form" class="hidden">
            @csrf
        </form>
    </x-auth-card>
</x-guest-layout>
