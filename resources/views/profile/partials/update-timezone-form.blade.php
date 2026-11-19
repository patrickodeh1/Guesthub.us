<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Your time zone</h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Alert texts show the property's time and, when it differs, the same moment in your time zone.</p>
    </header>

    <form method="post" action="{{ route('profile.timezone.update') }}" class="mt-6 space-y-4" id="tz-form">
        @csrf
        @method('patch')

        <select name="timezone" id="tz-select" class="input w-full">
            <option value="">Same as the property (no conversion)</option>
            @foreach(timezone_identifiers_list() as $tz)
                <option value="{{ $tz }}" @selected(old('timezone', auth()->user()->timezone) === $tz)>{{ $tz }}</option>
            @endforeach
        </select>
        @error('timezone')
            <p class="text-sm text-red-600">{{ $message }}</p>
        @enderror

        <p id="tz-detect" class="hidden text-sm text-amber-700">
            <span></span>
            <button type="button" id="tz-use" class="underline font-medium"></button>
        </p>

        <div class="flex items-center gap-4">
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">Save</button>
            @if (session('status') === 'timezone-updated')
                <p class="text-sm text-gray-600 dark:text-gray-400">Saved.</p>
            @endif
        </div>
    </form>

    <script>
        (function () {
            var sel = document.getElementById('tz-select');
            var box = document.getElementById('tz-detect');
            var btn = document.getElementById('tz-use');
            var detected;
            try { detected = Intl.DateTimeFormat().resolvedOptions().timeZone; } catch (e) {}
            if (!detected || !sel || !box || !btn) return;
            var known = Array.prototype.some.call(sel.options, function (o) { return o.value === detected; });
            if (!known || sel.value === detected) return;
            box.querySelector('span').textContent = sel.value
                ? 'Your device is set to ' + detected + ', which differs from your saved time zone. '
                : 'Your device looks like it is in ' + detected + '. ';
            btn.textContent = 'Use ' + detected;
            box.classList.remove('hidden');
            btn.addEventListener('click', function () {
                sel.value = detected;
                document.getElementById('tz-form').submit();
            });
        })();
    </script>
</section>
