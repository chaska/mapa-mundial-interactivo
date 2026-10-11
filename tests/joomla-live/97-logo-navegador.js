// The logo in a real browser: whatever is uploaded, the header shows it at the size the design asks for
// (208 px on a computer, down to 96 px on the smallest phones), whole, without moving anything.
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const path = require('path');
const BASE = process.env.MV_BASE || 'http://127.0.0.1:8081';
let ok = 0, fail = 0;
const afirma = (c, m) => { c ? (ok++, console.log('  ok    ' + m)) : (fail++, console.log('  FALLA ' + m)); };
const logo = (...a) => execFileSync('php', [path.join(__dirname, 'lib/logo.php'), ...a], { env: process.env }).toString().trim();
(async () => {
  const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' }).catch(() => chromium.launch());
  const sizes = [[1920, 1080], [1440, 900], [1024, 768], [768, 1024], [721, 700], [720, 700], [430, 932], [414, 896], [390, 844], [360, 740], [320, 568]];
  for (const [w, h, label] of [[4000, 4000, 'cuadrado 4000x4000'], [4000, 2000, 'apaisado 4000x2000'], [2000, 4000, 'vertical 2000x4000'], [512, 512, 'tu logo de 512x512']]) {
    console.log('\n== ' + label + ' ==');
    const stored = logo(String(w), String(h));
    const m = stored.match(/(\d+)x(\d+)$/);
    afirma(m && Math.max(+m[1], +m[2]) === 512, 'guardado a 512 px de lado largo (' + stored + ')');
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } }); const page = await ctx.newPage();
    await page.goto(BASE + '/index.php?option=com_users&view=login');
    await page.fill('#username', 'ana'); await page.fill('#password', process.env.MV_ANA || 'cambia-esta-clave-de-prueba');
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit].btn-primary, .btn-primary[type=submit]')]);
    for (const [vw, vh] of sizes) {
      await page.setViewportSize({ width: vw, height: vh });
      await page.goto(BASE + '/index.php?option=com_mapaviajes'); await page.waitForSelector('.mv-logo-img', { timeout: 15000 });
      await page.waitForFunction(() => document.querySelector('.mv-logo-img').complete);
      const r = await page.evaluate(() => { const i = document.querySelector('.mv-logo-img'), b = i.getBoundingClientRect(), a = document.querySelector('.mv-actions').getBoundingClientRect(); return { w: Math.round(b.width), h: Math.round(b.height), right: b.right, actLeft: a.left, nw: i.naturalWidth, nh: i.naturalHeight, fit: getComputedStyle(i).objectFit, docW: document.documentElement.scrollWidth, h1: getComputedStyle(document.querySelector('.mv-brand h1')).position }; });
      const want = vw > 1100 ? 208 : vw > 900 ? 168 : vw > 720 ? 132 : vw > 400 ? 120 : vw > 340 ? 108 : 96;
      afirma(r.w === want && r.h === want, `${vw}x${vh}: el logo se pinta a ${r.w}x${r.h} (el diseno pide ${want}x${want})`);
      afirma(r.fit === 'contain' && Math.max(r.nw, r.nh) === 512 && r.right <= vw && r.actLeft >= r.right - 1 && r.docW <= vw && r.h1 === 'absolute', `${vw}x${vh}: entero (contain), imagen real de ${r.nw}x${r.nh}, los botones no lo tapan, el titulo sigue en la pagina (oculto a la vista) y no hay scroll horizontal`);
    }
    if (w === 4000 && h === 4000) {
      for (const [n, vw, vh] of [['pc', 1440, 700], ['movil', 390, 844]]) { await page.setViewportSize({ width: vw, height: vh }); await page.goto(BASE + '/index.php?option=com_mapaviajes'); await page.waitForSelector('.mv-logo-img'); await page.waitForTimeout(1200); await page.screenshot({ path: `${process.env.MV_OUT || '.'}/logo-final-${n}.png`, clip: { x: 0, y: 0, width: vw, height: n === 'pc' ? 120 : 140 } }); }
    }
    await ctx.close();
  }
  logo('quitar');
  await browser.close();
  console.log(`\n${ok} correctas, ${fail} fallos`);
  process.exit(fail ? 1 : 0);
})();
