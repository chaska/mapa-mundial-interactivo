// Real browser test: the place box understands decimals and the degrees-minutes-seconds format of Google Maps.
const { chromium } = require('playwright');
const BASE = process.env.MV_BASE || 'http://127.0.0.1:8081';
let ok = 0, fail = 0;
const afirma = (c, m) => { c ? (ok++, console.log('  ok    ' + m)) : (fail++, console.log('  FALLA ' + m)); };
(async () => {
  const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' }).catch(() => chromium.launch());
  const p = await (await browser.newContext({ viewport: { width: 1280, height: 820 } })).newPage();
  const errs = []; p.on('pageerror', (e) => errs.push(String(e)));
  await p.goto(BASE + '/index.php?option=com_users&view=login'); await p.fill('#username', 'ana'); await p.fill('#password', process.env.MV_ANA || 'cambia-esta-clave-de-prueba');
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit].btn-primary, .btn-primary[type=submit]')]);
  await p.goto(BASE + '/index.php?option=com_mapaviajes'); await p.waitForSelector('#mv-svg path.mv-sphere[d]'); await p.click('#mv-btnAdd'); await p.waitForTimeout(600);
  const cases = [
    ['40.41, -3.70', '40.410, -3.700'],
    ['40°22′14″N 2°48′41″O', '40.371, -2.811'],
    ['40°22\'14"N 2°48\'41"W', '40.371, -2.811'],
    ['50°41′30″ N, 1°58′20″ O', '50.692, -1.972'],
    ['N 40°22′14″ W 2°48′41″', '40.371, -2.811'],
    ['40.3706 N, 2.8114 W', '40.371, -2.811'],
    ['2°48′41″O 40°22′14″N', '40.371, -2.811'],
    ['33°55′S 18°25′E', '-33.917, 18.417'],
  ];
  for (const [typed, want] of cases) {
    await p.fill('#mv-q', typed); await p.waitForTimeout(500);
    const first = (await p.locator('#mv-list li[role=option]').first().textContent().catch(() => '')) || '';
    afirma(first.includes(want), '«' + typed + '» → ' + want + ' (' + first.trim().slice(0, 40) + ')');
  }
  await p.fill('#mv-q', '91°N 2°E'); await p.waitForTimeout(400);
  afirma(!((await p.locator('#mv-list li[role=option]').first().textContent().catch(() => '')) || '').includes('91.000'), 'una latitud imposible (91°) no se acepta');
  afirma(errs.length === 0, 'sin errores de JavaScript ' + JSON.stringify(errs));
  await browser.close();
  console.log(`\n${ok} correctas, ${fail} fallos`); process.exit(fail ? 1 : 0);
})();
