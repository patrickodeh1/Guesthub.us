<main class="flex flex-col items-center flex-1 px-4 pt-6 sm:justify-center">
    <div>
        <a href="{{ url('/') }}" class="flex items-center gap-3">
            <x-application-logo class="h-12 w-12 object-contain" />
            <span class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ \App\Support\Branding::siteName() }}</span>
        </a>
    </div>

    <div class="w-full px-6 py-4 my-6 overflow-hidden bg-white rounded-md shadow-md sm:max-w-md dark:bg-dark-eval-1">
        {{ $slot }}
    </div>
</main>
