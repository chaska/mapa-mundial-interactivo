// Real browser test of the comments button: viewer -> panel -> Engage thread inside the panel -> post a comment -> deep link.
// Usage: MV_COMMENTS=engage NODE_PATH=$(npm root -g) node tests/joomla-live/61-comentarios-navegador.js
const { chromium, devices } = require('playwright');
const { execFileSync } = require('child_process');
const path = require('path');
const BASE = process.env.MV_BASE || 'http://127.0.0.1:8081';
let ok = 0, fail = 0;
const afirma = (c, m) => { c ? (ok++, console.log('  ok    ' + m)) : (fail++, console.log('  FALLA ' + m)); };
(async () => {
  const id = execFileSync('php', [path.join(__dirname, 'lib/semilla.php')], { env: { ...process.env, MV_COMMENTS: 'engage' } }).toString().trim();
  afirma(/^\d+$/.test(id), 'viaje semilla con comentarios (' + id + ')');
  const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' }).catch(() => chromium.launch());
  const errors = [];
  for (const [name, opts] of [['movil', { ...devices['iPhone 13'], locale: 'es-ES' }], ['escritorio', { viewport: { width: 1440, height: 900 }, locale: 'es-ES' }]]) {
    console.log('\n== ' + name + ' ==');
    const ctx = await browser.newContext(opts); const page = await ctx.newPage();
    page.on('pageerror', (e) => errors.push(e.message));
    await page.goto(BASE + '/index.php?option=com_users&view=login');
    await page.fill('#username', 'beto'); await page.fill('#password', process.env.MV_BETO || 'cambia-esta-clave-de-prueba');
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit].btn-primary, .btn-primary[type=submit]')]);
    // deep link: ?trip=ID opens the viewer on that trip
    await page.goto(BASE + '/index.php?option=com_mapaviajes&trip=' + id);
    await page.waitForSelector('#mv-fi:not([hidden])', { timeout: 20000 });
    afirma((await page.textContent('#mv-fiT')).includes('Semilla'), 'el enlace con ?trip= abre la ficha de ese viaje');
    afirma(await page.locator('#mv-fCom:not([hidden])').count() === 1 && await page.locator('#mv-fiCm:not([hidden])').count() === 1, 'la ficha trae el boton de comentarios y la seccion de comentarios debajo de las fotos');
    afirma(await page.locator('#mv-fiDiag[hidden]').count() === 1, 'una persona normal no ve el enlace de diagnostico de administradores');
    await page.click('#mv-fCom');
    const frame = page.frameLocator('#mv-fiFrame');
    await frame.locator('#akengageCommentForm').waitFor({ state: 'attached', timeout: 20000 });
    await page.waitForTimeout(800);
    afirma(!(await frame.locator('#akengageCommentForm').isVisible()), 'el formulario de Engage NO se ve al abrir: se ven los comentarios');
    afirma(await frame.locator('.akengage-title').first().isVisible(), 'y la cabecera del hilo (numero de comentarios) si');
    const hs = await page.evaluate(() => { const f = document.getElementById('mv-fiFrame'); return { frame: f.offsetHeight, body: f.contentDocument.body.offsetHeight }; });
    afirma(hs.frame - hs.body < 60 && hs.frame >= hs.body, 'el marco de comentarios mide lo que mide su contenido, sin hueco en blanco debajo (' + hs.frame + ' / ' + hs.body + ')');
    await page.click('#mv-fiWrite'); await frame.locator('#akengageCommentForm').waitFor({ state: 'visible', timeout: 10000 });
    afirma(true, 'al pulsar «Escribir un comentario…» aparece el formulario de Engage');
    afirma(true, 'la ficha carga el hilo de Engage con su formulario');
    await page.screenshot({ path: `${process.env.MV_OUT || '.'}/${name}-comentarios.png` });
    const text = 'Comentario de prueba ' + name + ' ' + Date.now();
    // Joomla's editor (TinyMCE) lives in a frame of its own inside the panel
    const ed = frame.frameLocator('iframe.tox-edit-area__iframe');
    await ed.locator('body').click({ timeout: 30000 }); await ed.locator('body').pressSequentially(text, { delay: 5 });
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }).catch(() => {}), frame.locator('#akengageCommentForm button[type=submit]').click()]);
    await page.waitForTimeout(2500);
    const f = page.frames().find((x) => x.url().includes('view=tripcomments'));
    afirma(!!f, 'tras comentar, la ficha sigue en la pagina de comentarios del viaje (no en otra)');
    const body = f ? await f.content() : '';
    afirma(body.includes(text) || /moderaci|moderation|pending|revisi/i.test(body), 'el comentario aparece (o queda pendiente de moderacion)');
    await page.keyboard.press('Escape'); await page.waitForTimeout(300);
    afirma(await page.locator('#mv-fi[hidden]').count() === 1 && await page.getAttribute('#mv-fiFrame', 'src') === null, 'Escape cierra la ficha y vacia el marco');
    await ctx.close();
  }
  afirma(errors.length === 0, 'sin errores de JavaScript' + (errors.length ? ': ' + errors[0] : ''));
  console.log(`\n${ok} correctas, ${fail} fallos`);
  await browser.close(); process.exit(fail ? 1 : 0);
})();
