// Builds media/data/places.tsv with SPANISH names from the "Countries States Cities Database" (dr5hn, ODbL 1.0;
// itself built on GeoNames CC BY 4.0, Wikidata CC0 and others), plus the extra names in places.seed.json.
// Usage: node tools/make-places.mjs <json-cities.json> [out.tsv]
//   json-cities.json: https://github.com/dr5hn/countries-states-cities-database/releases/latest/download/json-cities.json.gz
import fs from 'fs';
import { createRequire } from 'module';
const src = process.argv[2]; if (!src) { console.error('falta json-cities.json'); process.exit(1); }
const cities = JSON.parse(fs.readFileSync(src, 'utf8'));
const norm = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
// Only real places people go to: not provinces, counties, sections, ruins.
const KEEP = new Set(['city', 'capital', 'municipality', 'town', 'village', 'locality', 'gov_seat', 'settlement', 'township', 'cities', 'special municipality', 'null', '']);
// Names must survive our own text sanitiser (no backticks, no letter.letter that looks like a domain).
const clean = (n) => String(n).replace(/[\t\r\n<>]/g, ' ').replace(/`/g, "'").replace(/\.(?=\p{L})/gu, '. ').replace(/\s+/g, ' ').trim();
const rows = []; // [key, display name, cc, lat, lon, population, [other keys]]
for (const c of cities) {
  if (!KEEP.has(String(c.type))) continue;
  const lat = parseFloat(c.latitude), lon = parseFloat(c.longitude);
  if (!isFinite(lat) || !isFinite(lon) || !/^[A-Z]{2}$/.test(c.country_code || '')) continue;
  const n = clean((c.translations && c.translations.es) || c.name), k = norm(n);
  if (k.length < 2) continue;
  // The name people may also type: the local one, the English one, the native one when it is written with our letters.
  const others = new Set([norm(clean(c.name)), norm(clean(c.native || ''))]);
  rows.push([k, n, c.country_code, +lat.toFixed(3), +lon.toFixed(3), c.population | 0, others, norm(clean(c.name)), norm(clean(c.native || ''))]);
}
// Spanish names by hand (exonyms the database lacks: Gante, Brujas, Núremberg...): [spanish, local/English name, country]
const exo = JSON.parse(fs.readFileSync(new URL('./exonimos.json', import.meta.url)));
const missing = [];
// Big cities the database stores as a province/state (Shanghai, Buenos Aires, Bogotá...) are looked up in the full
// list, then in GeoNames' own city list (npm "all-the-cities", MIT code, CC BY 4.0 data) when NODE_PATH points to it.
let gn = [];
try { gn = createRequire((process.env.NODE_PATH || '.') + '/x.js')('all-the-cities'); } catch (_) { /* optional */ }
const pool = (cc, lk) => {
  const a = cities.filter((c) => c.country_code === cc && (norm(clean(c.name)) === lk || norm(clean(c.native || '')) === lk || norm(clean((c.translations && c.translations.es) || '')) === lk) && isFinite(parseFloat(c.latitude)))
    .sort((x, y) => (y.population | 0) - (x.population | 0))[0];
  const g = gn.filter((c) => c.country === cc && norm(clean(c.name)) === lk).sort((x, y) => y.population - x.population)[0];
  const A = a && { name: a.name, native: a.native || '', lat: parseFloat(a.latitude), lon: parseFloat(a.longitude), pop: a.population | 0 };
  const G = g && { name: g.name, native: '', lat: g.loc.coordinates[1], lon: g.loc.coordinates[0], pop: g.population };
  return A && G ? (G.pop > A.pop ? G : A) : A || G || null; // namesakes: the real, big one wins
};
for (const [esName, local, cc, extra] of exo) {
  const lk = norm(local);
  let hit = rows.filter((r) => r[2] === cc && (r[7] === lk || r[8] === lk || r[0] === lk || r[0] === norm(esName))).sort((x, y) => y[5] - x[5])[0];
  const p = pool(cc, lk);
  if (hit && p && p.pop > 4 * Math.max(hit[5], 1) && p.pop > 100000) { hit = null; } // the kept row is a small namesake
  if (!hit) {
    if (!p) { missing.push(esName + ' (' + local + ', ' + cc + ')'); continue; }
    hit = [norm(esName), esName, cc, +p.lat.toFixed(3), +p.lon.toFixed(3), p.pop, new Set([norm(clean(p.name)), norm(clean(p.native))]), norm(clean(p.name)), norm(clean(p.native))];
    rows.push(hit);
  }
  hit[1] = clean(esName); hit[0] = norm(esName); hit[5] = Math.max(hit[5], 99000000);
  hit[6].add(norm(clean(local))); for (const x of extra || []) { hit[6].add(norm(clean(x))); }
}
if (missing.length) { console.error('sin encontrar (' + missing.length + '):', missing.join('; ')); }
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
  if (near && near[5] > 50000) { near[0] = k; near[1] = n; near[5] = 99000000; } else { rows.push([k, n, cc, lat, lon, 99000000, new Set(), '', '']); }
}
// one row per (name, country) within ~30 km: keep the most populated
rows.sort((a, b) => (a[0] < b[0] ? -1 : a[0] > b[0] ? 1 : b[5] - a[5]));
const out = [];
for (const r of rows) { let dup = false; for (let i = out.length - 1; i >= 0 && out[i][0] === r[0]; i--) { if (out[i][2] === r[2] && Math.abs(out[i][3] - r[3]) < 0.3 && Math.abs(out[i][4] - r[4]) < 0.3) { dup = true; break; } } if (!dup) { out.push(r); } }
// Extra lines so the local / English / native name finds the same place (shown with the Spanish name).
const lines = out.map((r) => r.slice(0, 6));
let alias = 0;
for (const r of out) for (const k of r[6]) { if (k && k.length >= 2 && k !== r[0]) { lines.push([k, r[1], r[2], r[3], r[4], Math.min(r[5], 98000000)]); alias++; } }
lines.sort((a, b) => (a[0] < b[0] ? -1 : a[0] > b[0] ? 1 : b[5] - a[5]));
fs.writeFileSync(process.argv[3] || 'places.tsv', lines.map((r) => r.join('\t')).join('\n') + '\n');
console.log(out.length, 'lugares,', alias, 'nombres alternativos');
