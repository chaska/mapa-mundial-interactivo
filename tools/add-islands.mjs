// Adds the Canary Islands, the Balearic Islands, Madeira and the Azores to media/data/world.json.
// The world file was drawn from Natural Earth at 1:110m, which leaves those archipelagos out. This takes their polygons from
// Natural Earth 1:10m "map subunits" (public domain) and projects them with the same projection as the rest of the map; each
// archipelago is appended to Spain / Portugal, so albums there belong to their country.
//
//   mkdir tmp && cd tmp && npm i d3-geo
//   curl -O https://raw.githubusercontent.com/nvkelso/natural-earth-vector/master/geojson/ne_10m_admin_0_map_subunits.geojson
//   node /path/to/tools/add-islands.mjs /path/to/world.json ne_10m_admin_0_map_subunits.geojson
import { readFileSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
const require = createRequire(process.cwd() + '/');
const { geoNaturalEarth1, geoPath } = require('d3-geo');
const [file, subunits] = process.argv.slice(2);
const world = JSON.parse(readFileSync(file, 'utf8'));
if (world.islands) { console.log('already added'); process.exit(0); }
const proj = geoNaturalEarth1().scale(world.scale).translate(world.t);
const path = geoPath(proj).digits(2);
// sanity: Madrid, projected here, must fall inside the bounding box of the existing Spain outline (same projection as the file)
const nums = world.countries.find((c) => c.n === 'Spain').d.match(/-?\d+(?:\.\d+)?,-?\d+(?:\.\d+)?/g).map((p) => p.split(',').map(Number));
const [mx, my] = proj([-3.7, 40.4]);
if (mx < Math.min(...nums.map((p) => p[0])) || mx > Math.max(...nums.map((p) => p[0])) || my < Math.min(...nums.map((p) => p[1])) || my > Math.max(...nums.map((p) => p[1]))) { console.error('projection mismatch'); process.exit(1); }
const all = JSON.parse(readFileSync(subunits, 'utf8')).features;
const groups = { Spain: ['Canary Is.', 'Balearic Is.'], Portugal: ['Madeira', 'Azores'] };
for (const [name, subs] of Object.entries(groups)) {
	const country = world.countries.find((c) => c.n === name);
	for (const sub of subs) {
		const f = all.find((x) => x.properties.NAME === sub && x.properties.ADMIN === name);
		if (!f) { console.error('missing', sub); process.exit(1); }
		const polys = f.geometry.type === 'MultiPolygon' ? f.geometry.coordinates : [f.geometry.coordinates];
		for (const coords of polys) { country.d += path({ type: 'Polygon', coordinates: coords }); }
		console.log(name, sub, polys.length, 'islands');
	}
}
world.islands = true;
writeFileSync(file, JSON.stringify(world));
