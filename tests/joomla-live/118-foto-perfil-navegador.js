// Browser test of profile photos in the map: the server answers are intercepted and given "av" (MiFoto is not installed on the test site).
// Usage: NODE_PATH=$(npm root -g) node tests/joomla-live/118-foto-perfil-navegador.js
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const path = require('path');
const BASE = process.env.MV_BASE || 'http://127.0.0.1:8081';
let ok = 0, fail = 0;
const afirma = (c, m) => { c ? (ok++, console.log('  ok    ' + m)) : (fail++, console.log('  FALLA ' + m)); };
// a 2x2 PNG
const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAFElEQVR4nGP8z8Dwn4EIwESMolGF/wEAU2kBBJkYxk0AAAAASUVORK5CYII=', 'base64');
(async () => {
  execFileSync('php', [path.join(__dirname, 'lib/semilla-podio.php')]);
  const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' }).catch(() => chromium.launch());
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'es-ES' }); const page = await ctx.newPage();
  const errors = []; const external = [];
  page.on('pageerror', (e) => errors.push(e.message));
  page.on('request', (r) => { const u = r.url(); if (!u.startsWith(BASE) && !u.startsWith('data:') && !u.startsWith('blob:')) { external.push(u); } });
  let hits = 0;
  await ctx.route('**/mv-avatar-test/**', (route) => { hits++; route.fulfill({ status: 200, contentType: 'image/png', body: PNG }); });
  await ctx.route('**/index.php?option=com_mapaviajes&task=ranking.data*', async (route) => {
    const res = await route.fetch(); const j = await res.json();
    const put = (rows, avs) => (rows || []).forEach((r) => { if (avs[r.n] !== undefined) { r.av = avs[r.n]; } });
    const A = { pd_alba: '/mv-avatar-test/alba.png', pd_bruno: 'https://evil.example/x.png', ana: '/mv-avatar-test/ana.png' };
    put(j.week.rows, A); put(j.last.rows, A); put(j.general.rows, A);
    route.fulfill({ response: res, json: j });
  });
  await ctx.route('**/index.php?option=com_mapaviajes&task=trips.data*', async (route) => {
    const res = await route.fetch(); const j = await res.json();
    const t = (j.trips || [])[0]; j.av = {}; if (t) { j.av[t.u] = '/mv-avatar-test/autor.png'; }
    j.friend = { friends: [{ k: 'aaaaaaaaaaaa', n: 'Amiga Con Foto', av: '/mv-avatar-test/amiga.png' }, { k: 'bbbbbbbbbbbb', n: 'Amigo Sin Foto', av: '' }], incoming: [], outgoing: [], blocked: [] };
    route.fulfill({ response: res, json: j });
  });
  await page.goto(BASE + '/index.php?option=com_users&view=login');
  await page.fill('#username', 'beto'); await page.fill('#password', process.env.MV_BETO || 'cambia-esta-clave-de-prueba');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit].btn-primary, .btn-primary[type=submit]')]);
  await page.goto(BASE + '/index.php?option=com_mapaviajes');
  await page.waitForSelector('#mv-svg path.mv-sphere[d]', { timeout: 20000 });
  await page.click('#mv-btnMenu'); await page.click('#mv-btnPod'); await page.waitForSelector('#mv-pod:not([hidden]) .mv-ptitle', { timeout: 15000 });
  await page.click('#mv-pod [data-v=last]'); await page.waitForTimeout(800);
  const slots = await page.evaluate(() => [...document.querySelectorAll('.mv-pslot')].map((s) => ({ n: s.querySelector('.mv-pname') && s.querySelector('.mv-pname').textContent, img: !!s.querySelector('img.mv-pav'), ini: !!s.querySelector('span.mv-pav:not(.mv-pnone)') })));
  const by = (n) => slots.find((s) => s.n === n) || {};
  afirma(by('pd_alba').img === true, 'podio: quien tiene foto (alba) la enseña en el circulo');
  afirma(by('pd_bruno').img === false && by('pd_bruno').ini === true, 'podio: una direccion externa se ignora y queda la inicial (bruno)');
  afirma(by('ana').img === true, 'podio: la tercera con foto (ana) tambien');
  afirma(await page.evaluate(() => { const i = document.querySelector('.mv-pslot img.mv-pav'); return !!i && i.complete && i.naturalWidth > 0 && i.getBoundingClientRect().width >= 50; }), 'la foto se carga de verdad y tiene el tamano del circulo');
  afirma(await page.evaluate(() => [...document.querySelectorAll('.mv-pslot img.mv-pav')].every((i) => i.getAttribute('alt') === '' && i.getAttribute('referrerpolicy') === 'no-referrer')), 'imagen decorativa (alt vacio) y sin referer');
  afirma(!(await page.evaluate(() => [...document.querySelectorAll('.mv-pslot img')].some((i) => /evil\.example/.test(i.src)))), 'ninguna imagen apunta a otro dominio');
  await page.screenshot({ path: `${process.env.MV_OUT || '.'}/escritorio-podio-fotos.png` });
  await page.click('#mv-pod [data-v=all]'); await page.waitForTimeout(500);
  afirma(await page.locator('.mv-pslot img.mv-pav').count() >= 1, 'general: tambien salen las fotos');
  await page.keyboard.press('Escape'); await page.waitForTimeout(300);
  if (await page.locator('#mv-pod:not([hidden])').count()) { await page.click('#mv-pod .mv-pclose, #mv-podX, #mv-pod [data-close]').catch(() => {}); }
  // friends list
  await page.evaluate(() => { const b = document.querySelector('#mv-btnMenu'); if (b) { b.click(); } });
  const friendsBtn = page.locator('#mv-menu button', { hasText: /amigos|friends/i }).first();
  if (await friendsBtn.count()) { await friendsBtn.click(); await page.waitForTimeout(600); }
  const fr = await page.evaluate(() => [...document.querySelectorAll('.mv-fr-li')].map((l) => ({ t: l.textContent, img: !!l.querySelector('img.mv-fr-av'), ini: !!l.querySelector('span.mv-fr-av') })));
  const con = fr.find((x) => /Amiga Con Foto/.test(x.t)) || {}; const sin = fr.find((x) => /Amigo Sin Foto/.test(x.t)) || {};
  afirma(con.img === true, 'amigos: quien tiene foto la enseña');
  afirma(sin.ini === true && sin.img === false, 'amigos: quien no tiene foto lleva su inicial');
  if (await page.locator('.mv-fr-li').count()) { await page.screenshot({ path: `${process.env.MV_OUT || '.'}/escritorio-amigos-fotos.png` }); }
  afirma(hits > 0, 'el navegador pidio las fotos a nuestro propio sitio (' + hits + ' peticiones)');
  afirma(external.length === 0, 'ninguna peticion a otro dominio' + (external.length ? ': ' + external.slice(0, 3).join(', ') : ''));
  afirma(errors.length === 0, 'sin errores de JavaScript' + (errors.length ? ': ' + errors[0] : ''));
  // the author in the viewer of a trip
  await page.reload(); await page.waitForSelector('#mv-svg path.mv-sphere[d]', { timeout: 20000 });
  await ctx.unroute('**/mv-avatar-test/**'); await ctx.route('**/mv-avatar-test/**', (route) => route.fulfill({ status: 200, contentType: 'image/png', body: PNG }));
  await page.reload(); await page.waitForSelector('#mv-svg path.mv-sphere[d]', { timeout: 20000 });
  await page.click('#mv-btnMenu'); await page.click('#mv-btnDisc'); await page.waitForSelector('#mv-drawer.mv-open .mv-arow-main', { timeout: 10000 });
  await page.click('#mv-drawer .mv-arow-main >> nth=0'); await page.waitForSelector('#mv-fi:not([hidden]), #mv-viewer:not([hidden])', { timeout: 10000 }).catch(() => {});
  afirma(await page.locator('#mv-fiS img.mv-fi-av').count() === 1, 'ficha del viaje: el autor lleva su foto junto al nombre');
  // a photo that fails falls back to the initial
  await ctx.unroute('**/mv-avatar-test/**'); await ctx.route('**/mv-avatar-test/**', (route) => route.fulfill({ status: 404, body: '' }));
  await page.reload(); await page.waitForSelector('#mv-svg path.mv-sphere[d]', { timeout: 20000 });
  await page.click('#mv-btnMenu'); await page.click('#mv-btnPod'); await page.waitForSelector('#mv-pod:not([hidden]) .mv-ptitle', { timeout: 15000 });
  await page.click('#mv-pod [data-v=last]'); await page.waitForTimeout(1200);
  afirma(await page.locator('.mv-pslot img.mv-pav').count() === 0 && await page.locator('.mv-pslot span.mv-pav:not(.mv-pnone)').count() === 3, 'si la foto falla (404) vuelve la inicial en los tres puestos');
  await browser.close();
  console.log('\n' + ok + ' correctas, ' + fail + ' fallos'); process.exit(fail ? 1 : 0);
})();
