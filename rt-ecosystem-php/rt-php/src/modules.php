<?php
declare(strict_types=1);

const NOW_YEAR = 2026;

/** Верхняя панель с переходом между модулями. */
function topbar(string $active): void
{
    $tabs = ['/' => 'Радар присутствия', '/invest.php' => 'Инвестиционный навигатор', '/calc.php' => 'Калькулятор сделок'];
    if (($_SESSION['auth_user'] ?? '') === 'admin') {
        $tabs['/admin.php'] = 'Админ-панель';
    }
    echo '<header class="topbar"><div class="topbar-title">Экосистема коммерческого блока</div>'
  . '<button class="menu-btn" aria-label="Меню" onclick="document.querySelector(\'.nav\').classList.toggle(\'open\')">☰</button>'
  . '<nav class="nav">';
    foreach ($tabs as $u => $t) {
        echo '<a href="' . $u . '"' . ($u === $active ? ' class="on"' : '') . '>' . h($t) . '</a>';
    }
    echo '</nav><div class="topbar-user">' . h((string)$_SESSION['auth_user']) . ' · <a href="/logout.php">Выйти</a></div></header>';
}

/** Отрасль и потребность по ключевым словам названия проекта. */
function classify_project(string $t): array
{
    $t = mb_strtolower($t);
    $has = fn(array $w): bool => (bool)array_filter($w, fn($x) => str_contains($t, $x));
    $ind = match (true) {
        $has(['азс', 'нефт', 'газ', 'энерг', 'электро', 'котельн', 'солнечн'])   => 'ТЭК',
        $has(['логист', 'склад', 'терминал', 'транспорт', 'автодор', 'порт'])    => 'Логистика и склады',
        $has(['теплиц', 'животно', 'свин', 'корм', 'ферм', 'зерно', 'агро', 'овощ', 'молоч', 'птиц', 'рыб', 'сад']) => 'АПК',
        $has(['магазин', 'торгов', 'рынок', 'маркет'])                           => 'Торговля',
        $has(['завод', 'цех', 'производств', 'фабрик'])                          => 'Промышленность',
        default                                                                  => 'Услуги населению',
    };
    $needs = [];
    if ($has(['цех', 'завод', 'производств', 'линия', 'автоматиз', 'котельн', 'теплиц'])) $needs[] = 'АСУ ТП';
    if ($has(['отдых', 'гостин', 'отель', 'стадион', 'комплекс', 'склад', 'логист', 'торгов', 'здани', 'центр'])) $needs[] = 'Безопасность';
    $needs[] = 'ИТ-инфраструктура';   // связь и ЦОД нужны любому новому объекту
    return [$ind, $needs];
}

function load_invest(): array
{
    static $c = null;
    if ($c !== null) return $c;
    $h = fopen(DATA_DIR . '/invest.csv', 'r');
    $hd = fgetcsv($h, 0, ',', '"', '');
    $out = [];
    while (($r = fgetcsv($h, 0, ',', '"', '')) !== false) {
        $x = array_combine($hd, $r);
        [$ind, $needs] = classify_project($x['title']);
        $s = (int)$x['start']; $e = (int)$x['end'];
        $stage = $s > NOW_YEAR ? 'Проектирование' : ($e <= NOW_YEAR ? 'Запуск' : 'Стройка');
        $out[] = ['id' => (int)$x['n'], 'place' => $x['place'], 'key' => $x['key'] === 'Да', 'initiator' => $x['initiator'],
            'title' => $x['title'], 'start' => $s, 'end' => $e, 'sum' => (float)$x['sum'], 'jobs' => (int)$x['jobs'],
            'stage' => $stage, 'industry' => $ind, 'needs' => $needs];
    }
    fclose($h);
    return $c = $out;
}

function filter_invest(array $rows, array $q): array
{
    $t = mb_strtolower(trim((string)($q['q'] ?? '')));
    return array_values(array_filter($rows, function ($r) use ($q, $t) {
        foreach (['stage', 'industry', 'place'] as $k) {
            if (($q[$k] ?? '') !== '' && $r[$k] !== $q[$k]) return false;
        }
        if (($q['need'] ?? '') !== '' && !in_array($q['need'], $r['needs'], true)) return false;
        if (($q['key'] ?? '') === '1' && !$r['key']) return false;
        return $t === '' || str_contains(mb_strtolower($r['initiator'] . ' ' . $r['title']), $t);
    }));
}

function calc_data(): array
{
    return json_decode((string)file_get_contents(DATA_DIR . '/calc.json'), true, 512, JSON_THROW_ON_ERROR);
}

/** Расчёт: оборудование -> работы (% от оборудования) -> коэффициенты -> вилка цены. */
function calc_quote(array $in): array
{
    $d = calc_data();
    $byId = array_column($d['items'], null, 'id');
    $lines = []; $equip = 0.0;
    foreach ((array)($in['items'] ?? []) as $id => $qty) {
        $qty = (int)$qty;
        if ($qty < 1 || !isset($byId[$id])) continue;
        $it = $byId[$id];
        $sum = $qty * $it['price'] * $it['k'];
        $equip += $sum;
        $lines[] = ['name' => $it['name'], 'group' => $it['group'], 'qty' => $qty, 'sum' => round($sum)];
    }
    $stagesSel = (array)($in['stages'] ?? []);
    $works = []; $worksSum = 0.0;
    foreach ($d['stages'] as $s) {
        if (in_array($s['name'], $stagesSel, true)) {
            $v = $equip * $s['pct'];
            $works[] = ['name' => $s['name'], 'sum' => round($v)];
            $worksSum += $v;
        }
    }
    $reg = array_column($d['regions'], 'k', 'name')[$in['region'] ?? ''] ?? 1.0;
    $lvl = array_column($d['levels'], 'k', 'name')[$in['level'] ?? ''] ?? 1.0;
    $urgent = ['normal' => 1.0, 'fast' => 1.15, 'asap' => 1.3][$in['urgency'] ?? 'normal'] ?? 1.0;
    $unique = !empty($in['unique']) ? 1.2 : 1.0;
    $margin = min(60.0, max(0.0, (float)($in['margin'] ?? 20))) / 100;
    $cost = ($equip + $worksSum) * $reg * $lvl * $urgent * $unique;
    return [
        'lines' => $lines, 'works' => $works, 'equipment' => round($equip), 'works_sum' => round($worksSum),
        'k' => ['region' => $reg, 'level' => $lvl, 'urgency' => $urgent, 'unique' => $unique],
        'cost' => round($cost), 'min' => round($cost * 1.05), 'opt' => round($cost * (1 + $margin)),
        'max' => round($cost * (1 + $margin + 0.15)), 'margin' => $margin * 100,
    ];
}

/** Коммерческое предложение в формате .doc (HTML, открывается в Word). */
function quote_doc(array $in, array $q): string
{
    $m = fn($v) => number_format((float)$v, 0, ',', ' ') . ' ₽';
    $rows = '';
    foreach ($q['lines'] as $l) $rows .= '<tr><td>' . h($l['group'] . ': ' . $l['name']) . '</td><td>' . $l['qty'] . '</td><td>' . $m($l['sum']) . '</td></tr>';
    foreach ($q['works'] as $w) $rows .= '<tr><td>' . h($w['name']) . '</td><td>—</td><td>' . $m($w['sum']) . '</td></tr>';
    $cust = h((string)($in['customer'] ?? 'Заказчик'));
    return '<html><head><meta charset="utf-8"><style>body{font-family:Arial}h1{color:#7700FF}td,th{border:1px solid #ccc;padding:6px}table{border-collapse:collapse}</style></head><body>'
        . '<h1>Коммерческое предложение</h1><p>ПАО «Ростелеком» · Технологии возможностей</p><p>Заказчик: <b>' . $cust . '</b><br>Регион: ' . h((string)($in['region'] ?? '')) . ' · Уровень: ' . h((string)($in['level'] ?? '')) . '<br>Дата: ' . date('d.m.Y') . '</p>'
        . '<table><tr><th>Позиция</th><th>Кол-во</th><th>Стоимость</th></tr>' . $rows . '</table>'
        . '<p>Оборудование: ' . $m($q['equipment']) . '; работы: ' . $m($q['works_sum']) . '.</p>'
        . '<h2>Стоимость проекта: ' . $m($q['opt']) . '</h2><p>Ориентировочная вилка: ' . $m($q['min']) . ' — ' . $m($q['max']) . '. Предложение действительно 30 дней.</p></body></html>';
}
