// Real browser test: friends (ask from the card, accept in the Friends tab), "friends only" photos locked for others, "Friends only" people filter,
// editing the photos of my own trip (switch, delete, add) and the "at least one public" rule in the new-album form.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = process.env.MV_BASE || 'http://127.0.0.1:8081';
let ok = 0, fail = 0;
const afirma = (c, m) => { c ? (ok++, console.log('  ok    ' + m)) : (fail++, console.log('  FALLA ' + m)); };
const psql = (sql) => execSync(process.env.MV_PSQL + ' -Atc "' + sql.replace(/"/g, '\\"') + '"').toString().trim();
const login = async (p, user, pass) => {
  await p.goto(BASE + '/index.php?option=com_users&view=login'); await p.fill('#username', user); await p.fill('#password', pass);
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit].btn-primary, .btn-primary[type=submit]')]);
};
(async () => {
  if (!process.env.MV_PSQL) { console.log('Falta MV_PSQL'); process.exit(2); }
  psql("DELETE FROM jt_mapaviajes_friends"); psql("DELETE FROM jt_mapaviajes_blocks");
  psql("UPDATE jt_mapaviajes_albums SET access=2");
  const trip = psql("SELECT t.id FROM jt_mapaviajes_trips t WHERE t.user_id=(SELECT id FROM jt_users WHERE username='ana') AND t.published=1 AND (SELECT COUNT(*) FROM jt_mapaviajes_photos p WHERE p.trip_id=t.id AND p.state=1)>=2 ORDER BY t.id DESC LIMIT 1");
  afirma(!!trip, 'ana tiene un viaje con 2 fotos o mas (' + trip + ')');
  psql("UPDATE jt_mapaviajes_photos SET friends_only=0 WHERE trip_id=" + trip);
  psql("UPDATE jt_mapaviajes_photos SET friends_only=1 WHERE trip_id=" + trip + " AND id<>(SELECT MIN(id) FROM jt_mapaviajes_photos WHERE trip_id=" + trip + ")");
  const nPriv = Number(psql("SELECT COUNT(*) FROM jt_mapaviajes_photos WHERE friends_only=1 AND trip_id=" + trip));
  const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' }).catch(() => chromium.launch());
  const errs = [];
  const mk = async (user, pass, w = 1280) => { const c = await browser.newContext({ viewport: { width: w, height: 820 } }); const p = await c.newPage(); p.on('pageerror', (e) => errs.push(user + ': ' + e.message)); await login(p, user, pass); return p; };
  const open = async (p, id) => { await p.goto(BASE + '/index.php?option=com_mapaviajes&trip=' + id); await p.waitForSelector('#mv-fi:not([hidden])'); await p.waitForTimeout(900); };

  const beto = await mk('beto', process.env.MV_BETO || 'cambia-esta-clave-de-prueba');
  await open(beto, trip);
  afirma(await beto.locator('#mv-fiLock').isVisible() && /solo para amigos|friends only/i.test(await beto.locator('#mv-fiLockT').textContent()) && (await beto.locator('#mv-fiLockT').textContent()).includes(String(nPriv)), 'beto (no amigo) ve «' + nPriv + ' fotos solo para amigos» con candado');
  afirma(await beto.locator('#mv-fiTh .mv-th').count() === 0 && (await beto.locator('#mv-fiImg').getAttribute('src')).includes('photo.view'), 'y solo una foto (la publica), sin tira de miniaturas');
  afirma(await beto.locator('#mv-fEdit').isHidden() && await beto.locator('#mv-fFriend').isVisible(), 'no hay boton de editar fotos, pero si «Pedir amistad»');
  await beto.click('#mv-fFriend'); await beto.waitForTimeout(1200);
  afirma(psql("SELECT COUNT(*) FROM jt_mapaviajes_friends WHERE status=0") === '1' && /cancelar|cancel/i.test(await beto.locator('#mv-fFriend').textContent()), 'beto pide amistad: queda pendiente y el boton lo dice');

  const ana = await mk('ana', process.env.MV_ANA || 'cambia-esta-clave-de-prueba');
  await ana.goto(BASE + '/index.php?option=com_mapaviajes'); await ana.waitForSelector('#mv-svg path.mv-sphere[d]'); await ana.waitForTimeout(900);
  afirma(await ana.locator('#mv-btnMenu.mv-dot').count() === 1, 'ana ve el puntito de aviso en el menu ☰');
  await ana.click('#mv-btnMenu'); await ana.click('#mv-btnFri'); await ana.waitForTimeout(500);
  afirma(await ana.locator('.mv-fr-li').count() === 1 && (await ana.locator('.mv-fr-n').first().textContent()) === 'beto', 'la pestaña Amigos muestra la solicitud de beto');
  await ana.click('.mv-fr-b.mv-yes'); await ana.waitForTimeout(1200);
  afirma(psql("SELECT status FROM jt_mapaviajes_friends") === '1' && await ana.locator('.mv-fr-h').count() >= 1, 'ana acepta: son amigos y aparece en su lista');

  await beto.reload(); await beto.waitForSelector('#mv-fi:not([hidden])'); await beto.waitForTimeout(900);
  afirma(await beto.locator('#mv-fiLock').isHidden() && await beto.locator('#mv-fiTh .mv-th').count() === 1 + nPriv, 'beto recarga: ya no hay candado y ve las ' + (1 + nPriv) + ' fotos');
  afirma(await beto.locator('#mv-fiTh .mv-th.mv-fo').count() === nPriv, 'las privadas llevan su marca');
  await beto.click('#mv-fiX'); await beto.click('#mv-zppl'); await beto.waitForTimeout(400);
  const before = await beto.locator('#mv-pplList input:checked').count();
  await beto.click('#mv-pplFri'); await beto.waitForTimeout(500);
  const checked = await beto.locator('#mv-pplList input:checked').count();
  afirma(checked >= 1 && checked < before + 1 && (await beto.locator('#mv-pplList li').count()) > checked, '«Solo amigos» deja marcados a los amigos (y a ti) y desmarca al resto (' + before + ' → ' + checked + ')');
  afirma((await beto.locator('#mv-chip').isVisible()), 'aparece la etiqueta de filtro activa');

  // editing my own photos
  await open(ana, trip);
  afirma(await ana.locator('#mv-fEdit').isVisible() && await ana.locator('#mv-fFriend').isHidden(), 'ana ve «Editar fotos» en su viaje');
  await ana.click('#mv-fEdit'); await ana.waitForSelector('dialog.mv-dlg[open] .mv-ed-li');
  const rows = await ana.locator('dialog .mv-ed-list .mv-ed-li').count();
  afirma(rows === 1 + nPriv, 'el editor lista las ' + rows + ' fotos');
  const first = ana.locator('dialog .mv-ed-list .mv-ed-li').first().locator('input[type=checkbox]');
  await first.check(); await ana.waitForTimeout(800);
  afirma(/El|At least|Al menos|public/i.test(await ana.locator('dialog .mv-dlg-msg').textContent()) && psql("SELECT COUNT(*) FROM jt_mapaviajes_photos WHERE friends_only=0 AND trip_id=" + trip) === '1', 'esconder la unica foto publica: avisa y no cambia nada');
  const second = ana.locator('dialog .mv-ed-list .mv-ed-li').nth(1).locator('input[type=checkbox]');
  await second.uncheck(); await ana.waitForTimeout(800);
  afirma(psql("SELECT COUNT(*) FROM jt_mapaviajes_photos WHERE friends_only=0 AND trip_id=" + trip) === '2', 'hacer publica otra foto: se guarda al momento');
  await second.check(); await ana.waitForTimeout(600);
  const room = 5 - rows;
  if (room > 0 && process.env.MV_P1) {
    await ana.setInputFiles('dialog .mv-ed-file', [process.env.MV_P1]); await ana.waitForTimeout(400);
    await ana.click('dialog dialog, dialog button.mv-btn-primary').catch(() => {});
    afirma(await ana.locator('dialog .mv-ed-new .mv-ed-li').count() === 1, 'al elegir una foto sale su fila con pie y «Solo amigos»');
    await ana.click('dialog button.mv-btn-primary'); await ana.waitForTimeout(500);
    afirma(/permiso|confirm|tick|Marca/i.test(await ana.locator('dialog .mv-dlg-msg').textContent()), 'sin marcar los derechos no se sube');
    await ana.check('#mv-ed-rights'); await ana.click('dialog button.mv-btn-primary'); await ana.waitForTimeout(3500);
    afirma(Number(psql("SELECT COUNT(*) FROM jt_mapaviajes_photos WHERE trip_id=" + trip)) === rows + 1, 'la foto nueva se sube y el album tiene ' + (rows + 1));
    afirma(await ana.locator('dialog[open]').count() === 0, 'el editor se cierra y la ficha se refresca');
  } else { await ana.click('dialog .mv-dlg-row button'); }

  // new-album form: at least one public photo
  await ana.goto(BASE + '/index.php?option=com_mapaviajes'); await ana.waitForSelector('#mv-svg path.mv-sphere[d]');
  if (process.env.MV_P1 && process.env.MV_P2) {
    await ana.click('#mv-btnAdd'); await ana.waitForTimeout(700);
    await ana.setInputFiles('#mv-files', [process.env.MV_P1, process.env.MV_P2]); await ana.waitForTimeout(600);
    afirma(await ana.locator('#mv-photos .mv-pv').count() === 2, 'cada foto del formulario lleva su casilla «Solo amigos»');
    await ana.check('#mv-pv0'); await ana.check('#mv-pv1');
    await ana.click('#mv-btnSend'); await ana.waitForTimeout(400);
    afirma(/p[uú]blica|public/i.test(await ana.locator('#mv-perr').textContent()), 'con todas privadas el formulario avisa y no envia');
  }
  afirma(errs.length === 0, 'sin errores de JavaScript ' + JSON.stringify(errs));
  psql("DELETE FROM jt_mapaviajes_friends"); psql("UPDATE jt_mapaviajes_photos SET friends_only=0 WHERE trip_id=" + trip);
  await browser.close();
  console.log(`\n${ok} correctas, ${fail} fallos`); process.exit(fail ? 1 : 0);
})();
