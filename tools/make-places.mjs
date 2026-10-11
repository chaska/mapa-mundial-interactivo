// Builds media/data/places.tsv with SPANISH names from the "Countries States Cities Database" (dr5hn, ODbL 1.0;
// itself built on GeoNames CC BY 4.0, Wikidata CC0 and others), plus the extra names in places.seed.json.
// Usage: node tools/make-places.mjs <json-cities.json> [out.tsv]
//   json-cities.json: https://github.com/dr5hn/countries-states-cities-database/releases/latest/download/json-cities.json.gz
import fs from 'fs';
const src = process.argv[2]; if (!src) { console.error('falta json-cities.json'); process.exit(1); }
const cities = JSON.parse(fs.readFileSync(src, 'utf8'));
const norm = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
// Only real places people go to: not provinces, counties, sections, ruins.
const KEEP = new Set(['city', 'capital', 'municipality', 'town', 'village', 'locality', 'gov_seat', 'settlement', 'township', 'cities', 'special municipality', 'null', '']);
// Names must survive our own text sanitiser (no backticks, no letter.letter that looks like a domain).
const clean = (n) => String(n).replace(/[\t\r\n<>]/g, ' ').replace(/`/g, "'").replace(/\.(?=\p{L})/gu, '. ').replace(/\s+/g, ' ').trim();
const rows = [];
for (const c of cities) {
  if (!KEEP.has(String(c.type))) continue;
  const lat = parseFloat(c.latitude), lon = parseFloat(c.longitude);
  if (!isFinite(lat) || !isFinite(lon) || !/^[A-Z]{2}$/.test(c.country_code || '')) continue;
  const n = clean((c.translations && c.translations.es) || c.name), k = norm(n);
  if (k.length < 2) continue;
  rows.push([k, n, c.country_code, +lat.toFixed(3), +lon.toFixed(3), c.population | 0]);
}
// Spanish names the database lacks (it keeps the English one there).
const es = new Intl.DisplayNames(['es'], { type: 'region' });
const codes = {}; for (const a of 'ABCDEFGHIJKLMNOPQRSTUVWXYZ') for (const b of 'ABCDEFGHIJKLMNOPQRSTUVWXYZ') { try { const n = es.of(a + b); if (n && n !== a + b && !['UK', 'EU', 'UN', 'EZ'].includes(a + b)) codes[n] = a + b; } catch (_) {} }
const seed = JSON.parse(fs.readFileSync(new URL('./places.seed.json', import.meta.url)));
for (const [n, country, lat, lon] of seed) {
  const cc = codes[country]; if (!cc) { console.error('sin código para', country); continue; }
  const k = norm(n), r = rows.find((x) => x[0] === k && x[2] === cc && Math.abs(x[3] - lat) < 1 && Math.abs(x[4] - lon) < 1);
  if (r) { r[5] = Math.max(r[5], 99000000); continue; }
  // replace the English-named row of the same place, if any, so it is not offered twice
  const near = rows.filter((x) => x[2] === cc && Math.abs(x[3] - lat) < 0.35 && Math.abs(x[4] - lon) < 0.35).sort((a, b) => b[5] - a[5])[0];
  if (near && near[5] > 50000) { near[0] = k; near[1] = n; near[5] = 99000000; } else { rows.push([k, n, cc, lat, lon, 99000000]); }
}
// one row per (name, country) within ~30 km: keep the most populated
rows.sort((a, b) => (a[0] < b[0] ? -1 : a[0] > b[0] ? 1 : b[5] - a[5]));
const out = [];
for (const r of rows) { const p = out[out.length - 1]; let dup = false; for (let i = out.length - 1; i >= 0 && out[i][0] === r[0]; i--) { if (out[i][2] === r[2] && Math.abs(out[i][3] - r[3]) < 0.3 && Math.abs(out[i][4] - r[4]) < 0.3) { dup = true; break; } } if (!dup) { out.push(r); } }
fs.writeFileSync(process.argv[3] || 'places.tsv', out.map((r) => r.join('\t')).join('\n') + '\n');
console.log(out.length, 'lugares');
