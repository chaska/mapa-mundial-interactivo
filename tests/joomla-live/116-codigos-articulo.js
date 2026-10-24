// The article codes {mapaviajes stats|albums|countries} give the same totals the map header gives (countries counted with the SAME outlines).
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const fs = require('fs'); const path = require('path');
const BASE = process.env.MV_BASE || 'http://127.0.0.1:8081';
let ok = 0, fail = 0;
const afirma = (c, m) => { c ? (ok++, console.log('  ok    ' + m)) : (fail++, console.log('  FALLA ' + m)); };
const psql = (sql) => execSync(process.env.MV_PSQL + ' -Atc "' + sql.replace(/"/g, '\\"') + '"').toString().trim();
const world = JSON.parse(fs.readFileSync(path.join(__dirname, '../../src/component/media/data/world.json'), 'utf8'));
const rawNE = (l, p) => { const p2 = p * p, p4 = p2 * p2; return [l * (0.8707 - 0.131979 * p2 + p4 * (-0.013791 + p4 * (0.003971 * p2 - 0.001529 * p4))), p * (1.007226 + p2 * (0.015085 + p4 * (-0.044475 + 0.028874 * p2 - 0.005916 * p4)))]; };
const project = (lon, lat) => { const [a, b] = rawNE(lon * Math.PI / 180, lat * Math.PI / 180); return [world.t[0] + world.scale * a, world.t[1] - world.scale * b]; };
(async () => {
  if (!process.env.MV_PSQL) { console.log('Falta MV_PSQL'); process.exit(2); }
  psql("DELETE FROM jt_content WHERE alias='mv-codigos-test'");
  const body = 'A:{mapaviajes albums}|C:{mapaviajes countries}|S:{mapaviajes stats}|P:{mapaviajes photos}|N:{mapaviajes albums album=999999}|X:{mapaviajes nada}';
  psql(`INSERT INTO jt_content (title, alias, introtext, "fulltext", state, catid, created, modified, created_by, access, language, images, urls, attribs, metakey, metadesc, metadata, version, ordering, featured) VALUES ('Codigos', 'mv-codigos-test', '${body}', '', 1, 2, now(), now(), 0, 1, '*', '{}', '{}', '{}', '', '', '{}', 1, 0, 0)`);
  const id = psql("SELECT id FROM jt_content WHERE alias='mv-codigos-test'");
  const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' }).catch(() => chromium.launch());
  const page = await (await browser.newContext()).newPage();
  await page.goto(BASE + '/index.php?option=com_content&view=article&id=' + id + '&catid=2');
  const txt = (await page.locator('body').innerText());
  const m = txt.match(/A:(\d+)\|C:(\d+)\|S:([^|]+)\|P:(\d+)\|N:(\d+)\|X:(\S*)/);
  afirma(!!m, 'el articulo (visto por un invitado, sin sesion) trae los totales: ' + (m ? m[0].slice(0, 80) : txt.slice(0, 120)));
  const rows = psql("SELECT t.lat || ',' || t.lon FROM jt_mapaviajes_trips t JOIN jt_mapaviajes_albums a ON a.id=t.album_id WHERE t.published=1 AND a.published=1").split('\n').filter(Boolean).map((r) => r.split(',').map(Number));
  // the browser's own country test (the one the map uses), for every published trip
  const svgPage = await (await browser.newContext()).newPage();
  await svgPage.setContent('<svg id="s" width="1000" height="520"></svg>');
  const keys = await svgPage.evaluate(([countries, pts]) => {
    const s = document.getElementById('s'); const paths = countries.map((c) => { const p = document.createElementNS('http://www.w3.org/2000/svg', 'path'); p.setAttribute('d', c.d); s.appendChild(p); return [c.n, p]; });
    const probe = s.createSVGPoint(); const out = new Set();
    for (const [x, y] of pts) { probe.x = x; probe.y = y; let k = null; for (const [n, p] of paths) { if (p.isPointInFill(probe)) { k = 'c:' + n; break; } } out.add(k || 'q:' + Math.round(x / 25) + ',' + Math.round(y / 25)); }
    return [...out].length;
  }, [world.countries, rows.map(([la, lo]) => project(lo, la))]);
  if (m) {
    afirma(Number(m[1]) === rows.length, 'albumes = viajes publicados de mapas publicados (' + m[1] + ' = ' + rows.length + ')');
    afirma(Number(m[2]) === keys, 'paises = los mismos que cuenta el navegador con los mismos contornos (' + m[2] + ' = ' + keys + ')');
    afirma(/\d+ (álbum|album)/.test(m[3]) && m[3].includes(m[1]) && m[3].includes(m[2]), 'stats escribe «N albumes · N paises» con los dos numeros: «' + m[3].trim() + '»');
    afirma(Number(m[4]) === Number(psql("SELECT COUNT(*) FROM jt_mapaviajes_photos p JOIN jt_mapaviajes_trips t ON t.id=p.trip_id JOIN jt_mapaviajes_albums a ON a.id=t.album_id WHERE t.published=1 AND a.published=1 AND p.state=1")), 'fotografias = fotos aprobadas de viajes publicados (' + m[4] + ')');
    afirma(m[5] === '0', 'con album=ID de un mapa que no existe cuenta 0');
    afirma(m[6] !== undefined, 'un codigo desconocido no rompe el articulo');
  }
  afirma(!(await page.content()).includes('Exception') && txt.includes('X:'), 'el articulo se pinta entero');
  psql("DELETE FROM jt_content WHERE alias='mv-codigos-test'");
  await browser.close();
  console.log(`\n${ok} correctas, ${fail} fallos`); process.exit(fail ? 1 : 0);
})();
