<div class="rounded-xl border border-slate-200 p-4">
    <p class="text-sm font-bold">Vehicle Information <span class="text-red-600">*</span></p>
    <p class="mt-1 text-sm text-slate-600">Please take a photo of the <strong>entire back of your vehicle</strong>, with the license plate, make and model all visible. Photos that don't show the whole back of the vehicle will be rejected.</p>

    <div id="vehicle-capture">
    <div id="plate-scan-fields" class="my-3 space-y-3 {{ $booking->license_plate ? 'plate-revealed' : '' }}">
<style>#plate-scan-fields:not(.plate-revealed) > :not(#plate-scan-status){display:none}</style>
        <div id="plate-scan-status" class="hidden rounded-lg border p-3 text-sm"></div>
        <div>
            <label for="license_plate" class="guest-stay-tile-label block mb-1">Plate number</label>
            <input type="text" name="license_plate" id="license_plate" value="{{ old('license_plate', $booking->license_plate) }}" maxlength="12" autocapitalize="characters" autocomplete="off" class="w-full rounded-lg border border-slate-300 px-3 py-2 uppercase tracking-wider">
        </div>
        <div>
            <label for="license_plate_state" class="guest-stay-tile-label block mb-1">State</label>
            <select name="license_plate_state" id="license_plate_state" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                <option value="">Select state</option>
                @foreach(['AL','AK','AZ','AR','CA','CO','CT','DE','DC','FL','GA','HI','ID','IL','IN','IA','KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT','VA','WA','WV','WI','WY'] as $st)
                    <option value="{{ $st }}" @selected(old('license_plate_state', $booking->license_plate_state) === $st)>{{ $st }}</option>
                @endforeach
                <option value="OT" @selected(old('license_plate_state', $booking->license_plate_state) === 'OT')>Other / not US</option>
            </select>
        </div>
        <div>
            <label for="vehicle_make_model" class="guest-stay-tile-label block mb-1">Vehicle make and model</label>
            <input type="text" name="vehicle_make_model" id="vehicle_make_model" value="{{ old('vehicle_make_model', $booking->vehicle_make_model) }}" maxlength="80" autocomplete="off" placeholder="e.g. Toyota Camry" class="w-full rounded-lg border border-slate-300 px-3 py-2">
        </div>
    </div>

    <button type="button" id="plate-cam-open" data-scan-url="{{ route('guest.scan-plate', [$booking->booking_id, $booking->token]) }}" class="mb-3 w-full rounded-xl bg-slate-900 px-4 py-3 font-semibold text-white">Take photo of the back of your vehicle</button>
    <input type="file" name="license_plate_photo" id="license_plate_photo" accept="image/*" class="hidden">
    <script src="{{ asset('js/plate-capture.js') }}?v={{ @filemtime(public_path('js/plate-capture.js')) }}"></script>

    </div>

    <input type="checkbox" id="vehicle-later" class="hidden">
    <button type="button" id="vehicle-later-btn" class="mt-3 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700">Don't have this yet? Do this later</button>
    <div id="vehicle-later-note" class="mt-3 hidden rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
        No problem, we'll ask for your plate again later.
        <button type="button" id="vehicle-later-undo" class="ml-1 font-semibold text-blue-600 underline">Add it now</button>
    </div>
    <script>
    (function () {
        var cb = document.getElementById("vehicle-later");
        var cap = document.getElementById("vehicle-capture");
        var btn = document.getElementById("vehicle-later-btn");
        var note = document.getElementById("vehicle-later-note");
        function setLater(on) {
            cb.checked = on;
            cap.style.display = on ? "none" : "";
            btn.style.display = on ? "none" : "";
            note.classList.toggle("hidden", !on);
        }
        btn.addEventListener("click", function () { setLater(true); });
        document.getElementById("vehicle-later-undo").addEventListener("click", function () { setLater(false); });
    })();
    </script>
</div>
