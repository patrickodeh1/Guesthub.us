@if(!empty($hostSite) && $hostSite->show_powered_by)
    <footer class="mx-auto mt-12 w-full max-w-7xl border-t border-slate-200 px-4 py-6 text-center text-xs text-slate-500 sm:px-6">
        Powered by <a href="{{ route('public.stays.index') }}" class="font-semibold underline">GuestHub</a>
    </footer>
@endif
