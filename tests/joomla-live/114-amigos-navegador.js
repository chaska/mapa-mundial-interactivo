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
  psql("DELETE FROM jt_mapaviajes_photos WHERE state=0"); // leftovers of other tests (photos still waiting for a moderator)
  psql("UPDATE jt_mapaviajes_albums SET access=2");
  const trip = psql("SELECT t.id FROM jt_mapaviajes_trips t WHERE t.user_id=(SELECT id FROM jt_users WHERE username='ana') AND t.published=1 AND (SELECT COUNT(*) FROM jt_mapaviajes_photos p WHERE p.trip_id=t.id AND p.state=1)>=2 ORDER BY t.id DESC LIMIT 1");
  afirma(!!trip, 'ana tiene un viaje con 2 fotos o mas (' + trip + ')');
  psql("UPDATE jt_mapaviajes_photos SET friends_only=0 WHERE trip_id=" + trip);
  psql("UPDATE jt_mapaviajes_photos SET friends_only=1 WHERE trip_id=" + trip + " AND id<>(SELECT MIN(id) FROM jt_mapaviajes_photos WHERE trip_id=" + trip + ")");
  const origLat = psql("SELECT lat FROM jt_mapaviajes_trips WHERE id=" + trip), origLon = psql("SELECT lon FROM jt_mapaviajes_trips WHERE id=" + trip);
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

  // editing my own photos: the SAME screen as the new-album one, with what cannot change hidden
  await open(ana, trip);
  afirma(await ana.locator('#mv-fEdit').isVisible() && await ana.locator('#mv-fFriend').isHidden(), 'ana ve «Editar fotos» en su viaje');
  await ana.click('#mv-fEdit'); await ana.waitForSelector('#mv-panel.mv-open.mv-editing'); await ana.waitForTimeout(500);
  const rows = await ana.locator('#mv-photos .mv-photo').count();
  afirma(rows === 1 + nPriv && /Edit the photos|Editar las fotos/.test(await ana.locator('#mv-panelTitle').textContent()), 'la pantalla de edicion es el panel de crear: ' + rows + ' tarjetas de foto con su titulo');
  const place0 = psql("SELECT place FROM jt_mapaviajes_trips WHERE id=" + trip), title0 = psql("SELECT title FROM jt_mapaviajes_trips WHERE id=" + trip);
  afirma(await ana.locator('#mv-q').isVisible() && (await ana.inputValue('#mv-q')) === place0 && (await ana.inputValue('#mv-title')) === title0 && await ana.locator('#mv-year').isVisible() && await ana.locator('#mv-drop').isVisible() && await ana.locator('#mv-pv0').isVisible(), 'trae el lugar, el titulo y el ano del album para cambiarlos, mas la zona de subir y las casillas «Solo amigos»');
  afirma(await ana.locator('#mv-panel .mv-field:has(input[name=mv-comments])').isHidden(), 'los comentarios (que ya se cambian desde la ficha) no estan');
  afirma((await ana.locator('#mv-btnSend').textContent()).trim().length > 0 && /Save|Guardar/.test(await ana.locator('#mv-btnSend').textContent()), 'el boton dice «Guardar cambios»');
  afirma(await ana.locator('#mv-rightsRow').isHidden(), 'sin fotos nuevas no pide la declaracion de derechos');
  await ana.check('#mv-pv0'); await ana.click('#mv-btnSend'); await ana.waitForTimeout(500);
  afirma(/p[uú]blica|public/i.test(await ana.locator('#mv-perr').textContent()) && psql("SELECT COUNT(*) FROM jt_mapaviajes_photos WHERE friends_only=0 AND trip_id=" + trip) === '1', 'esconder la unica foto publica: avisa y no guarda');
  await ana.uncheck('#mv-pv0'); await ana.uncheck('#mv-pv1'); await ana.waitForTimeout(300);
  afirma(psql("SELECT COUNT(*) FROM jt_mapaviajes_photos WHERE friends_only=0 AND trip_id=" + trip) === '1', 'los cambios NO se guardan hasta pulsar Guardar');
  await ana.fill('#mv-q', '40°22′14″N 2°48′41″O'); await ana.waitForTimeout(500); await ana.keyboard.press('Enter'); await ana.waitForTimeout(500);
  afirma(/40\.371, -2\.811/.test(await ana.locator('#mv-coords').textContent()), 'cambiar la ciudad: se escriben las coordenadas de Google y el panel las entiende');
  await ana.click('#mv-btnSend'); await ana.waitForSelector('#mv-panel:not(.mv-open)', { state: 'attached' }); await ana.waitForTimeout(1500);
  afirma(Math.abs(Number(psql("SELECT lat FROM jt_mapaviajes_trips WHERE id=" + trip)) - 40.370556) < 0.0001 && Math.abs(Number(psql("SELECT lon FROM jt_mapaviajes_trips WHERE id=" + trip)) + 2.811389) < 0.0001, 'al guardar, el album cambia de sitio en la base de datos');
  afirma(psql("SELECT COUNT(*) FROM jt_mapaviajes_photos WHERE friends_only=0 AND trip_id=" + trip) === '2' && await ana.locator('#mv-fi:not([hidden])').count() === 1, 'al guardar se aplica y vuelve a la ficha del viaje');
  await ana.click('#mv-fEdit'); await ana.waitForSelector('#mv-panel.mv-open.mv-editing'); await ana.waitForTimeout(400);
  await ana.locator('#mv-photos .mv-photo').last().locator('.mv-rm').click(); await ana.waitForTimeout(300);
  afirma(await ana.locator('#mv-photos .mv-photo').count() === rows - 1 && Number(psql("SELECT COUNT(*) FROM jt_mapaviajes_photos WHERE trip_id=" + trip)) === rows, '«Quitar» saca la foto de la lista pero no la borra hasta guardar');
  if (process.env.MV_P1) {
    await ana.setInputFiles('#mv-files', [process.env.MV_P1]); await ana.waitForTimeout(500);
    afirma(await ana.locator('#mv-photos .mv-photo').count() === rows && await ana.locator('#mv-rightsRow').isVisible(), 'la foto nueva sale como las del formulario de crear (pie, «Solo amigos») y pide los derechos');
    await ana.click('#mv-btnSend'); await ana.waitForTimeout(500);
    afirma(/permiso|confirm|tick|Marca|publish/i.test(await ana.locator('#mv-rerr').textContent()), 'sin marcar los derechos no se guarda');
    await ana.check('#mv-rights'); await ana.click('#mv-btnSend'); await ana.waitForSelector('#mv-panel:not(.mv-open)', { state: 'attached' }); await ana.waitForTimeout(2500);
    afirma(Number(psql("SELECT COUNT(*) FROM jt_mapaviajes_photos WHERE trip_id=" + trip)) === rows, 'se borra la quitada y se sube la nueva en un solo guardado (' + rows + ' fotos)');
  } else { await ana.click('#mv-btnCancel'); }
  await ana.goto(BASE + '/index.php?option=com_mapaviajes'); await ana.waitForSelector('#mv-svg path.mv-sphere[d]'); await ana.click('#mv-btnAdd'); await ana.waitForTimeout(600);
  afirma(await ana.locator('#mv-q').isVisible() && !(await ana.locator('#mv-panel.mv-editing').count()), 'el boton «Añadir mi viaje» sigue abriendo el formulario completo de siempre');
  await ana.click('#mv-btnCancel'); await ana.waitForTimeout(300);

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
  psql("UPDATE jt_mapaviajes_trips SET place='" + place0.replace(/'/g, "''") + "', lat=" + origLat + ", lon=" + origLon + " WHERE id=" + trip);
  await browser.close();
  console.log(`\n${ok} correctas, ${fail} fallos`); process.exit(fail ? 1 : 0);
})();
