@if(!empty($hostSite))
    <header class="mx-auto mb-6 flex w-full max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6" style="border-bottom:1px solid #e5e7eb">
        <a href="{{ route('public.host.show', $hostSite->slug) }}" class="flex min-w-0 items-center gap-3 no-underline">
            @if($hostSite->logo_path)
                <img src="{{ url('/img/'.$hostSite->logo_path) }}" alt="{{ $hostSite->display_name }}" class="max-h-12 max-w-36 object-contain">
            @endif
            <span class="truncate text-lg font-semibold" style="color:{{ $hostSite->brand_color }}">{{ $hostSite->display_name }}</span>
        </a>
        @if($hostSite->support_email)
            <a href="mailto:{{ $hostSite->support_email }}" class="text-sm text-slate-600 hover:underline">Contact host</a>
        @endif
    </header>
@endif
