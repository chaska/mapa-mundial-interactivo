// Real browser test: the author turns the comments of their own trip on and off from the trip card.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = process.env.MV_BASE || 'http://127.0.0.1:8081';
let ok = 0, fail = 0;
const afirma = (c, m) => { c ? (ok++, console.log('  ok    ' + m)) : (fail++, console.log('  FALLA ' + m)); };
const psql = (sql) => execSync(process.env.MV_PSQL + ' -Atc "' + sql + '"').toString().trim();
(async () => {
  if (!process.env.MV_PSQL) { console.log('Falta MV_PSQL'); process.exit(2); }
  const anaId = psql("SELECT id FROM jt_users WHERE username='ana'");
  psql("UPDATE jt_extensions SET params=(params::jsonb || '{\\\"comments_provider\\\":\\\"engage\\\"}'::jsonb)::text WHERE element='com_mapaviajes' AND type='component'");
  psql("UPDATE jt_mapaviajes_albums SET allow_comments=1 WHERE id=1");
  const mine = psql("SELECT id FROM jt_mapaviajes_trips WHERE user_id=" + anaId + " AND published=1 AND photo_count>0 ORDER BY id DESC LIMIT 1");
  const other = psql("SELECT id FROM jt_mapaviajes_trips WHERE user_id<>" + anaId + " AND published=1 AND photo_count>0 ORDER BY id DESC LIMIT 1");
  psql('UPDATE jt_mapaviajes_trips SET allow_comments=0 WHERE id=' + mine);
  const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' }).catch(() => chromium.launch());
  const p = await (await browser.newContext({ viewport: { width: 1280, height: 800 } })).newPage(); const errs = []; p.on('pageerror', (e) => errs.push(e.message));
  await p.goto(BASE + '/index.php?option=com_users&view=login'); await p.fill('#username', 'ana'); await p.fill('#password', process.env.MV_ANA || 'cambia-esta-clave-de-prueba');
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit].btn-primary, .btn-primary[type=submit]')]);
  const open = async (id) => { await p.goto(BASE + '/index.php?option=com_mapaviajes&trip=' + id); await p.waitForSelector('#mv-fi:not([hidden])'); await p.waitForTimeout(900); };
  await open(mine);
  const sw = p.locator('#mv-fCmSw');
  afirma(await sw.isVisible() && /on|activar/i.test(await sw.innerText()) && !/off|desactivar/i.test(await sw.innerText()), 'en mi viaje con comentarios cerrados sale «Activar comentarios» (' + (await sw.innerText()).trim() + ')');
  afirma(await p.locator('#mv-fiCm').isHidden() && await p.locator('#mv-fCom').isHidden(), 'y no hay seccion de comentarios');
  await sw.click(); await p.waitForTimeout(1500);
  afirma(psql('SELECT allow_comments FROM jt_mapaviajes_trips WHERE id=' + mine) === '1', 'al pulsar, se activan en la base de datos');
  afirma(await p.locator('#mv-fiCm').isVisible() && /off|desactivar/i.test(await sw.innerText()), 'aparece la seccion de comentarios y el boton pasa a «Desactivar comentarios»');
  afirma(/comment/i.test(await p.locator('#mv-toast').innerText()), 'sale un aviso');
  await open(mine);
  afirma(await p.locator('#mv-fiCm').isVisible(), 'al recargar siguen activados');
  await sw.click(); await p.waitForTimeout(1200);
  afirma(psql('SELECT allow_comments FROM jt_mapaviajes_trips WHERE id=' + mine) === '0' && await p.locator('#mv-fiCm').isHidden(), 'se pueden volver a cerrar');
  await open(other);
  afirma(await p.locator('#mv-fCmSw').isHidden(), 'en un viaje ajeno no hay botones para los comentarios');
  psql('UPDATE jt_mapaviajes_trips SET allow_comments=1 WHERE id=' + mine);
  afirma(errs.length === 0, 'sin errores de JavaScript ' + JSON.stringify(errs.slice(0, 2)));
  await browser.close();
  console.log(`\n${ok} correctas, ${fail} fallos`); process.exit(fail ? 1 : 0);
})();
