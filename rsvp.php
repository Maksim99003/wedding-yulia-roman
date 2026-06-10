<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $slug = preg_replace('/[^a-z0-9_-]/', '', $_GET['guest'] ?? '');
    if (!$slug) { echo json_encode(['error' => 'invalid']); exit; }

    $guests = load_guests();
    $g = $guests[$slug] ?? null;
    if (!$g) { echo json_encode(['error' => 'not_found']); exit; }

    echo json_encode([
        'name'    => $g['name'],
        'status'  => $g['rsvp']['status']  ?? null,
        'comment' => $g['rsvp']['comment'] ?? '',
    ]);
    exit;
}

if ($method === 'POST') {
    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $slug    = preg_replace('/[^a-z0-9_-]/', '', $body['guest'] ?? '');
    $status  = in_array($body['status'] ?? '', ['attending', 'not_attending']) ? $body['status'] : null;
    $comment = mb_substr(trim($body['comment'] ?? ''), 0, 500, 'UTF-8');
    $zags    = in_array($body['zags'] ?? '', ['yes', 'no']) ? $body['zags'] : null;

    if (!$slug || !$status) { echo json_encode(['error' => 'invalid']); exit; }

    $guests = load_guests();
    if (!isset($guests[$slug])) { echo json_encode(['error' => 'not_found']); exit; }

    $guests[$slug]['rsvp'] = [
        'status'     => $status,
        'comment'    => $comment,
        'zags'       => $zags,
        'updated_at' => date('Y-m-d\TH:i:s'),
    ];
    save_guests($guests);
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['error' => 'method_not_allowed']);
