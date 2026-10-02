@props([
    'title',
    'open' => 'drawerOpen',
    'onClose' => 'drawerOpen = false',
    'id' => 'app-drawer',
])

<div x-cloak x-show="{{ $open }}" x-transition.opacity class="fixed inset-0 z-[80]">
    <button type="button" class="absolute inset-0 h-full w-full bg-slate-950/50" aria-label="Close drawer" @click="{{ $onClose }}"></button>
    <section
        class="absolute inset-y-0 right-0 flex h-full w-full max-w-xl flex-col overflow-y-auto bg-white shadow-2xl sm:rounded-l-2xl"
        role="dialog"
        aria-modal="true"
        aria-labelledby="{{ $id }}-title"
        tabindex="-1"
        @keydown.escape.window="{{ $onClose }}"
    >
        <header class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <h2 id="{{ $id }}-title" class="text-lg font-semibold text-slate-950">{{ $title }}</h2>
            <button type="button" class="rounded-md p-2 text-slate-500 hover:bg-slate-100" aria-label="Close drawer" @click="{{ $onClose }}">
                <x-icon name="x" class="h-5 w-5" />
            </button>
        </header>
        <div class="flex-1 p-5">{{ $slot }}</div>
    </section>
</div>
