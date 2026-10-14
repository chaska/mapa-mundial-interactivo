// Real browser test: follow a traveller (trip card + people list), the "Following" tab with its "New" tags and dot, the weekly-mail switch.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = process.env.MV_BASE || 'http://127.0.0.1:8081';
let ok = 0, fail = 0;
const afirma = (c, m) => { c ? (ok++, console.log('  ok    ' + m)) : (fail++, console.log('  FALLA ' + m)); };
const psql = (sql) => execSync(process.env.MV_PSQL + ' -Atc "' + sql + '"').toString().trim();
(async () => {
  if (!process.env.MV_PSQL) { console.log('Falta MV_PSQL'); process.exit(2); }
  const anaId = psql("SELECT id FROM jt_users WHERE username='ana'"), betoId = psql("SELECT id FROM jt_users WHERE username='beto'");
  psql('DELETE FROM jt_mapaviajes_follows; DELETE FROM jt_mapaviajes_prefs WHERE user_id IN (' + anaId + ',0)');
  const betoTrip = psql("SELECT id FROM jt_mapaviajes_trips WHERE user_id=" + betoId + " AND published=1 AND photo_count>0 ORDER BY id DESC LIMIT 1");
  const anaTrip = psql("SELECT id FROM jt_mapaviajes_trips WHERE user_id=" + anaId + " AND published=1 AND photo_count>0 ORDER BY id DESC LIMIT 1");
  afirma(!!betoTrip && !!anaTrip, 'hay un viaje de beto (' + betoTrip + ') y uno de ana (' + anaTrip + ')');
  const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' }).catch(() => chromium.launch());
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 } }); const p = await ctx.newPage(); const errs = []; p.on('pageerror', (e) => errs.push(e.message));
  await p.goto(BASE + '/index.php?option=com_users&view=login'); await p.fill('#username', 'ana'); await p.fill('#password', process.env.MV_ANA || 'cambia-esta-clave-de-prueba');
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit].btn-primary, .btn-primary[type=submit]')]);
  const open = async (id) => { await p.goto(BASE + '/index.php?option=com_mapaviajes' + (id ? '&trip=' + id : '')); await p.waitForSelector('.mv-land path'); await p.waitForTimeout(id ? 1100 : 900); };

  await open(anaTrip);
  afirma(await p.locator('#mv-fFollow').isHidden(), 'en un viaje propio no hay boton de seguir');
  await open(betoTrip);
  afirma(await p.locator('#mv-fFollow').isVisible() && /beto/i.test(await p.locator('#mv-fFollow').innerText()) && (await p.getAttribute('#mv-fFollow', 'aria-pressed')) === 'false', 'en un viaje ajeno hay «Seguir a beto»');
  afirma(await p.locator('#mv-btnMenu.mv-dot').count() === 0, 'sin seguir a nadie no hay puntito de novedades');
  await p.click('#mv-fFollow'); await p.waitForTimeout(700);
  afirma((await p.getAttribute('#mv-fFollow', 'aria-pressed')) === 'true' && /Following|Siguiendo/.test(await p.locator('#mv-fFollow').innerText()), 'al pulsar pasa a «Siguiendo a beto»');
  afirma(psql('SELECT COUNT(*) FROM jt_mapaviajes_follows WHERE follower_id=' + anaId + ' AND followed_id=' + betoId) === '1', 'queda guardado en la base de datos');
  afirma(/beto/i.test(await p.locator('#mv-toast').innerText()), 'sale un aviso que dice a quien sigues');

  // news: followed a moment ago, the existing trips of beto are "new" until she looks
  await open('');
  afirma(await p.locator('#mv-btnMenu.mv-dot').count() === 1, 'con viajes sin ver de alguien a quien sigue sale el puntito en el menu');
  await p.click('#mv-btnMenu'); await p.click('#mv-btnDisc'); await p.waitForSelector('#mv-drawer.mv-open');
  afirma((await p.locator('#mv-tab-fol').count()) === 1, 'el panel Descubrir tiene la pestaña «Siguiendo»');
  await p.click('#mv-tab-fol'); await p.waitForTimeout(500);
  afirma(await p.locator('#mv-dBody .mv-arow').count() >= 1 && await p.locator('#mv-dBody .mv-newtag').count() >= 1, 'lista los viajes de beto con la etiqueta «Nuevo»');
  afirma(await p.locator('#mv-digest').count() === 1 && !(await p.isChecked('#mv-digest')), 'el resumen semanal por correo empieza apagado');
  await p.waitForTimeout(500);
  afirma(Number(psql('SELECT COUNT(*) FROM jt_mapaviajes_prefs WHERE user_id=' + anaId + ' AND follows_seen IS NOT NULL')) === 1, 'ver las novedades las marca como vistas');
  await p.check('#mv-digest'); await p.waitForTimeout(600);
  afirma(psql('SELECT digest FROM jt_mapaviajes_prefs WHERE user_id=' + anaId) === '1', 'activar el resumen semanal se guarda');
  await open('');
  afirma(await p.locator('#mv-btnMenu.mv-dot').count() === 0, 'tras mirarlas, el puntito desaparece');
  await p.click('#mv-btnMenu'); await p.click('#mv-btnDisc'); await p.click('#mv-tab-fol'); await p.waitForTimeout(400);
  afirma(await p.isChecked('#mv-digest') && await p.locator('#mv-dBody .mv-newtag').count() === 0, 'el resumen sigue activado y ya no hay etiquetas «Nuevo»');
  await p.uncheck('#mv-digest'); await p.waitForTimeout(500);
  afirma(psql('SELECT digest FROM jt_mapaviajes_prefs WHERE user_id=' + anaId) === '0', 'y se puede apagar');

  // people list
  await open('');
  await p.click('#mv-zppl'); await p.waitForSelector('#mv-ppl:not([hidden])');
  const betoBtn = p.locator('#mv-pplList .mv-ppl-fol').first();
  afirma(await p.locator('#mv-pplList .mv-ppl-fol[aria-pressed="true"]').count() === 1, 'en la lista de personas se ve a quien sigue marcado');
  const rows = await p.locator('#mv-pplList .mv-ppl-li').count(), btns = await p.locator('#mv-pplList .mv-ppl-fol').count();
  afirma(btns === rows - 1, 'cada persona tiene su boton de seguir, salvo tu misma (' + btns + ' de ' + rows + ')');
  await p.locator('#mv-pplList .mv-ppl-fol[aria-pressed="true"]').click(); await p.waitForTimeout(700);
  afirma(psql('SELECT COUNT(*) FROM jt_mapaviajes_follows') === '0' && await p.locator('#mv-pplList .mv-ppl-fol[aria-pressed="true"]').count() === 0, 'pulsarlo otra vez deja de seguir');
  await p.locator('#mv-pplList .mv-ppl-fol').first().click(); await p.waitForTimeout(700);
  afirma(psql('SELECT COUNT(*) FROM jt_mapaviajes_follows') === '1', 'y seguir desde la lista tambien funciona');
  await p.click('#mv-pplX');
  afirma(errs.length === 0, 'sin errores de JavaScript ' + JSON.stringify(errs.slice(0, 2)));
  psql('DELETE FROM jt_mapaviajes_follows; DELETE FROM jt_mapaviajes_prefs WHERE user_id IN (' + anaId + ',0)');
  await browser.close();
  console.log(`\n${ok} correctas, ${fail} fallos`); process.exit(fail ? 1 : 0);
})();
