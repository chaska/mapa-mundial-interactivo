// Real browser test: delete my trip from its card (own confirmation window), and every control of the card is readable on every map look.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const fs = require('fs');
const BASE = process.env.MV_BASE || 'http://127.0.0.1:8081';
let ok = 0, fail = 0;
const afirma = (c, m) => { c ? (ok++, console.log('  ok    ' + m)) : (fail++, console.log('  FALLA ' + m)); };
const psql = (sql) => execSync(process.env.MV_PSQL + ' -Atc "' + sql + '"').toString().trim();
const lum = (c) => { const m = c.match(/[\d.]+/g).map(Number); const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; }; return 0.2126 * f(m[0]) + 0.7152 * f(m[1]) + 0.0722 * f(m[2]); };
const contrast = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); };
(async () => {
  if (!process.env.MV_PSQL) { console.log('Falta MV_PSQL'); process.exit(2); }
  const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' }).catch(() => chromium.launch());
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 } }); const p = await ctx.newPage();
  await p.goto(BASE + '/index.php?option=com_users&view=login'); await p.fill('#username', 'ana'); await p.fill('#password', process.env.MV_ANA || 'cambia-esta-clave-de-prueba');
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit].btn-primary, .btn-primary[type=submit]')]);
  psql("UPDATE jt_extensions SET params=(params::jsonb || '{\\\"map_user_choice\\\":1}'::jsonb)::text WHERE element='com_mapaviajes' AND type='component'");
  const mine = psql("SELECT id FROM jt_mapaviajes_trips WHERE user_id=(SELECT id FROM jt_users WHERE username='ana') AND published=1 AND photo_count>0 ORDER BY id DESC LIMIT 1");
  const other = psql("SELECT id FROM jt_mapaviajes_trips WHERE user_id<>(SELECT id FROM jt_users WHERE username='ana') AND published=1 AND photo_count>0 ORDER BY id DESC LIMIT 1");
  afirma(!!mine && !!other, 'hay un viaje de ana (' + mine + ') y uno ajeno (' + other + ')');
  const open = async (id) => { await p.goto(BASE + '/index.php?option=com_mapaviajes&trip=' + id); await p.waitForSelector('#mv-fi:not([hidden])'); await p.waitForTimeout(900); };
  await open(other);
  afirma(await p.locator('#mv-fDel').isHidden(), 'en un viaje ajeno NO hay boton de eliminar');
  // every control readable on every look
  for (const look of ['pergamino', 'noche', 'atlas', 'politico', 'acuarela', 'plano']) {
    await p.evaluate((l) => document.getElementById('mv-root').setAttribute('data-map', l), look); await p.waitForTimeout(250);
    const bad = await p.evaluate((sels) => sels.map((s) => { const e = document.querySelector(s); if (!e || e.hidden || !e.offsetParent) { return null; } const c = getComputedStyle(e); let bg = c.backgroundColor, n = e; while ((bg === 'rgba(0, 0, 0, 0)' || bg === 'transparent') && n.parentElement) { n = n.parentElement; bg = getComputedStyle(n).backgroundColor; } return { s, color: c.color, bg }; }).filter(Boolean), ['#mv-fLike', '#mv-fFan', '#mv-fiX', '#mv-fiPrev']);
    const worst = Math.min(...bad.map((b) => contrast(b.color, b.bg)));
    afirma(bad.length >= 3 && worst >= 3, look + ': los botones de la ficha se leen (contraste minimo ' + worst.toFixed(1) + ')');
  }
  // the "3 albums · 2 countries" line (a pill under the logo) is readable on every look: give the album a logo for this check
  const store = process.env.MV_STORAGE, logoName = 'logo_contraste1.png';
  if (store) {
    const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAYAAADED76LAAAAEklEQVR4nGP8z8Dwn4EIwDiqEAAjsgIKd1ADQAAAAABJRU5ErkJggg==', 'base64');
    fs.writeFileSync(store + '/photos/' + logoName, png); psql("UPDATE jt_mapaviajes_albums SET logo='" + logoName + "' WHERE id=1");
  }
  await p.goto(BASE + '/index.php?option=com_mapaviajes'); await p.waitForSelector('#mv-stat'); await p.waitForTimeout(600);
  for (const look of ['pergamino', 'noche', 'atlas', 'politico', 'acuarela', 'plano']) {
    await p.evaluate((l) => document.getElementById('mv-root').setAttribute('data-map', l), look); await p.waitForTimeout(200);
    const r = await p.evaluate(() => { const e = document.getElementById('mv-stat'); if (!e || !e.offsetParent) { return null; } const c = getComputedStyle(e); let bg = c.backgroundColor, n = e; while ((bg === 'rgba(0, 0, 0, 0)' || bg === 'transparent') && n.parentElement) { n = n.parentElement; bg = getComputedStyle(n).backgroundColor; } const a = (bg.match(/[\d.]+/g) || []); return { color: c.color, bg, alpha: a.length > 3 ? +a[3] : 1 }; });
    afirma(r === null || contrast(r.color, r.bg) >= 3, look + ': la linea de albumes y paises se lee' + (r ? ' (contraste ' + contrast(r.color, r.bg).toFixed(1) + ')' : ' (no se muestra)'));
  }
  if (store) { psql("UPDATE jt_mapaviajes_albums SET logo='' WHERE id=1"); try { fs.unlinkSync(store + '/photos/' + logoName); } catch (_) { /* gone */ } }
  await p.evaluate(() => document.getElementById('mv-root').setAttribute('data-map', 'pergamino'));
  await open(mine);
  afirma(await p.locator('#mv-fDel').isVisible(), 'en un viaje propio SI hay boton de eliminar');
  await p.click('#mv-fDel'); await p.waitForSelector('dialog.mv-dlg[open]');
  afirma((await p.locator('dialog.mv-dlg').innerText()).length > 20, 'sale una ventana de confirmacion propia (no la del navegador)');
  await p.locator('dialog.mv-dlg button').first().click(); await p.waitForTimeout(300);
  afirma(Number(psql('SELECT COUNT(*) FROM jt_mapaviajes_trips WHERE id=' + mine)) === 1 && (await p.locator('dialog.mv-dlg').count()) === 0, 'Cancelar no borra nada');
  await p.click('#mv-fDel'); await p.waitForSelector('dialog.mv-dlg[open]');
  await p.locator('dialog.mv-dlg button').nth(1).click(); await p.waitForTimeout(1200);
  afirma(Number(psql('SELECT COUNT(*) FROM jt_mapaviajes_trips WHERE id=' + mine)) === 0, 'Confirmar borra el viaje de la base de datos');
  afirma(await p.locator('#mv-fi').isHidden(), 'la ficha se cierra');
  await p.goto(BASE + '/index.php?option=com_mapaviajes&trip=' + mine); await p.waitForTimeout(1200);
  afirma(await p.locator('#mv-fi:not([hidden])').count() === 0, 'el enlace al viaje borrado ya no abre nada');
  psql("UPDATE jt_extensions SET params=(params::jsonb - 'map_user_choice')::text WHERE element='com_mapaviajes' AND type='component'");
  await browser.close();
  console.log(`\n${ok} correctas, ${fail} fallos`); process.exit(fail ? 1 : 0);
})();
