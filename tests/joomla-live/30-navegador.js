// Real browser test (Chromium via Playwright): the add-trip form on a phone and on a desktop, end to end.
// Usage: NODE_PATH=$(npm root -g) MV_OUT=/some/dir node tests/joomla-live/30-navegador.js
const { chromium, devices } = require('playwright');
const fs = require('fs');
const BASE = process.env.MV_BASE || 'http://127.0.0.1:8081';
const OUT = process.env.MV_OUT || '.';
let ok = 0, fail = 0;
const afirma = (c, m) => { c ? (ok++, console.log('  ok    ' + m)) : (fail++, console.log('  FALLA ' + m)); };

function jpeg(w, h) { // a real JPEG made by the browser itself would need a canvas; use a tiny valid JPEG scaled by PHP instead
  return null;
}
(async () => {
  const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' }).catch(() => chromium.launch());
  const errors = [], external = [];
  async function sesion(ctxOpts, name) {
    const ctx = await browser.newContext(ctxOpts);
    const page = await ctx.newPage();
    page.on('pageerror', (e) => errors.push(name + ': ' + e.message));
    page.on('console', (m) => { if (m.type() === 'error' && !/favicon|Failed to load resource.*(404|422)/.test(m.text())) errors.push(name + ' console: ' + m.text()); });
    page.on('request', (r) => { const u = new URL(r.url()); if (u.host !== new URL(BASE).host && !['data:', 'blob:'].includes(u.protocol)) external.push(r.url()); });
    await page.goto(BASE + '/index.php?option=com_users&view=login');
    await page.fill('#username', 'ana'); await page.fill('#password', process.env.MV_ANA || 'cambia-esta-clave-de-prueba');
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit].btn-primary, .btn-primary[type=submit]')]);
    await page.goto(BASE + '/index.php?option=com_mapaviajes');
    await page.waitForSelector('#mv-svg path.mv-sphere[d]', { timeout: 15000 });
    return { ctx, page };
  }
  const photos = [process.env.MV_P1, process.env.MV_P2].filter(Boolean);

  for (const [name, opts] of [['movil', { ...devices['iPhone 13'], locale: 'es-ES' }], ['escritorio', { viewport: { width: 1440, height: 900 }, locale: 'es-ES' }]]) {
    console.log('\n== ' + name + ' ==');
    const { ctx, page } = await sesion(opts, name);
    afirma(await page.locator('#mv-land path').count() > 100, 'el mapa se dibuja (paises)');
    afirma((await page.locator('#mv-stat').textContent()).trim().length > 0, 'cabecera con recuento');
    await page.screenshot({ path: `${OUT}/${name}-1-mapa.png` });
    // open the form with the button, the path every phone user has (no precise tap needed)
    await page.click('#mv-btnAdd'); await page.waitForTimeout(700);
    afirma(await page.locator('#mv-panel.mv-open').count() === 1, 'el formulario se abre');
    afirma(/Dónde|Where/.test(await page.locator('#mv-panelTitle').textContent()), 'titulo del formulario (¿Dónde estuviste? / Where have you been?)');
    // empty submit => friendly errors, nothing sent
    let sent = 0; page.on('request', (r) => { if (r.url().includes('trip.submit')) sent++; });
    await page.click('#mv-btnSend'); await page.waitForTimeout(300);
    afirma(sent === 0 && (await page.locator('#mv-qerr').textContent()).length > 0 && (await page.locator('#mv-terr').textContent()).length > 0 && (await page.locator('#mv-perr').textContent()).length > 0, 'enviar vacio: tres errores claros y no se envia nada');
    // search a place
    await page.fill('#mv-q', 'ciudad del'); await page.waitForTimeout(250);
    afirma(await page.locator('#mv-list li[role=option]').count() >= 1, 'la busqueda de lugares sugiere "Ciudad del Cabo" (lista propia)');
    await page.screenshot({ path: `${OUT}/${name}-2-busqueda.png` });
    await page.keyboard.press('Enter'); await page.waitForTimeout(900);
    afirma(/^(Fotos en|Photos in) Ciudad del Cabo$/.test(await page.inputValue('#mv-title')), 'el titulo se rellena solo');
    afirma(await page.locator('.mv-draft').count() === 1, 'chincheta provisional sobre el mapa');
    // hostile title: server and client must both refuse, nothing stored
    await page.fill('#mv-title', '<script>alert(1)</script>');
    await page.setInputFiles('#mv-files', photos);
    await page.waitForTimeout(500);
    afirma(await page.locator('.mv-photo').count() === photos.length, photos.length + ' fotos en la lista con su pie');
    await page.fill('#mv-cap0', 'Yo con la montaña de fondo');
    let status = 0, bodyJson = null;
    page.on('response', async (r) => { if (r.url().includes('trip.submit')) { status = r.status(); try { bodyJson = await r.json(); } catch (e) {} } });
    await page.click('#mv-btnSend'); await page.waitForTimeout(2500);
    afirma(status === 422 && /code|symbols|código|símbolos/i.test(bodyJson && bodyJson.error || ''), 'titulo con <script>: el servidor responde 422 con un mensaje claro (' + status + ')');
    afirma(await page.locator('#mv-panel.mv-open').count() === 1 && (await page.locator('#mv-terr').textContent()).length > 0, 'el formulario sigue abierto y marca el titulo');
    await page.screenshot({ path: `${OUT}/${name}-3-error.png` });
    // now a good one
    await page.fill('#mv-title', 'Fotos en Ciudad del Cabo ' + name);
    status = 0; await page.click('#mv-btnSend'); await page.waitForTimeout(3500);
    afirma(status === 201 && bodyJson && bodyJson.ok === true, 'envio correcto: 201 (' + status + ')');
    afirma(await page.locator('#mv-panel.mv-open').count() === 0, 'el formulario se cierra solo');
    afirma(/review|revis/i.test(await page.locator('#mv-toast').textContent()), 'aviso: queda pendiente de revision');
    await page.waitForTimeout(1500);
    await page.screenshot({ path: `${OUT}/${name}-4-enviado.png` });
    afirma(await page.locator('.mv-pin').count() >= 1, 'aparece su chincheta (pendiente) en el mapa');
    await ctx.close();
  }
  afirma(external.length === 0, 'ninguna peticion a otro dominio durante todo el flujo' + (external.length ? ': ' + external.slice(0, 3).join(' ') : ''));
  afirma(errors.length === 0, 'sin errores de JavaScript en consola' + (errors.length ? ': ' + errors.slice(0, 3).join(' | ') : ''));
  console.log(`\n${ok} correctas, ${fail} fallos`);
  await browser.close();
  process.exit(fail ? 1 : 0);
})();
