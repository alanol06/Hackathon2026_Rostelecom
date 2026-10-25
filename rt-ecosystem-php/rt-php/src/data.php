<?php
declare(strict_types=1);

/**
 * Данные читаются прямо из data/ours.csv (без базы данных).
 * Файл небольшой, поэтому разбор на каждый запрос занимает доли секунды.
 */

function load_companies(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $EMP = 'Среднесписочная численность работников за предшествующий календарный год';
    $rows = [];
    $seen = [];
    // Сначала «наши» (ours.csv), затем остальные (rmsp.csv, если файл положили в data/).
    foreach ([['ours.csv', true], ['rmsp.csv', false]] as [$file, $ours]) {
        $h = @fopen(DATA_DIR . '/' . $file, 'r');
        if ($h === false) {
            if ($ours) throw new RuntimeException('Не найден файл data/ours.csv');
            continue;
        }
        // fgetcsv читает значения как строки, поэтому ведущие нули ИНН сохраняются.
        $header    = fgetcsv($h, 0, ',', '"', '');
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$header[0]);   // BOM
        $idx       = array_flip(array_map('trim', $header));
        if (!isset($idx['ИНН'])) {
            throw new RuntimeException('В CSV нет колонки «ИНН»');
        }
        $get = function (array $row, string $name) use ($idx): string {
            return isset($idx[$name]) ? trim((string)($row[$idx[$name]] ?? '')) : '';
        };
        while (($r = fgetcsv($h, 0, ',', '"', '')) !== false) {
            if (count($r) < 2) {
                continue;
            }
            $inn = $get($r, 'ИНН');
            if ($inn === '' || isset($seen[$inn])) {
                continue;                                   // пустой ИНН или дубль
            }
            if ($get($r, 'Дата исключения из реестра') !== '') {
                continue;                                   // закрылись, исключены из реестра
            }
            $seen[$inn] = true;

            $okved = $okvedName = '';
            if (preg_match('/^([\d\.]+)\s*(.*)$/u', $get($r, 'Основной вид деятельности'), $m)) {
                $okved = $m[1];
                $okvedName = $m[2];
            }
            $year = preg_match('/(\d{4})$/', $get($r, 'Дата включения в реестр'), $m) ? (int)$m[1] : null;

            [$place, $lat, $lng] = geo_locate($inn, $get($r, 'Город'), $get($r, 'Район'), $get($r, 'Населенный пункт'));

            $emp  = $get($r, $EMP);
            $isIp = $get($r, 'Тип субъекта') === 'Индивидуальный предприниматель';
            $size = $get($r, 'Категория');
            // Выручки в открытых данных РСМП нет, поэтому это ОЦЕНКА (млн руб.): штат × 3,2 млн
            // либо типовое значение по категории, с разбросом 0,6–1,6 по ИНН. Для демо.
            $base = is_numeric($emp) && (int)$emp > 0 ? (int)$emp * 3.2
                : ($isIp ? 4.0 : (str_starts_with($size, 'Микро') ? 8.0 : (str_starts_with($size, 'Малое') ? 60.0 : 300.0)));
            $rev  = round($base * (0.6 + (crc32($inn) % 1000) / 1000), 1);

            $rows[] = [
                'inn'        => $inn,
                'name'       => $get($r, 'Наименование / ФИО'),
                'is_ip'      => $isIp,
                'type'       => $isIp ? 'ИП' : 'Юрлицо',
                'size'       => $get($r, 'Категория'),
                'okved'      => $okved,
                'okved_name' => $okvedName,
                'industry'   => industry_of($okved),
                'place'      => $place,
                'is_new'     => $get($r, 'Вновь созданный') === 'Да',
                'year'       => $year,
                'employees'  => is_numeric($emp) ? (int)$emp : null,
                'ours'       => $ours,
                'lat'        => round($lat, 4),
                'lng'        => round($lng, 4),
                'rev'        => $rev,
            ];
        }
        fclose($h);
    }
    return $cache = $rows;
}

/** Фильтры: industry, place, type (ip|ul), q (поиск по названию или ИНН). */
function filter_companies(array $rows, array $q): array
{
    $ind   = trim((string)($q['industry'] ?? ''));
    $place = trim((string)($q['place'] ?? ''));
    $type  = trim((string)($q['type'] ?? ''));
    $text  = mb_strtolower(trim((string)($q['q'] ?? '')));
    $own   = (string)($q['own'] ?? '');
    $rmin  = ($q['rev_min'] ?? '') !== '' ? (float)$q['rev_min'] : null;
    $rmax  = ($q['rev_max'] ?? '') !== '' ? (float)$q['rev_max'] : null;

    return array_values(array_filter($rows, function (array $r) use ($ind, $place, $type, $text, $own, $rmin, $rmax): bool {
        if ($ind !== '' && $r['industry'] !== $ind) {
            return false;
        }
        if ($place !== '' && $r['place'] !== $place) {
            return false;
        }
        if (($own === 'ours' && !$r['ours']) || ($own === 'other' && $r['ours'])) {
            return false;
        }
        if (($rmin !== null && $r['rev'] < $rmin) || ($rmax !== null && $r['rev'] > $rmax)) {
            return false;
        }
        if ($type === 'ip' && !$r['is_ip']) {
            return false;
        }
        if ($type === 'ul' && $r['is_ip']) {
            return false;
        }
        if ($text !== '' && !str_contains(mb_strtolower($r['name']), $text) && !str_contains($r['inn'], $text)) {
            return false;
        }
        return true;
    }));
}

/** Сводка для карточек и графиков. */
function summarize(array $rows): array
{
    $byInd = [];
    $byPlace = [];
    $ip = 0;
    $new = 0;
    foreach ($rows as $r) {
        $byInd[$r['industry']] = ($byInd[$r['industry']] ?? 0) + 1;
        if ($r['place'] !== '') {
            $byPlace[$r['place']] = ($byPlace[$r['place']] ?? 0) + 1;
        }
        $ip  += $r['is_ip'] ? 1 : 0;
        $new += $r['is_new'] ? 1 : 0;
    }
    arsort($byInd);
    arsort($byPlace);
    $fmt = fn(array $a): array => array_map(
        fn($k, $v) => ['name' => (string)$k, 'n' => $v],
        array_keys($a),
        array_values($a)
    );
    return [
        'ip'          => $ip,
        'new'         => $new,
        'industries'  => count($byInd),
        'by_industry' => $fmt($byInd),
        'by_place'    => $fmt(array_slice($byPlace, 0, 10, true)),
    ];
}

/** Списки значений для выпадающих фильтров. */
function filter_options(array $rows): array
{
    $ind = array_unique(array_column($rows, 'industry'));
    $pl  = array_unique(array_filter(array_column($rows, 'place')));
    sort($ind);
    sort($pl);
    return ['industries' => array_values($ind), 'places' => array_values($pl)];
}

/** Защита от «формульных» значений при открытии CSV в Excel. */
function csv_safe(string $v): string
{
    return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
}
