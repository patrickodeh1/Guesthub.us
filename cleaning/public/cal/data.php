<?php
/**
 * StaySync — Persistent Storage API
 * GET  → returns all saved sources as JSON
 * POST → saves sources array to JSON file
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$dataDir  = __DIR__ . '/data';
$dataFile = $dataDir . '/sources.json';

// Ensure data directory exists
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

// ── GET: load saved data ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!file_exists($dataFile)) {
        echo json_encode(['sources' => []]);
        exit;
    }
    $raw  = file_get_contents($dataFile);
    $data = json_decode($raw, true);
    echo json_encode(is_array($data) ? $data : ['sources' => []]);
    exit;
}

// ── POST: save data ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);

    if (!is_array($body) || !array_key_exists('sources', $body)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid payload']);
        exit;
    }

    $ok = file_put_contents(
        $dataFile,
        json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    ) !== false;

    echo json_encode(['success' => $ok, 'count' => count($body['sources'])]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
