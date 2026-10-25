// Real browser test: a HEIC photo (iPhone / some Xiaomi) is read in the browser by the program that travels with the component,
// previewed, sent as an ordinary picture, and stored as WebP; nothing is fetched from the internet and the server never opens a HEIC.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const fs = require('fs'); const path = require('path');
const BASE = process.env.MV_BASE || 'http://127.0.0.1:8081';
let ok = 0, fail = 0;
const afirma = (c, m) => { c ? (ok++, console.log('  ok    ' + m)) : (fail++, console.log('  FALLA ' + m)); };
const psql = (sql) => execSync(process.env.MV_PSQL + ' -Atc "' + sql + '"').toString().trim();
const setParams = (o) => psql("UPDATE jt_extensions SET params=(params::jsonb || '" + JSON.stringify(o).replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/'/g, "''") + "'::jsonb)::text WHERE element='com_mapaviajes' AND type='component'");
(async () => {
  if (!process.env.MV_PSQL || !process.env.MV_STORAGE) { console.log('Faltan MV_PSQL / MV_STORAGE'); process.exit(2); }
  setParams({ trips_per_hour: 1000, trips_per_day: 1000, review_first: 0 });
  const heic = path.join(__dirname, 'fixtures', 'prueba.heic');
  const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' }).catch(() => chromium.launch());
  const p = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
  const errs = [], urls = []; p.on('pageerror', (e) => errs.push(String(e))); p.on('request', (r) => urls.push(r.url()));
  await p.addInitScript(() => { const o = XMLHttpRequest.prototype.send; XMLHttpRequest.prototype.send = function (b) { if (b instanceof FormData) { window.__sent = [...b.entries()].filter(([, v]) => v instanceof Blob).map(([k, v]) => [k, v.name, v.type]); } return o.call(this, b); }; });
  await p.goto(BASE + '/index.php?option=com_users&view=login'); await p.fill('#username', 'ana'); await p.fill('#password', process.env.MV_ANA || 'cambia-esta-clave-de-prueba');
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit].btn-primary, .btn-primary[type=submit]')]);
  await p.goto(BASE + '/index.php?option=com_mapaviajes'); await p.waitForSelector('#mv-svg path.mv-sphere[d]');
  afirma(!urls.some((u) => u.includes('libheif')), 'el decodificador NO se descarga mientras nadie elige una foto HEIC');
  await p.click('#mv-btnAdd'); await p.waitForTimeout(600);
  afirma((await p.getAttribute('#mv-files', 'accept')).includes('.heic'), 'el selector de archivos admite .heic y .heif');
  await p.fill('#mv-q', '40.41, -3.70'); await p.waitForTimeout(500); await p.keyboard.press('Enter'); await p.waitForTimeout(500);
  await p.selectOption('#mv-year', '2024'); await p.fill('#mv-title', 'Prueba HEIC');
  await p.setInputFiles('#mv-files', heic);
  await p.waitForFunction(() => { const i = document.querySelector('#mv-photos .mv-photo img'); return i && i.complete && i.naturalWidth > 0; }, null, { timeout: 30000 });
  await p.setInputFiles('#mv-files', path.join(__dirname, 'fixtures', 'grande.jpg')); // a big ordinary JPEG goes through the normal path too
  const dims = await p.evaluate(() => { const i = document.querySelector('#mv-photos .mv-photo img'); return [i.naturalWidth, i.naturalHeight, i.src.slice(0, 5)]; });
  afirma(urls.some((u) => u.includes('libheif-bundle.js')) && dims[2] === 'blob:', 'al elegir el HEIC se carga el decodificador del propio sitio y sale la vista previa');
  afirma(Math.max(dims[0], dims[1]) === 1800 && Math.min(dims[0], dims[1]) === 1200, 'la foto de 3000×2000 se reduce a 1800×1200 como cualquier otra (' + dims[0] + '×' + dims[1] + ')');
  afirma(await p.locator('#mv-photos .mv-photo').count() === 2 && (await p.locator('#mv-perr').textContent()) === '', 'dos tarjetas (el HEIC y un JPEG grande) y sin errores');
  await p.check('#mv-rights'); const before = Number(psql("SELECT COUNT(*) FROM jt_mapaviajes_trips WHERE user_id=(SELECT id FROM jt_users WHERE username='ana')"));
  const resp = p.waitForResponse((r) => r.url().includes('trip.submit'), { timeout: 30000 });
  await p.click('#mv-btnSend'); const r = await resp;
  afirma(r.status() === 201, 'el envio sale bien (201)');
  const sent = await p.evaluate(() => window.__sent || []);
  afirma(sent.length === 2 && sent.every(([, n, t]) => t === 'image/webp' && /\.webp$/.test(n)), 'las dos fotos viajan como WebP (en este navegador): el HEIC ya convertido y el JPEG grande reducido; el HEIC original nunca sale del movil: ' + JSON.stringify(sent.map((x) => x[2])));
  const row = psql("SELECT p.filename || '|' || p.width || '|' || p.height FROM jt_mapaviajes_photos p JOIN jt_mapaviajes_trips t ON t.id=p.trip_id WHERE t.user_id=(SELECT id FROM jt_users WHERE username='ana') ORDER BY p.id DESC LIMIT 1").split('|');
  const bytes = fs.readFileSync(process.env.MV_STORAGE + '/photos/' + row[0]);
  afirma(Number(psql("SELECT COUNT(*) FROM jt_mapaviajes_photos WHERE trip_id=(SELECT MAX(id) FROM jt_mapaviajes_trips WHERE user_id=(SELECT id FROM jt_users WHERE username='ana'))")) === 2, 'el viaje nuevo tiene sus 2 fotos');
  afirma(Number(psql("SELECT COUNT(*) FROM jt_mapaviajes_trips WHERE user_id=(SELECT id FROM jt_users WHERE username='ana')")) === before + 1, 'queda un viaje nuevo');
  afirma(bytes.slice(0, 4).toString() === 'RIFF' && bytes.slice(8, 12).toString() === 'WEBP' && !bytes.includes(Buffer.from('Exif')), 'en el disco es un WebP de verdad y sin datos EXIF');
  afirma(urls.every((u) => u.startsWith(BASE) || u.startsWith('blob:') || u.startsWith('data:')), 'ninguna peticion salio a internet');
  afirma(errs.length === 0, 'sin errores de JavaScript ' + JSON.stringify(errs));
  await browser.close();
  setParams({ review_first: 1 });
  console.log(`\n${ok} correctas, ${fail} fallos`); process.exit(fail ? 1 : 0);
})();
