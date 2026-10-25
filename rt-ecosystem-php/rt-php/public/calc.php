<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
session_boot();
if (!is_authenticated()) redirect('/login.php');
$v = (string)@filemtime(__DIR__ . '/assets/calc.js');
?><!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Калькулятор сделок</title><link rel="stylesheet" href="/assets/style.css?v=<?= h($v) ?>"></head>
<body><?php topbar('/calc.php'); ?>
<main class="page"><h1>Калькулятор сделок</h1>
<p class="note">Экспресс-оценка: оборудование × сложность, работы в % от оборудования, затем коэффициенты региона, уровня проекта, срочности и уникальности.</p>
<div class="calc-grid">
 <section class="card"><h2>Параметры</h2>
  <label>Заказчик <input id="customer" placeholder="Название организации"></label>
  <label>Регион <select id="region"></select></label>
  <label>Уровень проекта <select id="level"></select></label>
  <label>Срочность <select id="urgency"><option value="normal">Обычная</option><option value="fast">Ускоренная (+15%)</option><option value="asap">Срочная (+30%)</option></select></label>
  <label class="chk"><input type="checkbox" id="unique"> Уникальное решение / нестандартное ПО (+20%)</label>
  <label>Маржа, % <input id="margin" type="number" min="0" max="60" value="20"></label>
  <h2>Виды работ</h2><div id="stages"></div>
  <h2>Оборудование</h2><div id="items"></div>
 </section>
 <section><div class="kpis k3" id="kpis"></div>
  <div class="card"><h2>Состав стоимости</h2><div id="chart"></div></div>
  <div class="card"><h2>Спецификация</h2><div class="table-wrap"><table><tbody id="rows"></tbody></table></div>
  <form method="post" action="/api/calc-doc"><input type="hidden" name="payload" id="payload"><button class="btn" type="submit">Скачать КП (.doc)</button></form></div>
 </section></div></main><script src="/assets/calc.js?v=<?= h($v) ?>"></script></body></html>
