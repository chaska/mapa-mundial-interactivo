// Real browser test: resizing the window keeps the map fitted (it is not left at the old size), and the phone controls sit 40 px higher.
const { chromium } = require('playwright');
const BASE = process.env.MV_BASE || 'http://127.0.0.1:8081';
let ok = 0, fail = 0;
const afirma = (c, m) => { c ? (ok++, console.log('  ok    ' + m)) : (fail++, console.log('  FALLA ' + m)); };
async function login(page) {
  await page.goto(BASE + '/index.php?option=com_users&view=login');
  await page.fill('#username', 'ana'); await page.fill('#password', process.env.MV_ANA || 'cambia-esta-clave-de-prueba');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit].btn-primary, .btn-primary[type=submit]')]);
}
const sphere = (page) => page.evaluate(() => { const b = document.getElementById('mv-sphere').getBoundingClientRect(); return { w: Math.round(b.width), cx: Math.round(b.left + b.width / 2), vw: innerWidth }; });
(async () => {
  const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' }).catch(() => chromium.launch());
  // reference: the map as drawn by a fresh load at each width
  const ref = {};
  for (const w of [1440, 900, 640, 390]) {
    const c = await browser.newContext({ viewport: { width: w, height: 800 } }); const p = await c.newPage(); await login(p);
    await p.goto(BASE + '/index.php?option=com_mapaviajes'); await p.waitForSelector('#mv-svg path.mv-sphere[d]'); await p.waitForTimeout(800); ref[w] = await sphere(p); await c.close();
  }
  console.log('referencia (carga nueva): ' + JSON.stringify(ref));
  const c = await browser.newContext({ viewport: { width: 1440, height: 800 } }); const page = await c.newPage(); await login(page);
  await page.goto(BASE + '/index.php?option=com_mapaviajes'); await page.waitForSelector('#mv-svg path.mv-sphere[d]'); await page.waitForTimeout(800);
  for (const w of [900, 640, 390, 1440]) {
    await page.setViewportSize({ width: w, height: 800 }); await page.waitForTimeout(500);
    const s = await sphere(page);
    afirma(Math.abs(s.w - ref[w].w) <= 3 && Math.abs(s.cx - ref[w].cx) <= 3, `de 1440 a ${w} px: el mapa se reajusta al ancho como en una carga nueva (${s.w} px, centro ${s.cx}; esperado ${ref[w].w}, ${ref[w].cx})`);
  }
  await c.close();
  const m = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true }); const mp = await m.newPage(); await login(mp);
  await mp.goto(BASE + '/index.php?option=com_mapaviajes'); await mp.waitForSelector('#mv-svg path.mv-sphere[d]'); await mp.waitForTimeout(600);
  const b = await mp.evaluate(() => { const z = document.querySelector('.mv-zoom').getBoundingClientRect(); return { bottomGap: innerHeight - z.bottom }; });
  afirma(b.bottomGap >= 58 && b.bottomGap <= 64, 'en el movil los mandos quedan 40 px mas arriba (hueco inferior ' + Math.round(b.bottomGap) + ' px)');
  await m.close(); await browser.close();
  console.log(`\n${ok} correctas, ${fail} fallos`); process.exit(fail ? 1 : 0);
})();
