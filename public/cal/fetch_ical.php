<?php
/**
 * StaySync — iCal Proxy & Parser
 * Letakkan file ini di server PHP yang sama dengan index.php
 * Panggil: fetch_ical.php?url=https://...&platform=airbnb&unit=My+Unit
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$url      = $_GET['url']      ?? '';
$platform = $_GET['platform'] ?? 'airbnb';
$unit     = $_GET['unit']     ?? 'My Unit';

if (empty($url)) {
    echo json_encode(['error' => 'No URL provided']);
    exit;
}

// Validasi URL
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['error' => 'Invalid URL']);
    exit;
}

// Hanya izinkan domain platform yang diketahui
$allowed_domains = [
    'airbnb.com', 'www.airbnb.com',
    'vrbo.com', 'www.vrbo.com',
    'booking.com', 'admin.booking.com',
    'homeaway.com', 'www.homeaway.com',
];

$host = parse_url($url, PHP_URL_HOST);
$is_allowed = false;
foreach ($allowed_domains as $d) {
    if ($host === $d || str_ends_with($host, '.' . $d)) {
        $is_allowed = true;
        break;
    }
}

// Juga izinkan URL .ics apapun (untuk testing)
if (!$is_allowed && str_contains($url, '.ics')) {
    $is_allowed = true;
}

if (!$is_allowed) {
    echo json_encode(['error' => 'Domain not allowed. Only Airbnb, VRBO, Booking.com iCal URLs accepted.']);
    exit;
}

// Fetch iCal
$ctx = stream_context_create([
    'http' => [
        'timeout' => 15,
        'user_agent' => 'Mozilla/5.0 (compatible; StaySync/1.0)',
    ],
    'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
    ]
]);

$ics = @file_get_contents($url, false, $ctx);

if ($ics === false) {
    echo json_encode(['error' => 'Failed to fetch iCal URL. Check the URL and try again.']);
    exit;
}

// ── PARSE iCal ──────────────────────────────────────────
function parseIcal($ics, $platform, $unit) {
    $events = [];

    // Unfold line continuations (RFC 5545)
    $ics = preg_replace('/\r\n[ \t]/', '', $ics);
    $ics = str_replace("\r\n", "\n", $ics);

    preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $ics, $matches);

    foreach ($matches[1] as $i => $block) {
        $get = function($key) use ($block) {
            if (preg_match("/{$key}[^:]*:([^\n]+)/", $block, $m)) {
                return trim($m[1]);
            }
            return '';
        };

        $summary = $get('SUMMARY');

        // Skip blocked/unavailable entries — only show real reservations
        if (preg_match('/block|unavailable|not\s+available|closed|hold/i', $summary)) {
            continue;
        }

        $rawStart = $get('DTSTART');
        $rawEnd   = $get('DTEND');

        if (empty($rawStart) || empty($rawEnd)) continue;

        // Parse date: 20240312 or 20240312T120000Z
        $parseDate = function($raw) {
            $raw = preg_replace('/[TZ].*/', '', $raw);
            return sprintf('%s-%s-%s', substr($raw,0,4), substr($raw,4,2), substr($raw,6,2));
        };

        $start = $parseDate($rawStart);
        $end   = $parseDate($rawEnd);

        // Extract guest name from SUMMARY
        $guest = $summary;
        $guest = preg_replace('/reserved|reservation|airbnb|vrbo|booking\.com|booking|homeaway/i', '', $guest);
        $guest = trim($guest, ' -–—_');
        if (empty($guest)) $guest = 'Guest';

        // Try UID for unique ID
        $uid = $get('UID') ?: ($platform . '-' . $i);

        $events[] = [
            'id'       => md5($uid),
            'platform' => $platform,
            'unit'     => $unit,
            'guest'    => $guest,
            'start'    => $start,
            'end'      => $end,
        ];
    }

    return $events;
}

$events = parseIcal($ics, $platform, $unit);

echo json_encode([
    'success' => true,
    'platform' => $platform,
    'unit'    => $unit,
    'count'   => count($events),
    'events'  => $events,
]);
