// Прототип «Радара присутствия»: фильтры, карточки, графики, таблица.
const $ = (id) => document.getElementById(id);
const state = { offset: 0, total: 0, filtersReady: false };

function currentParams(extra = {}) {
  const p = new URLSearchParams();
  for (const k of ['industry', 'place', 'type', 'q', 'own', 'rev_min', 'rev_max']) {
    const v = $(k).value.trim();
    if (v) p.set(k, v);
  }
  for (const [k, v] of Object.entries(extra)) p.set(k, v);
  return p;
}

function fillSelect(id, values) {
  const sel = $(id);
  for (const v of values) {
    const o = document.createElement('option');
    o.value = v;
    o.textContent = v;
    sel.appendChild(o);
  }
}

function renderKpis(d) {
  const s = d.summary;
  const share = d.total ? Math.round((s.ip / d.total) * 100) : 0;
  const items = [
    [d.total, 'компаний'],
    [share + '%', 'индивидуальных предпринимателей'],
    [s.new, 'вновь созданных'],
    [s.industries, 'отраслей'],
  ];
  const box = $('kpis');
  box.replaceChildren();
  for (const [v, l] of items) {
    const el = document.createElement('div');
    el.className = 'kpi';
    el.innerHTML = '<div class="v"></div><div class="l"></div>';
    el.querySelector('.v').textContent = v;
    el.querySelector('.l').textContent = l;
    box.appendChild(el);
  }
}

function renderBars(id, items) {
  const box = $(id);
  box.replaceChildren();
  if (!items.length) {
    box.innerHTML = '<div class="empty">Нет данных</div>';
    return;
  }
  const max = Math.max(...items.map((i) => i.n));
  for (const i of items) {
    const row = document.createElement('div');
    row.className = 'bar-row';
    row.innerHTML = '<div class="bar-label"></div><div class="bar-track"><div class="bar-fill"></div></div><div class="bar-val"></div>';
    row.querySelector('.bar-label').textContent = i.name;
    row.querySelector('.bar-label').title = i.name;
    row.querySelector('.bar-fill').style.width = (i.n / max) * 100 + '%';
    row.querySelector('.bar-val').textContent = i.n;
    box.appendChild(row);
  }
}

function addRows(rows) {
  const tb = $('rows');
  for (const r of rows) {
    const tr = document.createElement('tr');
    const cells = [r.name, r.inn, r.type, r.industry, r.activity, r.place, r.size];
    cells.forEach((text, i) => {
      const td = document.createElement('td');
      if (i === 2 || i === 3) {
        const span = document.createElement('span');
        span.className = 'tag';
        span.textContent = text;
        td.appendChild(span);
      } else {
        td.textContent = text;
      }
      if (i === 4 || i === 6) td.className = 'col-opt';
      tr.appendChild(td);
    });
    tb.appendChild(tr);
  }
}

async function load(append = false) {
  if (!append) state.offset = 0;
  const res = await fetch('/api/companies?' + currentParams({ offset: state.offset }), { credentials: 'same-origin' });
  if (res.status === 401) { location.href = '/login.php'; return; }
  if (!res.ok) { $('count').textContent = '— ошибка загрузки'; return; }
  const d = await res.json();

  if (d.filters && !state.filtersReady) {
    fillSelect('industry', d.filters.industries);
    fillSelect('place', d.filters.places);
    state.filtersReady = true;
  }
  if (!append) {
    $('rows').replaceChildren();
    renderKpis(d);
    renderBars('chart-industry', d.summary.by_industry);
    renderBars('chart-place', d.summary.by_place);
    $('export').href = '/api/export?' + currentParams();
    if (window.refreshMap) window.refreshMap();
  }
  addRows(d.rows);
  state.offset += d.rows.length;
  state.total = d.total;
  $('count').textContent = '(' + d.total + ')';
  $('more').hidden = state.offset >= d.total;
  if (!d.total) $('rows').innerHTML = '<tr><td colspan="7" class="empty">Ничего не найдено</td></tr>';
}

let timer;
for (const id of ['industry', 'place', 'type', 'own']) $(id).addEventListener('change', () => load());
for (const id of ['rev_min', 'rev_max']) $(id).addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => load(), 400); });
$('q').addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => load(), 300); });
$('more').addEventListener('click', () => load(true));
load();
