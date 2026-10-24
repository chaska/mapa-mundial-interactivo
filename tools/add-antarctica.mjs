// Adds Antarctica to media/data/world.json (the first version of the file left it out).
// Natural Earth 1:110m (public domain, via world-atlas), same projection and precision as the rest of the map.
//   mkdir tmp && cd tmp && npm i world-atlas@2 topojson-client d3-geo && node /path/to/tools/add-antarctica.mjs /path/to/world.json
import { readFileSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
const require = createRequire(process.cwd() + '/');
const { feature } = require('topojson-client');
const { geoNaturalEarth1, geoPath } = require('d3-geo');
const file = process.argv[2];
const world = JSON.parse(readFileSync(file, 'utf8'));
if (world.countries.some((c) => c.n === 'Antarctica')) { console.log('already there'); process.exit(0); }
const topo = JSON.parse(readFileSync(process.cwd() + '/node_modules/world-atlas/countries-110m.json', 'utf8'));
const f = feature(topo, topo.objects.countries).features.find((x) => x.properties.name === 'Antarctica');
if (!f) { console.error('Antarctica not found'); process.exit(1); }
const proj = geoNaturalEarth1().scale(world.scale).translate(world.t);
const d = geoPath(proj).digits(1)(f);
if (!d || d.length < 200) { console.error('empty path'); process.exit(1); }
world.countries.push({ n: 'Antarctica', d });
writeFileSync(file, JSON.stringify(world));
console.log('Antarctica added', d.length, 'bytes');
