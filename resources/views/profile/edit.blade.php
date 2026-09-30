<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="eyebrow">Account controls</p>
            <h1 class="page-title">{{ __('My Account') }}</h1>
            <p class="page-subtitle">Manage your profile, password, preferences, and notification settings.</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg dark:bg-gray-800">
            <div class="max-w-xl">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg dark:bg-gray-800">
            <div class="max-w-xl">
                @include('profile.partials.update-preferences-form')
            </div>
        </div>

        <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg dark:bg-gray-800">
            <div class="max-w-xl">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg dark:bg-gray-800">
            <div class="max-w-xl">
                @include('profile.partials.delete-user-form')
            </div>
        </div>

        <section class="p-4 sm:p-8 bg-white shadow sm:rounded-lg dark:bg-gray-800">
            <div class="max-w-xl">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Account security</h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Two-factor authentication is not enabled for this account. Use a unique password and contact an administrator if you need help securing your access.</p>
            </div>
        </section>
    </div>
</x-app-layout>
