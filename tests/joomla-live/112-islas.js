// The map file must contain the Canary Islands, the Balearic Islands, Madeira and the Azores, as part of Spain / Portugal (so albums there group with their country).
const { chromium } = require('playwright');
const { createRequire } = require('module');
const fs = require('fs'); const path = require('path');
let ok = 0, fail = 0;
const afirma = (c, m) => { c ? (ok++, console.log('  ok    ' + m)) : (fail++, console.log('  FALLA ' + m)); };
const world = JSON.parse(fs.readFileSync(path.join(__dirname, '../../src/component/media/data/world.json'), 'utf8'));
// Natural Earth 1 as the world file was made: scale and translation come from the file itself (formula checked against the mainland below)
const proj = (lo, la) => {
	const r = Math.PI / 180, phi = la * r, lam = lo * r, p2 = phi * phi, p4 = p2 * p2;
	const x = lam * (0.8707 - 0.131979 * p2 + p4 * (-0.013791 + p4 * p2 * p2 * (0.003971 * p2 - 0.001529 * p4)));
	const y = phi * (1.007226 + p2 * (0.015085 + p4 * (-0.044475 + 0.028874 * p2 - 0.005916 * p4)));
	return [world.t[0] + x * world.scale, world.t[1] - y * world.scale];
};
(async () => {
	const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' }).catch(() => chromium.launch());
	const page = await browser.newPage();
	await page.setContent('<svg id="s" width="1000" height="520"></svg>');
	const inside = async (name, lo, la) => {
		const [x, y] = proj(lo, la);
		return page.evaluate(([n, d, x, y]) => { const s = document.getElementById('s'); const p = document.createElementNS('http://www.w3.org/2000/svg', 'path'); p.setAttribute('d', d); s.appendChild(p); const pt = s.createSVGPoint(); pt.x = x; pt.y = y; return p.isPointInFill(pt); }, [name, world.countries.find((c) => c.n === name).d, x, y]);
	};
	afirma(await inside('Spain', -3.7, 40.4), 'control: Madrid cae dentro de España con la formula de proyeccion');
	for (const [name, label, lo, la] of [
		['Spain', 'Tenerife', -16.6, 28.3], ['Spain', 'Gran Canaria', -15.6, 27.95], ['Spain', 'Lanzarote', -13.6, 29.0], ['Spain', 'La Palma', -17.85, 28.7],
		['Spain', 'Fuerteventura', -14.0, 28.4], ['Spain', 'El Hierro', -18.0, 27.75], ['Spain', 'Formentera', 1.45, 38.7], ['Spain', 'Mallorca', 2.95, 39.62], ['Spain', 'Menorca', 4.0, 39.95], ['Spain', 'Ibiza', 1.43, 38.98],
		['Portugal', 'Madeira', -16.95, 32.75], ['Portugal', 'Porto Santo', -16.33, 33.07], ['Portugal', 'San Miguel (Azores)', -25.5, 37.78], ['Portugal', 'Terceira (Azores)', -27.2, 38.72], ['Portugal', 'Pico (Azores)', -28.35, 38.47], ['Portugal', 'Flores (Azores)', -31.2, 39.45], ['Portugal', 'Corvo (Azores)', -31.1, 39.7], ['Portugal', 'Faial (Azores)', -28.72, 38.58], ['Portugal', 'Santa Maria (Azores)', -25.08, 36.97],
	]) { afirma(await inside(name, lo, la), label + ' esta en el mapa como parte de ' + name); }
	afirma(!(await inside('Spain', -16.6, 25.0)), 'el mar entre Canarias y el Sahara sigue siendo mar');
	afirma(world.countries.find((c) => c.n === 'Spain').d.length > 1200 && world.islands === true, 'el archivo marca que las islas ya estan añadidas');
	await browser.close();
	console.log(`\n${ok} correctas, ${fail} fallos`); process.exit(fail ? 1 : 0);
})();
