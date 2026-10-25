<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
session_boot();
if (!is_authenticated()) {
    redirect('/login.php');
}
$v = (string)@filemtime(__DIR__ . '/assets/app.js');   // сброс кэша браузера при обновлении
?><!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Радар присутствия · Экосистема коммерческого блока</title>
  <link rel="stylesheet" href="/assets/style.css?v=<?= h($v) ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/MarkerCluster.css">
</head>
<body>
  <?php topbar('/'); ?>

  <main class="page">
    <h1>Радар присутствия</h1>


    <section class="filters">
      <label>Отрасль <select id="industry"><option value="">Все</option></select></label>
      <label>Населённый пункт <select id="place"><option value="">Все</option></select></label>
      <label>Тип <select id="type">
        <option value="">Все</option><option value="ul">Юрлица</option><option value="ip">ИП</option></select></label>
      <label>Поиск <input id="q" placeholder="Название или ИНН"></label>
      <label>Клиенты <select id="own"><option value="">Все</option><option value="ours">Только наши</option><option value="other">Только не наши</option></select></label>
      <label>Выручка от, млн ₽ <input id="rev_min" type="number" min="0" placeholder="0"></label>
      <label>Выручка до, млн ₽ <input id="rev_max" type="number" min="0" placeholder="∞"></label>
      <a id="export" class="btn btn-outline" href="/api/export">Скачать CSV</a>
    </section>

    <section class="card mapcard">
      <h2>Карта</h2>
      <div id="map"></div>
      <div class="legend"><span><i style="background:#7700FF"></i>наши клиенты</span><span><i style="background:#FF4F12"></i>не наши</span><span id="map-note" class="muted"></span></div>
    </section>

    <section class="kpis" id="kpis"></section>

    <section class="charts">
      <div class="card"><h2>По отраслям</h2><div id="chart-industry"></div></div>
      <div class="card"><h2>Топ населённых пунктов</h2><div id="chart-place"></div></div>
    </section>

    <section class="card">
      <h2>Компании <span id="count" class="muted"></span></h2>
      <div class="table-wrap">
        <table>
          <thead><tr>
            <th>Название</th><th>ИНН</th><th>Тип</th><th>Отрасль</th>
            <th class="col-opt">Вид деятельности</th><th>Место</th><th class="col-opt">Размер</th>
          </tr></thead>
          <tbody id="rows"></tbody>
        </table>
      </div>
      <button id="more" class="btn btn-outline" hidden>Показать ещё</button>
    </section>
  </main>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.heat/0.2.0/leaflet-heat.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/leaflet.markercluster.js"></script>
  <script src="/assets/app.js?v=<?= h($v) ?>"></script>
  <script src="/assets/map.js?v=<?= h($v) ?>"></script>
</body>
</html>
