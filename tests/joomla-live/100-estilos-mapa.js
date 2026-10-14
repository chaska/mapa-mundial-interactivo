// Real browser test: map looks. The administrator picks the default look and lets people choose (control panel); people pick their own,
// it is remembered in their browser; an unknown / hostile look name is refused; with the choice off the button is not there.
const { chromium } = require('playwright');
const BASE = process.env.MV_BASE || 'http://127.0.0.1:8081';
let ok = 0, fail = 0;
const afirma = (c, m) => { c ? (ok++, console.log('  ok    ' + m)) : (fail++, console.log('  FALLA ' + m)); };
async function loginSite(page) {
  await page.goto(BASE + '/index.php?option=com_users&view=login');
  await page.fill('#username', 'ana'); await page.fill('#password', process.env.MV_ANA || 'cambia-esta-clave-de-prueba');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit].btn-primary, .btn-primary[type=submit]')]);
}
async function loginAdmin(ctx) {
  const g = await ctx.request.get(BASE + '/administrator/index.php'); const t = (await g.text()).match(/name="([a-f0-9]{32})" value="1"/)[1];
  await ctx.request.post(BASE + '/administrator/index.php', { form: { username: 'admin', passwd: process.env.MV_ADMIN || 'cambia-esta-clave-de-prueba', option: 'com_login', task: 'login', return: 'aW5kZXgucGhw', [t]: '1' }, maxRedirects: 0 }).catch(() => {});
}
const mapAttr = (page) => page.evaluate(() => document.getElementById('mv-root').getAttribute('data-map'));
const landFill = (page) => page.evaluate(() => getComputedStyle(document.querySelector('#mv-land path')).fill);
(async () => {
  const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' }).catch(() => chromium.launch());
  // --- administrator: control panel
  const ca = await browser.newContext({ viewport: { width: 1300, height: 900 } }); await loginAdmin(ca); const adm = await ca.newPage();
  await adm.goto(BASE + '/administrator/index.php?option=com_mapaviajes'); await adm.waitForSelector('select[data-name="map_style"]');
  const options = await adm.$$eval('select[data-name="map_style"] option', (o) => o.map((x) => x.value));
  afirma(['pergamino', 'noche', 'atlas', 'politico', 'acuarela', 'plano'].every((v) => options.includes(v)), 'el panel lista los 6 estilos: ' + options.join(', '));
  await adm.selectOption('select[data-name="map_style"]', 'noche'); await adm.waitForTimeout(700);
  afirma(/guardad|saved/i.test(await adm.textContent('#mvA-status')), 'cambiar el estilo se guarda al momento');
  const sw = adm.locator('input[data-name="map_user_choice"]');
  if (await sw.isChecked()) { await adm.locator('input[data-name="map_user_choice"] + .mvA-track').click(); await adm.waitForTimeout(500); }
  // an unknown / hostile value is refused by the server
  const bad = await adm.evaluate(async () => {
    const o = Joomla.getOptions('com_mapaviajes.admin'); const out = [];
    for (const v of ['../../configuration', 'noche"><script>', 'x'.repeat(40), 'NOCHE', '']) { const fd = new FormData(); fd.append(o.token, '1'); fd.append('name', 'map_style'); fd.append('value', v); const r = await fetch(o.url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } }); out.push(r.status); }
    return out;
  });
  afirma(bad.every((s) => s === 400), 'valores raros como estilo (ruta, etiqueta, largo, mayusculas, vacio) -> 400: ' + bad.join(','));
  // --- site: choice OFF
  const c = await browser.newContext({ viewport: { width: 1280, height: 780 } }); const p = await c.newPage(); await loginSite(p);
  await p.goto(BASE + '/index.php?option=com_mapaviajes'); await p.waitForSelector('#mv-land path'); await p.waitForTimeout(500);
  afirma((await mapAttr(p)) === 'noche', 'el mapa arranca con el estilo del administrador (noche)');
  afirma((await p.locator('#mv-zmap').count()) === 0, 'con la eleccion apagada no hay boton de estilos');
  const css = await p.$$eval('link[rel=stylesheet]', (l) => l.map((x) => x.href.split('/').pop().split('?')[0]));
  afirma(css.includes('noche.css') && !css.includes('atlas.css'), 'solo se carga el CSS del estilo elegido: ' + css.filter((x) => /^(noche|atlas|politico|acuarela|plano)\.css$/.test(x)).join(','));
  // --- administrator allows the choice
  if (!(await sw.isChecked())) { await adm.locator('input[data-name="map_user_choice"] + .mvA-track').click(); await adm.waitForTimeout(600); }
  await p.goto(BASE + '/index.php?option=com_mapaviajes'); await p.waitForSelector('#mv-land path'); await p.waitForTimeout(500);
  afirma((await p.locator('#mv-zmap').count()) === 1, 'con la eleccion encendida aparece el boton de estilos');
  await p.click('#mv-zmap'); await p.waitForSelector('#mv-maps:not([hidden])');
  const names = await p.$$eval('#mv-mapsList .mv-ppl-n', (e) => e.map((x) => x.textContent));
  afirma(names.length === 6, 'el selector muestra los 6 estilos: ' + names.join(' / '));
  const before = await landFill(p);
  await p.locator('#mv-mapsList input[value="politico"]').check(); await p.waitForTimeout(400);
  afirma((await mapAttr(p)) === 'politico' && (await landFill(p)) !== before, 'elegir «politico» cambia el aspecto al instante');
  await p.locator('#mv-mapsList input[value="plano"]').check(); await p.waitForTimeout(300);
  afirma((await mapAttr(p)) === 'plano', 'se puede cambiar otra vez (plano)');
  await p.reload(); await p.waitForSelector('#mv-land path'); await p.waitForTimeout(500);
  afirma((await mapAttr(p)) === 'plano', 'la eleccion se recuerda al recargar (solo en este navegador)');
  // another person (fresh browser) gets the administrator's default, not the first person's choice
  const c2 = await browser.newContext({ viewport: { width: 390, height: 800 }, isMobile: true, hasTouch: true }); const p2 = await c2.newPage(); await loginSite(p2);
  await p2.goto(BASE + '/index.php?option=com_mapaviajes'); await p2.waitForSelector('#mv-land path'); await p2.waitForTimeout(500);
  afirma((await mapAttr(p2)) === 'noche', 'otra persona ve el estilo por defecto del administrador (noche)');
  afirma(await p2.evaluate(() => { const b = document.getElementById('mv-zmap').getBoundingClientRect(); return b.width >= 44 && b.height >= 44; }), 'en el movil el boton mide al menos 44 px');
  // hostile value in the browser's storage is ignored
  await p2.evaluate(() => localStorage.setItem('mvMapStyle', '"><img src=x onerror=alert(1)>')); await p2.reload(); await p2.waitForSelector('#mv-land path'); await p2.waitForTimeout(400);
  afirma((await mapAttr(p2)) === 'noche', 'un valor raro guardado en el navegador se ignora');
  // leave the site as it was
  await adm.selectOption('select[data-name="map_style"]', 'pergamino'); await adm.waitForTimeout(500);
  if (await sw.isChecked()) { await adm.locator('input[data-name="map_user_choice"] + .mvA-track').click(); await adm.waitForTimeout(500); }
  await browser.close();
  console.log(`\n${ok} correctas, ${fail} fallos`); process.exit(fail ? 1 : 0);
})();
