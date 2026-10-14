// Real browser test of the weekly podium and of "show only one person's trips".
// Usage: NODE_PATH=$(npm root -g) node tests/joomla-live/80-podio-navegador.js
const { chromium, devices } = require('playwright');
const { execFileSync } = require('child_process');
const path = require('path');
const BASE = process.env.MV_BASE || 'http://127.0.0.1:8081';
let ok = 0, fail = 0;
const afirma = (c, m) => { c ? (ok++, console.log('  ok    ' + m)) : (fail++, console.log('  FALLA ' + m)); };
(async () => {
  execFileSync('php', [path.join(__dirname, 'lib/semilla-podio.php')]);
  const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' }).catch(() => chromium.launch());
  const errors = [];
  for (const [name, opts] of [['movil', { ...devices['iPhone 13'], locale: 'es-ES' }], ['escritorio', { viewport: { width: 1440, height: 900 }, locale: 'es-ES' }]]) {
    console.log('\n== ' + name + ' ==');
    const ctx = await browser.newContext(opts); const page = await ctx.newPage();
    page.on('pageerror', (e) => errors.push(e.message));
    await page.goto(BASE + '/index.php?option=com_users&view=login');
    await page.fill('#username', 'beto'); await page.fill('#password', process.env.MV_BETO || 'cambia-esta-clave-de-prueba');
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit].btn-primary, .btn-primary[type=submit]')]);
    await page.goto(BASE + '/index.php?option=com_mapaviajes');
    await page.waitForSelector('#mv-svg path.mv-sphere[d]', { timeout: 20000 });
    await page.click('#mv-btnMenu'); await page.click('#mv-btnPod'); await page.waitForSelector('#mv-pod:not([hidden]) .mv-ptitle', { timeout: 15000 });
    afirma(/podio de la semana|week's podium/i.test(await page.textContent('.mv-ptitle')), 'el podio de esta semana se abre');
    afirma(await page.locator('.mv-pslot').count() === 3, 'tres puestos');
    afirma(await page.locator('.mv-pslot.mv-p1 .mv-pstar').count() === 3 && await page.locator('.mv-pslot.mv-p2 .mv-pstar').count() === 2 && await page.locator('.mv-pslot.mv-p3.mv-pnoone').count() === 1, 'esta semana solo hay dos personas: 3 y 2 estrellas, y el tercer puesto vacio');
    afirma(await page.evaluate(() => [...document.querySelectorAll('.mv-pslot')].every((sl) => Math.abs(sl.querySelector('.mv-pblock').getBoundingClientRect().bottom - sl.getBoundingClientRect().bottom) < 1)), 'los tres escalones apoyan en el suelo (ninguno flota, tambien el puesto vacio)');
    afirma(/Nº|nº|number/.test(await page.textContent('.mv-pmine')) || /Todavía|No points/.test(await page.textContent('.mv-pmine')), 'dice en que puesto vas');
    await page.screenshot({ path: `${process.env.MV_OUT || '.'}/${name}-podio-semana.png` });
    await page.click('#mv-pod [data-v=last]'); await page.waitForTimeout(300);
    const names = await page.locator('.mv-pslot .mv-pname').allTextContents();
    afirma(names[1] === 'pd_alba' && names[0] === 'pd_bruno' && names[2] === 'ana', 'semana pasada (cerrada al vuelo): alba 70, bruno 35, ana 29; carla (26) y beto (23) fuera del podio (' + names.join(',') + ')');
    afirma((await page.locator('.mv-prest .mv-pname').allTextContents()).join() === 'pd_carla,beto', 'del 4 al 10: carla y beto');
    afirma(await page.locator('.mv-pslot.mv-p3 .mv-pstar').count() === 1 && await page.locator('.mv-pslot.mv-p1 .mv-pstar').count() === 3, 'estrellas 3, 2 y 1 con tres personas');
    await page.screenshot({ path: `${process.env.MV_OUT || '.'}/${name}-podio-pasada.png` });
    await page.click('#mv-pod [data-v=all]'); await page.waitForTimeout(300);
    afirma((await page.locator('.mv-pslot .mv-pname').allTextContents())[1] === 'pd_alba', 'general: alba lidera');
    afirma(await page.locator('.mv-phide').count() === 0, 'ya no hay interruptor «No aparecer en el ranking»: aparece todo el mundo');
    afirma(/20/.test(await page.locator('.mv-phow').textContent()) && await page.locator('.mv-phow[open]').count() === 1, '«Cómo se puntúa» está abierto y trae las puntuaciones');
    // filter by one person
    await page.click('#mv-pod [data-v=last]'); await page.waitForTimeout(200);
    await page.locator('.mv-pslot .mv-pname', { hasText: 'pd_alba' }).click(); await page.waitForTimeout(900);
    afirma(await page.locator('#mv-pod[hidden]').count() === 1 && await page.locator('#mv-chip:not([hidden])').count() === 1, 'pulsar un nombre cierra el podio y pone el filtro «Viajes de …»');
    afirma(/pd_alba/.test(await page.textContent('#mv-chip')), 'el filtro dice de quien son');
    await page.click('#mv-btnMenu'); await page.click('#mv-btnDisc'); await page.waitForSelector('#mv-dBody .mv-arow-main');
    const rows = await page.locator('#mv-dBody .mv-arow-main small:nth-of-type(1)').allTextContents();
    afirma(rows.length > 0 && rows.every((r) => r.includes('pd_alba')), 'el cajon solo lista viajes de esa persona (' + rows.length + ')');
    await page.click('#mv-dClose'); await page.click('#mv-chipX'); await page.waitForTimeout(400);
    afirma(await page.locator('#mv-chip[hidden]').count() === 1, 'pulsar el filtro lo quita');
    await ctx.close();
  }
  afirma(errors.length === 0, 'sin errores de JavaScript' + (errors.length ? ': ' + errors[0] : ''));
  console.log(`\n${ok} correctas, ${fail} fallos`);
  await browser.close(); process.exit(fail ? 1 : 0);
})();
