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
  // dragging: the map and the pins layer move together, frame by frame (one transform each), and the view is committed on release
  {
    const d = await browser.newContext({ viewport: { width: 1200, height: 800 } }); const dp = await d.newPage(); await login(dp);
    await dp.goto(BASE + '/index.php?option=com_mapaviajes'); await dp.waitForSelector('#mv-svg path.mv-sphere[d]'); await dp.waitForTimeout(800);
    for (let i = 0; i < 3; i++) { await dp.click('#mv-zin'); await dp.waitForTimeout(250); }
    await dp.waitForTimeout(900);
    const rects = () => dp.evaluate(() => { const s = document.getElementById('mv-sphere').getBoundingClientRect(), pin = document.querySelector('.mv-pin:not([hidden])'); const q = pin ? pin.getBoundingClientRect() : null; return { sx: s.left, sy: s.top, px: q ? q.left : null, py: q ? q.top : null, ts: document.getElementById('mv-svg').style.transform, tp: document.getElementById('mv-pins').style.transform }; });
    const before = await rects();
    afirma(before.px !== null, 'hay una chincheta visible para medir');
    await dp.mouse.move(600, 420); await dp.mouse.down(); await dp.mouse.move(660, 450, { steps: 3 }); await dp.mouse.move(720, 480, { steps: 3 });
    const during = await rects();
    afirma(during.ts !== '' && during.ts === during.tp, 'durante el arrastre mapa y capa de chinchetas llevan EXACTAMENTE la misma transformacion (' + during.ts + ')');
    const dMap = [during.sx - before.sx, during.sy - before.sy], dPin = [during.px - before.px, during.py - before.py];
    afirma(Math.abs(dMap[0] - dPin[0]) <= 1 && Math.abs(dMap[1] - dPin[1]) <= 1 && (Math.abs(dMap[0]) > 20 || Math.abs(dMap[1]) > 20), 'y se desplazan lo mismo en pantalla (mapa ' + dMap.map(Math.round) + ' / chincheta ' + dPin.map(Math.round) + ')');
    await dp.mouse.up(); await dp.waitForTimeout(200);
    const after = await rects();
    afirma(after.ts === '' && after.tp === '' && Math.abs((after.sx - before.sx) - (after.px - before.px)) <= 1, 'al soltar se confirma la vista y siguen alineados (' + Math.round(after.sx - before.sx) + ' / ' + Math.round(after.px - before.px) + ')');
    await d.close();
  }
  // the form: Send / Cancel are the end of the form, not pinned to the bottom
  {
    const f = await browser.newContext({ viewport: { width: 390, height: 460 }, isMobile: true, hasTouch: true }); const fp = await f.newPage(); await login(fp);
    await fp.goto(BASE + '/index.php?option=com_mapaviajes'); await fp.waitForSelector('#mv-svg path.mv-sphere[d]'); await fp.click('#mv-btnAdd'); await fp.waitForTimeout(700);
    const r = await fp.evaluate(() => { const b = document.getElementById('mv-btnSend'), body = document.getElementById('mv-body'); const rect = b.getBoundingClientRect(); return { inside: body.contains(b), visibleAtOpen: rect.bottom <= innerHeight, pos: getComputedStyle(b.closest('.mv-panel-foot')).position }; });
    afirma(r.inside && r.pos === 'static' && !r.visibleAtOpen, 'el boton Enviar va dentro del formulario (al final), no fijo: con poco espacio hay que bajar para verlo');
    await f.close();
  }
  const m = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true }); const mp = await m.newPage(); await login(mp);
  await mp.goto(BASE + '/index.php?option=com_mapaviajes'); await mp.waitForSelector('#mv-svg path.mv-sphere[d]'); await mp.waitForTimeout(600);
  const b = await mp.evaluate(() => { const z = document.querySelector('.mv-zoom').getBoundingClientRect(); return { bottomGap: innerHeight - z.bottom }; });
  afirma(b.bottomGap >= 58 && b.bottomGap <= 64, 'en el movil los mandos quedan 40 px mas arriba (hueco inferior ' + Math.round(b.bottomGap) + ' px)');
  for (const [w, h] of [[667, 375], [844, 390], [932, 430]]) {
    const l = await browser.newContext({ viewport: { width: w, height: h }, isMobile: true, hasTouch: true }); const lp = await l.newPage(); await login(lp);
    await lp.goto(BASE + '/index.php?option=com_mapaviajes'); await lp.waitForSelector('#mv-svg path.mv-sphere[d]'); await lp.waitForTimeout(600);
    const r = await lp.evaluate(() => { const q = (s) => { const e = document.querySelector(s); const b = e.getBoundingClientRect(); return { x: b.left, y: b.top, r: b.right, b: b.bottom }; }; return { z: q('.mv-zoom'), brand: q('.mv-brand'), tops: [...document.querySelectorAll('.mv-actions .mv-btn')].map((e) => Math.round(e.getBoundingClientRect().top)), vw: innerWidth }; });
    const hit = !(r.z.r <= r.brand.x || r.brand.r <= r.z.x || r.z.b <= r.brand.y || r.brand.b <= r.z.y);
    afirma(r.z.x > r.vw / 2 && !hit, `horizontal ${w}x${h}: los mandos van al borde derecho y no se montan en el logo`);
    afirma(new Set(r.tops).size === 1, `horizontal ${w}x${h}: los iconos de arriba van en fila`);
    await l.close();
  }
  await m.close(); await browser.close();
  console.log(`\n${ok} correctas, ${fail} fallos`); process.exit(fail ? 1 : 0);
})();
