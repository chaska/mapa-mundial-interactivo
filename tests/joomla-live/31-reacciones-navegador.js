// Real browser test of reactions: heart => random kind message, star => "I'm your fan!", favourites tab.
// Usage: NODE_PATH=$(npm root -g) node tests/joomla-live/31-reacciones-navegador.js
const { chromium, devices } = require('playwright');
const { execFileSync } = require('child_process');
const path = require('path');
const BASE = process.env.MV_BASE || 'http://127.0.0.1:8081';
let ok = 0, fail = 0;
const afirma = (c, m) => { c ? (ok++, console.log('  ok    ' + m)) : (fail++, console.log('  FALLA ' + m)); };
(async () => {
  const id = execFileSync('php', [path.join(__dirname, 'lib/semilla.php')]).toString().trim();
  afirma(/^\d+$/.test(id), 'viaje semilla de ana publicado (' + id + ')');
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
    await page.waitForSelector('#mv-svg path.mv-sphere[d]', { timeout: 15000 });
    await page.click('#mv-btnDisc'); await page.waitForSelector('#mv-dBody .mv-arow-main');
    // a previous run of this test may have left beto's marks: start clean
    await page.locator('#mv-dBody .mv-arow-main', { hasText: 'Semilla para reacciones' }).first().click();
    await page.waitForSelector('#mv-fi:not([hidden])');
    for (const [btn, key] of [['#mv-fLike', 'like'], ['#mv-fFan', 'fan']]) {
      if (await page.getAttribute(btn, 'aria-pressed') === 'true') { await page.click(btn); await page.waitForTimeout(600); }
    }
    afirma(await page.getAttribute('#mv-fLike', 'aria-pressed') === 'false', 'corazon apagado al empezar');
    const likes = ['¡Me encanta!', '¡Me gusta!'];
    const seen = new Set();
    await page.click('#mv-fLike'); await page.waitForTimeout(500);
    afirma(await page.getAttribute('#mv-fLike', 'aria-pressed') === 'true', 'el corazon queda marcado');
    const m1 = (await page.textContent('#mv-fMsg')).trim();
    afirma(m1.length > 2 && await page.locator('#mv-fMsg.mv-show').count() === 1, 'sale un mensaje debajo: "' + m1 + '"');
    await page.screenshot({ path: `${process.env.MV_OUT || '.'}/${name}-reaccion-corazon.png` });
    seen.add(m1);
    for (let i = 0; i < 14; i++) { await page.click('#mv-fLike'); await page.waitForTimeout(220); if (await page.getAttribute('#mv-fLike', 'aria-pressed') === 'true') { seen.add((await page.textContent('#mv-fMsg')).trim()); } }
    afirma(seen.size >= 3, 'los mensajes cambian al azar (distintos vistos: ' + seen.size + ')');
    if (await page.getAttribute('#mv-fLike', 'aria-pressed') !== 'true') { await page.click('#mv-fLike'); await page.waitForTimeout(500); }
    await page.click('#mv-fFan'); await page.waitForTimeout(500);
    afirma(await page.getAttribute('#mv-fFan', 'aria-pressed') === 'true', 'la estrella queda marcada');
    afirma((await page.textContent('#mv-fMsg')).trim() .match(/^(¡Soy tu fan!|I.m your fan!)$/) !== null, 'la estrella dice "¡Soy tu fan!"');
    await page.screenshot({ path: `${process.env.MV_OUT || '.'}/${name}-reaccion-estrella.png` });
    // survives a reload (server-authoritative) and shows in the favourites tab
    await page.reload(); await page.waitForSelector('#mv-svg path.mv-sphere[d]');
    await page.click('#mv-btnDisc'); await page.waitForSelector('#mv-dBody .mv-arow-main');
    await page.click('#mv-tab-fav'); await page.waitForTimeout(300);
    afirma(await page.locator('#mv-dBody .mv-arow-main', { hasText: 'Semilla para reacciones' }).count() === 1, 'pestaña Favoritos lista el viaje tras recargar');
    afirma((await page.textContent('#mv-favN')).trim() === '1', 'el contador de favoritos marca 1');
    await ctx.close();
  }
  afirma(errors.length === 0, 'sin errores de JavaScript' + (errors.length ? ': ' + errors[0] : ''));
  console.log(`\n${ok} correctas, ${fail} fallos`);
  await browser.close(); process.exit(fail ? 1 : 0);
})();
