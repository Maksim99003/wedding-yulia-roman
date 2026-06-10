<?php
session_start();
require_once __DIR__ . '/config.php';

if (empty($_SESSION['admin_auth'])) {
    header('Location: admin.php');
    exit;
}

$guests = load_guests();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="guests_' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-cache, no-store, must-revalidate');

$out = fopen('php://output', 'w');

// UTF-8 BOM — Excel откроет кириллицу корректно
fputs($out, "\xEF\xBB\xBF");

// Заголовки
fputcsv($out, ['№', 'Имя', 'Фамилия', 'Пол', 'Статус', 'В ЗАГС', 'Комментарий', 'Ссылка', 'Дата ответа'], ';');

$n = 0;
foreach ($guests as $slug => $g) {
    $st      = $g['rsvp']['status']   ?? null;
    $zags    = $g['rsvp']['zags']     ?? null;
    $comment = $g['rsvp']['comment']  ?? '';
    $date    = isset($g['rsvp']['updated_at'])
        ? date('d.m.Y H:i', strtotime($g['rsvp']['updated_at']))
        : '';
    $url     = SITE_URL . '/invite_' . $slug;

    $statusTxt = $st === 'attending' ? 'Придёт' : ($st === 'not_attending' ? 'Не придёт' : 'Ожидает');
    $zagsTxt   = ($st === 'not_attending') ? '—'
               : ($zags === 'yes' ? 'Будет' : ($zags === 'no' ? 'Не будет' : '—'));

    $members = !empty($g['members']) ? $g['members'] : [['first' => $g['name'] ?? '', 'last' => '', 'gender' => '']];

    foreach ($members as $m) {
        $n++;
        $genderTxt = ($m['gender'] ?? '') === 'm' ? 'М' : (($m['gender'] ?? '') === 'f' ? 'Ж' : '');
        fputcsv($out, [
            $n,
            $m['first'] ?? '',
            $m['last']  ?? '',
            $genderTxt,
            $statusTxt,
            $zagsTxt,
            $comment,
            $url,
            $date,
        ], ';');
    }
}

fclose($out);
