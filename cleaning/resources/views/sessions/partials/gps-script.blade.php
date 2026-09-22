@props([
    'propertyLat',
    'propertyLng',
    'propertyRadius' => 100,
    'overrideEnabled' => false,
])

<script>
    const propertyLat = {{ $propertyLat ?? 'null' }};
    const propertyLng = {{ $propertyLng ?? 'null' }};
    const propertyRadius = {{ $propertyRadius }};
    const overrideEnabled = {{ $overrideEnabled ? 'true' : 'false' }};
    const startForm = document.getElementById('gps-start');
    const startBtn = document.getElementById('start-btn');
    const locationStatus = document.getElementById('location-status');
    let userLat = null;
    let userLng = null;
    let locationCheckAttempts = 0;
    const maxAttempts = 3;

    function getErrorMessage(error) {
        switch(error.code) {
            case error.PERMISSION_DENIED:
                return 'Location permission denied. Please enable location access in your browser settings.';
            case error.POSITION_UNAVAILABLE:
                return 'Location information unavailable. Please check your device location settings.';
            case error.TIMEOUT:
                return 'Location request timed out. Please try again.';
            default:
                return 'Unable to determine your location. You can still start the session.';
        }
    }

    function checkLocation() {
        locationCheckAttempts++;

        if (overrideEnabled) {
            locationStatus.innerHTML = '<span class="text-green-600 dark:text-green-400">✓ GPS Override Active. You may start the session.</span>';
            startBtn.disabled = false;
            return;
        }

        // Check if geolocation is available
        if (!navigator.geolocation) {
            locationStatus.innerHTML = '<span class="text-red-600 dark:text-red-400">⚠ Location services not available in this browser. Please contact an administrator.</span>';
            startBtn.disabled = true;
            return;
        }

        // Check if we're in a secure context (HTTPS or localhost)
        if (!window.isSecureContext && window.location.protocol !== 'http:') {
            locationStatus.innerHTML = '<span class="text-red-600 dark:text-red-400">⚠ Location requires a secure connection (HTTPS). Please contact an administrator.</span>';
            startBtn.disabled = true;
            return;
        }

        // Update status
        if (locationCheckAttempts === 1) {
            locationStatus.textContent = 'Requesting location access...';
        } else {
            locationStatus.textContent = `Retrying location (${locationCheckAttempts}/${maxAttempts})...`;
        }

        const options = {
            enableHighAccuracy: locationCheckAttempts < 2, // Only use high accuracy on first attempt
            timeout: 15000, // Increased timeout
            maximumAge: 60000 // Accept cached location up to 1 minute old
        };

        navigator.geolocation.getCurrentPosition(
            function(position) {
                userLat = position.coords.latitude;
                userLng = position.coords.longitude;

                document.getElementById('lat').value = userLat;
                document.getElementById('lng').value = userLng;

                if (propertyLat !== null && propertyLng !== null) {
                    // Calculate distance (Haversine formula)
                    const R = 6371000; // Earth radius in meters
                    const dLat = (userLat - propertyLat) * Math.PI / 180;
                    const dLng = (userLng - propertyLng) * Math.PI / 180;
                    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                        Math.cos(propertyLat * Math.PI / 180) * Math.cos(userLat * Math.PI / 180) *
                        Math.sin(dLng/2) * Math.sin(dLng/2);
                    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
                    const distance = R * c;

                    if (distance <= propertyRadius) {
                        locationStatus.innerHTML = '<span class="text-green-600 dark:text-green-400">✓ Location verified. You\'re at the property.</span>';
                        startBtn.disabled = false;
                    } else {
                        locationStatus.innerHTML = '<span class="text-red-600 dark:text-red-400">⚠ GPS validation failed. You\'re ' + Math.round(distance) + 'm away from the property. <button type="button" onclick="checkLocation()" class="underline">Retry</button></span>';
                        startBtn.disabled = true;
                    }
                } else {
                    locationStatus.innerHTML = '<span class="text-green-600 dark:text-green-400">✓ Location captured. Property location not set, so verification skipped.</span>';
                    startBtn.disabled = false;
                }
                locationCheckAttempts = 0; // Reset on success
            },
            function(error) {
                const errorMsg = getErrorMessage(error);

                // If we haven't exceeded max attempts and it's not a permission error, retry
                if (locationCheckAttempts < maxAttempts && error.code !== error.PERMISSION_DENIED) {
                    setTimeout(() => checkLocation(), 2000);
                    return;
                }

                // Show error and block
                locationStatus.innerHTML = '<span class="text-red-600 dark:text-red-400">⚠ ' + errorMsg + ' <button type="button" onclick="checkLocation()" class="underline">Try again</button></span>';
                startBtn.disabled = true;
            },
            options
        );
    }

    // Check location on page load
    if (startBtn && locationStatus) {
        // Wait a bit for page to fully load
        setTimeout(() => {
            checkLocation();
        }, 500);
    }

    // Re-check location when form is submitted
    if (startForm) {
        startForm.addEventListener('submit', function(e) {
            // If location is required and not captured, and no override, prevent submit and re-check
            if (!overrideEnabled && propertyLat !== null && propertyLng !== null && (userLat === null || userLng === null)) {
                e.preventDefault();
                locationStatus.innerHTML = '<span class="text-red-600 dark:text-red-400">⚠ Location verification required. Please allow location access.</span>';
                locationCheckAttempts = 0; // Reset attempts
                checkLocation();
            }
        });
    }

    // Make checkLocation available globally for retry buttons
    window.checkLocation = checkLocation;
</script>
