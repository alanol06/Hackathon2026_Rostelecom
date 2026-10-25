<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';

function out(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// /api/companies -> /companies
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$path = '/' . trim((string)preg_replace('#^/api#', '', $path), '/');

if ($path === '/health') {
    out(['status' => 'ok']);
}
if (!is_authenticated()) {            // всё остальное - только после входа с 2FA
    out(['error' => 'unauthorized'], 401);
}

try {
    switch ($path) {
        case '/companies':
            $all      = load_companies();
            $filtered = filter_companies($all, $_GET);
            $offset   = max(0, (int)($_GET['offset'] ?? 0));
            $rows     = array_map(fn(array $r) => [
                'inn'      => $r['inn'],
                'name'     => $r['name'],
                'type'     => $r['type'],
                'industry' => $r['industry'],
                'activity' => $r['okved_name'],
                'place'    => $r['place'],
                'size'     => $r['size'],
            ], array_slice($filtered, $offset, 50));
            $resp = ['total' => count($filtered), 'summary' => summarize($filtered), 'rows' => $rows];
            if ($offset === 0) {
                $resp['filters'] = filter_options($all);
            }
            out($resp);

        case '/export':
            $filtered = filter_companies(load_companies(), $_GET);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="clients.csv"');
            echo "\xEF\xBB\xBF";                      // BOM, чтобы Excel понял кириллицу
            $o = fopen('php://output', 'w');
            fputcsv($o, ['ИНН', 'Название', 'Тип', 'Отрасль', 'ОКВЭД', 'Вид деятельности',
                         'Населённый пункт', 'Размер', 'Год включения в реестр', 'Наш клиент', 'Выручка, млн руб. (оценка)'], ';', '"', '');
            foreach ($filtered as $r) {
                fputcsv($o, array_map('csv_safe', [
                    $r['inn'], $r['name'], $r['type'], $r['industry'], $r['okved'],
                    $r['okved_name'], $r['place'], $r['size'], (string)$r['year'], $r['ours'] ? 'да' : 'нет', (string)$r['rev'],
                ]), ';', '"', '');
            }
            exit;

        case '/map':
            $f = filter_companies(load_companies(), $_GET);
            $zoom = (int)($_GET['zoom'] ?? 7);
            $bb = array_map('floatval', explode(',', (string)($_GET['bbox'] ?? '')));   // юг,запад,север,восток
            $cells = []; $pts = []; $ours = 0;
            foreach ($f as $r) {
                $ours += $r['ours'] ? 1 : 0;
                $k = round($r['lat'] / 0.03) . ':' . round($r['lng'] / 0.04);
                $cells[$k] = ($cells[$k] ?? [$r['lat'], $r['lng'], 0]);
                $cells[$k][2]++;
                if ($zoom >= 9 && count($bb) === 4 && count($pts) < 2500
                    && $r['lat'] >= $bb[0] && $r['lat'] <= $bb[2] && $r['lng'] >= $bb[1] && $r['lng'] <= $bb[3]) {
                    $pts[] = [$r['lat'], $r['lng'], $r['ours'] ? 1 : 0, $r['name'], $r['industry'], $r['rev'], $r['inn']];
                }
            }
            out(['total' => count($f), 'ours' => $ours, 'cells' => array_values($cells), 'points' => $pts, 'has_other' => $ours < count($f)]);

        case '/invest':
            $all = load_invest(); $f = filter_invest($all, $_GET);
            $years = []; $byInd = [];
            foreach ($f as $r) {
                $years[$r['end']] = ($years[$r['end']] ?? 0) + $r['sum'];
                $byInd[$r['industry']] = ($byInd[$r['industry']] ?? 0) + 1;
            }
            ksort($years); arsort($byInd);
            out(['total' => count($f), 'sum' => array_sum(array_column($f, 'sum')), 'jobs' => array_sum(array_column($f, 'jobs')),
                 'years' => $years, 'by_industry' => $byInd, 'rows' => $f,
                 'filters' => ['places' => array_values(array_unique(array_column($all, 'place'))), 'industries' => array_values(array_unique(array_column($all, 'industry')))]]);

        case '/invest-export':
            $f = filter_invest(load_invest(), $_GET);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="invest.csv"');
            echo "\xEF\xBB\xBF"; $o = fopen('php://output', 'w');
            fputcsv($o, ['Территория', 'Инициатор', 'Проект', 'Отрасль', 'Стадия', 'Потребности', 'Начало', 'Ввод', 'Млн руб.', 'Рабочих мест'], ';', '"', '');
            foreach ($f as $r) fputcsv($o, array_map('csv_safe', [$r['place'], $r['initiator'], $r['title'], $r['industry'], $r['stage'], implode(', ', $r['needs']), (string)$r['start'], (string)$r['end'], (string)$r['sum'], (string)$r['jobs']]), ';', '"', '');
            exit;

        case '/calc-meta':
            out(calc_data());

        case '/calc':
            out(calc_quote(json_decode((string)file_get_contents('php://input'), true) ?: []));

        case '/calc-doc':
            $in = json_decode((string)($_POST['payload'] ?? '{}'), true) ?: [];
            header('Content-Type: application/msword; charset=utf-8');
            header('Content-Disposition: attachment; filename="kp.doc"');
            echo quote_doc($in, calc_quote($in));
            exit;

        default:
            out(['error' => 'not found'], 404);
    }
} catch (Throwable $e) {
    error_log($e->getMessage());
    out(['error' => 'Ошибка сервера'], 500);
}
