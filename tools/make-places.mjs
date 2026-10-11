// Builds media/data/places.tsv from GeoNames (npm package "all-the-cities", MIT code, GeoNames data CC BY 4.0)
// plus the Spanish names in places.seed.json. Usage: NODE_PATH=<dir with all-the-cities> node tools/make-places.mjs
import { createRequire } from 'module';
import fs from 'fs';
const require = createRequire(process.env.NODE_PATH ? process.env.NODE_PATH + '/x.js' : import.meta.url);
const cities = require('all-the-cities');
const norm = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
const es = new Intl.DisplayNames(['es'], { type: 'region' });
const codes = {}; for (const a of 'ABCDEFGHIJKLMNOPQRSTUVWXYZ') for (const b of 'ABCDEFGHIJKLMNOPQRSTUVWXYZ') { try { const n = es.of(a + b); if (n && n !== a + b) codes[n] = a + b; } catch (_) {} }
const rows = [];
for (const c of cities) {
  // Names must survive our own text sanitiser (no backticks, no letter.letter that looks like a domain).
  const n = c.name.replace(/[\t\r\n<>]/g, ' ').replace(/`/g, "'").replace(/\.(?=\p{L})/gu, '. ').replace(/\s+/g, ' ').trim(); const k = norm(n); if (!k || k.length < 2) continue;
  rows.push([k, n, c.country, +c.loc.coordinates[1].toFixed(3), +c.loc.coordinates[0].toFixed(3), c.population | 0]);
}
const seed = JSON.parse(fs.readFileSync(new URL('./places.seed.json', import.meta.url)));
for (const [n, country, lat, lon] of seed) {
  const cc = codes[country]; if (!cc) { console.error('sin código para', country); continue; }
  const k = norm(n), same = rows.find((r) => r[0] === k && r[2] === cc && Math.abs(r[3] - lat) < 1 && Math.abs(r[4] - lon) < 1);
  if (same) { same[5] = 99000000; } else { rows.push([k, n, cc, lat, lon, 99000000]); }
}
rows.sort((a, b) => (a[0] < b[0] ? -1 : a[0] > b[0] ? 1 : b[5] - a[5]));
fs.writeFileSync(process.argv[2] || 'places.tsv', rows.map((r) => r.join('\t')).join('\n') + '\n');
console.log(rows.length, 'lugares');
