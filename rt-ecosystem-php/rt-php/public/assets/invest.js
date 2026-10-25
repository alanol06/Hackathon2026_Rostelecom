// Инвестиционный навигатор: фильтры, KPI, графики, таблица.
const $ = (id) => document.getElementById(id);
const fmt = (n) => Math.round(n).toLocaleString('ru-RU');
let ready = false;

function params() {
  const p = new URLSearchParams();
  for (const k of ['stage', 'industry', 'place', 'need', 'q']) if ($(k).value.trim()) p.set(k, $(k).value.trim());
  if ($('key').checked) p.set('key', '1');
  return p;
}
function bars(box, entries) {
  box.replaceChildren();
  const max = Math.max(1, ...entries.map((e) => e[1]));
  for (const [name, v] of entries) {
    const row = document.createElement('div'); row.className = 'bar-row';
    const l = document.createElement('div'); l.className = 'bar-label'; l.textContent = name; l.title = name;
    const t = document.createElement('div'); t.className = 'bar-track';
    const f = document.createElement('div'); f.className = 'bar-fill'; f.style.width = (v / max * 100) + '%'; t.appendChild(f);
    const n = document.createElement('div'); n.className = 'bar-val'; n.textContent = fmt(v);
    row.append(l, t, n); box.appendChild(row);
  }
}
function cell(tr, text, cls) { const td = document.createElement('td'); td.textContent = text; if (cls) td.className = cls; tr.appendChild(td); return td; }

async function load() {
  const r = await fetch('/api/invest?' + params());
  if (r.status === 401) { location = '/login.php'; return; }
  const d = await r.json();
  if (!ready) {
    for (const [id, vals] of [['industry', d.filters.industries], ['place', d.filters.places]])
      for (const v of vals.sort()) $(id).add(new Option(v, v));
    ready = true;
  }
  $('kpis').replaceChildren();
  for (const [v, l] of [[d.total, 'проектов'], [fmt(d.sum), 'млн ₽ инвестиций'], [fmt(d.jobs), 'новых рабочих мест']]) {
    const el = document.createElement('div'); el.className = 'kpi';
    el.innerHTML = '<div class="v"></div><div class="l"></div>'; el.children[0].textContent = v; el.children[1].textContent = l;
    $('kpis').appendChild(el);
  }
  bars($('chart-years'), Object.entries(d.years));
  bars($('chart-ind'), Object.entries(d.by_industry));
  $('count').textContent = '(' + d.total + ')';
  const tb = $('rows'); tb.replaceChildren();
  for (const x of d.rows) {
    const tr = document.createElement('tr');
    cell(tr, x.place); cell(tr, x.initiator + (x.key ? ' ★' : '')); cell(tr, x.title);
    const st = cell(tr, ''); const tag = document.createElement('span'); tag.className = 'tag'; tag.textContent = x.stage; st.appendChild(tag);
    cell(tr, x.needs.join(', ')); cell(tr, x.start + '–' + x.end); cell(tr, fmt(x.sum));
    tb.appendChild(tr);
  }
  if (!d.rows.length) { const tr = document.createElement('tr'); cell(tr, 'Ничего не найдено', 'empty'); tb.appendChild(tr); }
  $('export').href = '/api/invest-export?' + params();
}
let timer;
for (const k of ['stage', 'industry', 'place', 'need', 'key']) $(k).addEventListener('change', load);
$('q').addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(load, 250); });
load();
