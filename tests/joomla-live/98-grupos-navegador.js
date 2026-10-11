// Real browser test: albums that share a place (window of four panes, name tags beyond four) and the filter by PEOPLE.
// Needs: php lib/semilla-grupos.php first. Usage: NODE_PATH=$(npm root -g) node tests/joomla-live/98-grupos-navegador.js
const { chromium } = require('playwright');
const BASE = process.env.MV_BASE || 'http://127.0.0.1:8081';
let ok = 0, fail = 0;
const afirma = (c, m) => { c ? (ok++, console.log('  ok    ' + m)) : (fail++, console.log('  FALLA ' + m)); };
const overlap = (a, b) => !(a.x + a.width <= b.x + 1 || b.x + b.width <= a.x + 1 || a.y + a.height <= b.y + 1 || b.y + b.height <= a.y + 1);
(async () => {
  const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' }).catch(() => chromium.launch());
  for (const [name, opts] of [['escritorio', { viewport: { width: 1440, height: 810 } }], ['movil', { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true }]]) {
    console.log('\n== ' + name + ' ==');
    const ctx = await browser.newContext(opts), page = await ctx.newPage(); const errs = [];
    page.on('pageerror', (e) => errs.push(e.message));
    await page.goto(BASE + '/index.php?option=com_users&view=login');
    await page.fill('#username', 'ana'); await page.fill('#password', process.env.MV_ANA || 'cambia-esta-clave-de-prueba');
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit].btn-primary, .btn-primary[type=submit]')]);
    await page.goto(BASE + '/index.php?option=com_mapaviajes'); await page.waitForSelector('#mv-svg path.mv-sphere[d]'); await page.waitForTimeout(1200);
    const zoomTo = async (num, want) => {
      for (let i = 0; i < 16; i++) {
        if (await want()) { return true; }
        const pin = page.locator('.mv-pin', { hasText: new RegExp('^' + num + '$') }).first(); if (!(await pin.count())) { return false; }
        await pin.click({ force: true }).catch(() => {}); await page.waitForTimeout(1100); await page.keyboard.press('Escape');
      }
      return want();
    };
    // one album alone: tapping its pin opens the trip card straight away (no small card in between)
    const single = page.locator('.mv-pin').filter({ hasNotText: /\d/ }).first();
    if (await single.count()) { await single.click({ force: true }); await page.waitForTimeout(3500); afirma(await page.locator('#mv-fi:not([hidden])').count() === 1 && await page.locator('.mv-pop').count() === 0, 'tocar la chincheta de un album solo abre la ficha del viaje (no una tarjeta pequena)'); await page.keyboard.press('Escape'); await page.waitForTimeout(300); afirma(await page.locator('#mv-fi[hidden]').count() === 1, 'Escape cierra la ficha'); }
    else { afirma(false, 'hay una chincheta de un album solo'); }
    // 4 albums in one place -> four polaroids that do not touch each other
    const win = await zoomTo('4', async () => (await page.locator('.mv-polaroid').count()) >= 4);
    await page.waitForTimeout(900);
    const boxes = []; for (const el of await page.locator('.mv-polaroid').all()) { const b = await el.boundingBox(); if (b) { boxes.push(b); } }
    let touching = 0; for (let i = 0; i < boxes.length; i++) { for (let j = i + 1; j < boxes.length; j++) { if (overlap(boxes[i], boxes[j])) { touching++; } } }
    afirma(win && boxes.length >= 4 && touching === 0, 'cuatro albumes en la misma ciudad: cuatro polaroid en ventana de cuatro cristales, sin tocarse (' + boxes.length + ' polaroid, ' + touching + ' solapes)');
    await page.click('#mv-zhome'); await page.waitForTimeout(1200);
    // 7 albums -> name tags
    const tags = await zoomTo('7', async () => (await page.locator('.mv-tags .mv-tag').count()) >= 5);
    await page.waitForTimeout(700);
    const tb = []; for (const el of await page.locator('.mv-tags .mv-tag').all()) { const b = await el.boundingBox(); if (b) { tb.push(b); } }
    let tt = 0; for (let i = 0; i < tb.length; i++) { for (let j = i + 1; j < tb.length; j++) { if (overlap(tb[i], tb[j])) { tt++; } } }
    afirma(tags && tb.length >= 5 && tt === 0 && (await page.locator('.mv-tags .mv-polaroid').count()) === 0, 'siete albumes en la misma ciudad: etiquetas en columna, ninguna encima de otra (' + tb.length + ' etiquetas, ' + tt + ' solapes)');
    await page.click('#mv-zhome'); await page.waitForTimeout(1200);
    // people filter
    await page.click('#mv-zppl'); await page.waitForTimeout(300);
    const rows = await page.locator('#mv-pplList input').count();
    afirma(rows >= 3 && (await page.locator('#mv-pplList input:checked').count()) === rows, 'el boton de personas lista a quien ha publicado, todas marcadas (' + rows + ')');
    const statAll = (await page.locator('#mv-stat').textContent()).trim();
    await page.locator('#mv-pplList input').first().uncheck(); await page.waitForTimeout(400);
    const statOne = (await page.locator('#mv-stat').textContent()).trim();
    afirma(statOne !== statAll && !(await page.locator('#mv-chip').isHidden()), 'desmarcar a una persona oculta sus albumes y aparece el aviso del filtro (' + statAll + ' -> ' + statOne + ')');
    await page.click('#mv-pplNone'); await page.waitForTimeout(400);
    afirma((await page.locator('.mv-pin:visible').count()) === 0, 'Ninguna: el mapa queda sin chinchetas');
    await page.click('#mv-pplAll'); await page.waitForTimeout(400);
    afirma((await page.locator('#mv-stat').textContent()).trim() === statAll && await page.locator('#mv-chip').isHidden(), 'Todas: vuelve todo');
    afirma(errs.length === 0, 'sin errores de JavaScript' + (errs.length ? ': ' + errs[0] : ''));
    await ctx.close();
  }
  await browser.close();
  console.log(`\n${ok} correctas, ${fail} fallos`);
  process.exit(fail ? 1 : 0);
})();
