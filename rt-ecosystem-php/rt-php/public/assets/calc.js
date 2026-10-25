// Калькулятор сделок: форма -> /api/calc -> вилка цены, состав, КП.
const $ = (id) => document.getElementById(id);
const rub = (n) => Math.round(n).toLocaleString('ru-RU') + ' ₽';
const state = { items: {}, stages: new Set() };

function payload() {
  return { customer: $('customer').value, region: $('region').value, level: $('level').value, urgency: $('urgency').value,
    unique: $('unique').checked, margin: Number($('margin').value) || 0, items: state.items, stages: [...state.stages] };
}
async function recalc() {
  const body = payload();
  const r = await fetch('/api/calc', { method: 'POST', body: JSON.stringify(body) });
  if (r.status === 401) { location = '/login.php'; return; }
  const q = await r.json();
  $('payload').value = JSON.stringify(body);
  $('kpis').replaceChildren();
  for (const [v, l] of [[q.min, 'минимум'], [q.opt, 'оптимум'], [q.max, 'верхняя граница']]) {
    const el = document.createElement('div'); el.className = 'kpi';
    el.innerHTML = '<div class="v"></div><div class="l"></div>'; el.children[0].textContent = rub(v); el.children[1].textContent = l;
    $('kpis').appendChild(el);
  }
  const parts = [['Оборудование', q.equipment], ['Работы', q.works_sum], ['Коэффициенты', q.cost - q.equipment - q.works_sum], ['Маржа', q.opt - q.cost]];
  const max = Math.max(1, ...parts.map((p) => Math.abs(p[1])));
  $('chart').replaceChildren();
  for (const [n, v] of parts) {
    const row = document.createElement('div'); row.className = 'bar-row';
    row.innerHTML = '<div class="bar-label"></div><div class="bar-track"><div class="bar-fill"></div></div><div class="bar-val"></div>';
    row.children[0].textContent = n; row.children[1].firstChild.style.width = (Math.abs(v) / max * 100) + '%'; row.children[2].textContent = rub(v);
    row.style.gridTemplateColumns = '120px 1fr 120px'; $('chart').appendChild(row);
  }
  $('rows').replaceChildren();
  for (const l of [...q.lines.map((x) => [x.group + ': ' + x.name + ' × ' + x.qty, x.sum]), ...q.works.map((x) => [x.name, x.sum])]) {
    const tr = document.createElement('tr');
    for (const t of l) { const td = document.createElement('td'); td.textContent = typeof t === 'number' ? rub(t) : t; tr.appendChild(td); }
    $('rows').appendChild(tr);
  }
  if (!$('rows').children.length) $('rows').innerHTML = '<tr><td class="empty">Выберите оборудование слева</td></tr>';
}
(async () => {
  const d = await (await fetch('/api/calc-meta')).json();
  for (const x of d.regions) $('region').add(new Option(x.name + ' (×' + x.k + ')', x.name));
  for (const x of d.levels) $('level').add(new Option(x.name + ' (×' + x.k + ')', x.name));
  for (const s of d.stages) {
    const l = document.createElement('label'); l.className = 'chk';
    const c = document.createElement('input'); c.type = 'checkbox'; c.checked = true; state.stages.add(s.name);
    c.onchange = () => { c.checked ? state.stages.add(s.name) : state.stages.delete(s.name); recalc(); };
    l.append(c, ' ' + s.name + ' — ' + Math.round(s.pct * 100) + '% от оборудования'); $('stages').appendChild(l);
  }
  let g = '';
  for (const it of d.items) {
    if (it.group !== g) { g = it.group; const h = document.createElement('div'); h.className = 'grp'; h.textContent = g; $('items').appendChild(h); }
    const row = document.createElement('label'); row.className = 'item';
    const n = document.createElement('input'); n.type = 'number'; n.min = 0; n.value = 0;
    n.oninput = () => { Number(n.value) > 0 ? state.items[it.id] = Number(n.value) : delete state.items[it.id]; recalc(); };
    const t = document.createElement('span'); t.textContent = it.name + ' · ' + rub(it.price);
    row.append(n, t); $('items').appendChild(row);
  }
  for (const k of ['region', 'level', 'urgency', 'unique', 'margin']) $(k).addEventListener('input', recalc);
  recalc();
})();
