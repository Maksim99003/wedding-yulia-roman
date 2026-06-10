<?php
// Пароль для входа в панель управления — измените на свой
define('ADMIN_PASS', 'wedding2026');

define('DATA_FILE', __DIR__ . '/data/guests.json');
define('SITE_URL',  'http://weddingyuliaandroman.ru');

function load_guests(): array {
    if (!file_exists(DATA_FILE)) return [];
    return json_decode(file_get_contents(DATA_FILE), true) ?: [];
}

function save_guests(array $guests): void {
    $dir = dirname(DATA_FILE);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $tmp = DATA_FILE . '.tmp.' . uniqid();
    file_put_contents($tmp, json_encode($guests, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    rename($tmp, DATA_FILE);
}

function slugify(string $name): string {
    $name = mb_strtolower(trim($name), 'UTF-8');
    $t = [
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh','з'=>'z',
        'и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r',
        'с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh',
        'щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',' '=>'_','-'=>'_',
    ];
    $name = strtr($name, $t);
    $name = preg_replace('/[^a-z0-9_]+/', '_', $name);
    return trim($name, '_') ?: 'guest';
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// Слаг: "Иван" + "Петров" → "ivanp"
function makeSlug(string $first, string $last): string {
    $t = [
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh','з'=>'z',
        'и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r',
        'с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh',
        'щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
    ];
    $fSlug = preg_replace('/[^a-z0-9]+/', '', strtr(mb_strtolower(trim($first),'UTF-8'), $t));
    $lInit = '';
    if (trim($last)) {
        $lInit = preg_replace('/[^a-z0-9]+/', '', strtr(mb_substr(mb_strtolower(trim($last),'UTF-8'),0,1,'UTF-8'), $t));
    }
    return ($fSlug . $lInit) ?: 'guest';
}

// Массив полных имён ["Иван Петров", "Мария Петрова"]
function guestDisplayNames(array $guest): array {
    if (!empty($guest['members']) && is_array($guest['members'])) {
        return array_map(fn($m) => trim(($m['first'] ?? '') . ' ' . ($m['last'] ?? '')), $guest['members']);
    }
    return [$guest['name'] ?? 'Гость'];
}

// Приветствие: "Дорогой(-ая) Иван" / "Дорогие Иван и Мария"
function guestGreeting(array $guest): string {
    if (!empty($guest['members']) && is_array($guest['members'])) {
        $firsts = array_map(fn($m) => trim($m['first'] ?? ''), $guest['members']);
    } else {
        $firsts = [$guest['name'] ?? 'Гость'];
    }
    $firsts = array_filter($firsts);
    if (count($firsts) === 1) return 'Дорогой(-ая) ' . reset($firsts);
    $last = array_pop($firsts);
    return 'Дорогие ' . implode(', ', $firsts) . ' и ' . $last;
}
