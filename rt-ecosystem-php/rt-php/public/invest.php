<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
session_boot();
if (!is_authenticated()) redirect('/login.php');
$v = (string)@filemtime(__DIR__ . '/assets/invest.js');
?><!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Инвестиционный навигатор</title><link rel="stylesheet" href="/assets/style.css?v=<?= h($v) ?>"></head>
<body><?php topbar('/invest.php'); ?>
<main class="page"><h1>Инвестиционный навигатор</h1>

<section class="filters f6">
 <label>Стадия <select id="stage"><option value="">Все</option><option>Проектирование</option><option>Стройка</option><option>Запуск</option></select></label>
 <label>Отрасль <select id="industry"><option value="">Все</option></select></label>
 <label>Территория <select id="place"><option value="">Все</option></select></label>
 <label>Потребность <select id="need"><option value="">Все</option><option>ИТ-инфраструктура</option><option>АСУ ТП</option><option>Безопасность</option></select></label>
 <label>Поиск <input id="q" placeholder="Инициатор или проект"></label>
 <label class="chk"><input type="checkbox" id="key"> Только ключевые</label>
 <a id="export" class="btn btn-outline" href="/api/invest-export">Скачать CSV</a>
</section>
<section class="kpis" id="kpis"></section>
<section class="charts"><div class="card"><h2>Ввод в эксплуатацию по годам, млн ₽</h2><div id="chart-years"></div></div>
<div class="card"><h2>Проекты по отраслям</h2><div id="chart-ind"></div></div></section>
<section class="card"><h2>Проекты <span id="count" class="muted"></span></h2>
<div class="table-wrap"><table><thead><tr><th>Территория</th><th>Инициатор</th><th>Проект</th><th>Стадия</th><th>Потребности</th><th>Сроки</th><th>Млн ₽</th></tr></thead><tbody id="rows"></tbody></table></div></section>
</main><script src="/assets/invest.js?v=<?= h($v) ?>"></script></body></html>
