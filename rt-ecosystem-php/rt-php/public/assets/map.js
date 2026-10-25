// Карта «Радара присутствия»: тепловая плотность + точки двух цветов с кластерами при приближении.
const map = L.map('map', { minZoom: 6, attributionControl: false }).setView([47.6, 40.3], 8);
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '© OpenStreetMap' }).addTo(map);
const heat = L.heatLayer([], { radius: 22, blur: 18, minOpacity: 0.3, gradient: { 0.3: '#C9A8FF', 0.6: '#7700FF', 1: '#FF4F12' } }).addTo(map);
const cluster = L.markerClusterGroup({ disableClusteringAtZoom: 15, maxClusterRadius: 45 });
map.addLayer(cluster);
let seq = 0, mt;

window.refreshMap = async function () {
  const my = ++seq, b = map.getBounds(), z = map.getZoom();
  const p = currentParams({ zoom: z, bbox: [b.getSouth(), b.getWest(), b.getNorth(), b.getEast()].join(',') });
  const r = await fetch('/api/map?' + p, { credentials: 'same-origin' });
  if (!r.ok || my !== seq) return;
  const d = await r.json();
  const max = Math.max(1, ...d.cells.map((c) => c[2]));
  heat.setLatLngs(d.cells.map((c) => [c[0], c[1], Math.min(1, c[2] / max * 1.5)]));
  cluster.clearLayers();
  for (const [la, lo, ours, name, ind, rev, inn] of d.points) {
    const m = L.circleMarker([la, lo], { radius: 7, weight: 1, color: '#fff', fillOpacity: 0.95, fillColor: ours ? '#7700FF' : '#FF4F12' });
    const box = document.createElement('div');
    const t = document.createElement('b'); t.textContent = name;
    box.append(t, document.createElement('br'), (ours ? 'Наш клиент' : 'Не наш') + ' · ' + ind,
      document.createElement('br'), 'ИНН ' + inn + ' · выручка ≈ ' + rev + ' млн ₽ (оценка)');
    m.bindPopup(box);
    cluster.addLayer(m);
  }
  $('map-note').textContent = z < 9 ? 'приблизьте карту, чтобы увидеть компании'
    : (d.points.length >= 2500 ? 'показаны первые 2500 точек: уточните фильтры' : '')
    '';
};
map.on('moveend', () => { clearTimeout(mt); mt = setTimeout(window.refreshMap, 250); });
