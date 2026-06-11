<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$status  = in_array($body['status'] ?? '', ['attending', 'not_attending']) ? $body['status'] : null;
$zags    = in_array($body['zags']   ?? '', ['yes', 'no']) ? $body['zags'] : null;
$comment = mb_substr(trim($body['comment'] ?? ''), 0, 500, 'UTF-8');

if (!$status) {
    echo json_encode(['error' => 'invalid']);
    exit;
}

$file = __DIR__ . '/data/general_rsvp.json';
$entries = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
$entries[] = [
    'status'     => $status,
    'zags'       => $zags,
    'comment'    => $comment,
    'updated_at' => date('Y-m-d\TH:i:s'),
];

$dir = dirname($file);
if (!is_dir($dir)) mkdir($dir, 0755, true);
file_put_contents($file, json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo json_encode(['ok' => true]);
