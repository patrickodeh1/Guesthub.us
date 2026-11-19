<x-admin-layout :title="'Connect channels · '.$property->name">
    <div class="mx-auto max-w-7xl space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-950">Connect OTA channels</h1>
                <p class="mt-1 text-sm text-slate-500">{{ $property->name }} · Channex channel management</p>
            </div>
            <a href="{{ route('admin.properties.availability.index', $property) }}" class="btn-secondary">Back to availability</a>
        </div>
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <iframe
                src="{{ $iframeUrl }}"
                title="Channex channel management"
                class="h-[78vh] min-h-[620px] w-full border-0"
                referrerpolicy="no-referrer"
                allow="clipboard-read; clipboard-write"
            ></iframe>
        </div>
        <p class="text-xs text-slate-500">This single-use Channex sign-in token is temporary and is not saved by GuestHub. Only channels for this property are available in the embedded view.</p>
    </div>
</x-admin-layout>
