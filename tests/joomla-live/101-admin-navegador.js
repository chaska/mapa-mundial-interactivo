// Real browser test of the administrator redesign: panel, trips as cards (chips, per-card actions, floating bar on a selection), albums, ranking, raffle.
const { chromium } = require('playwright');
const BASE = process.env.MV_BASE || 'http://127.0.0.1:8081';
let ok = 0, fail = 0;
const afirma = (c, m) => { c ? (ok++, console.log('  ok    ' + m)) : (fail++, console.log('  FALLA ' + m)); };
(async () => {
  const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' }).catch(() => chromium.launch());
  const ctx = await browser.newContext({ viewport: { width: 1360, height: 900 } });
  const g = await ctx.request.get(BASE + '/administrator/index.php'); const t = (await g.text()).match(/name="([a-f0-9]{32})" value="1"/)[1];
  await ctx.request.post(BASE + '/administrator/index.php', { form: { username: 'admin', passwd: process.env.MV_ADMIN || 'cambia-esta-clave-de-prueba', option: 'com_login', task: 'login', return: 'aW5kZXgucGhw', [t]: '1' }, maxRedirects: 0 }).catch(() => {});
  const p = await ctx.newPage(); const errs = []; p.on('pageerror', (e) => errs.push(e.message));
  const open = async (q) => { await p.goto(BASE + '/administrator/index.php?option=com_mapaviajes' + q, { waitUntil: 'domcontentloaded' }); await p.waitForSelector('.mvA-app', { timeout: 20000 }); await p.waitForTimeout(400); };
  const chipN = (label) => p.evaluate((l) => { const c = [...document.querySelectorAll('.mvA-chip')].find((x) => x.textContent.trim().startsWith(l)); return c ? +c.querySelector('b').textContent : -1; }, label);

  await open('');
  // every link and form action of every screen is a clean URL (a doubly escaped "&amp;" sent every click back to the dashboard)
  const dirty = [];
  for (const q of ['', '&view=trips&filter[published]=', '&view=albums', '&view=ranking', '&view=raffle', '&view=settings']) {
    await open(q);
    dirty.push(...(await p.$$eval('.mvA-app a[href], .mvA-app form[action]', (e) => e.map((x) => x.getAttribute('href') || x.getAttribute('action')).filter((u) => /amp;/.test(u)))));
  }
  afirma(dirty.length === 0, 'ningun enlace ni formulario lleva «&amp;» doble: ' + JSON.stringify(dirty.slice(0, 2)));
  await open('');
  for (const [i, view] of [[1, 'trips'], [2, 'albums'], [3, 'ranking'], [4, 'raffle'], [5, 'settings'], [0, 'cpanel']]) {
    await Promise.all([p.waitForNavigation(), p.locator('.mvA-nav').first().locator('a').nth(i).click()]); await p.waitForSelector('.mvA-app');
    afirma((view === 'cpanel' ? !/view=/.test(p.url()) || /view=cpanel/.test(p.url()) : p.url().includes('view=' + view)) && (await p.locator('.mvA-side a.on').count()) === 1 && !/amp;/.test(p.url()), 'el menu lateral lleva a «' + view + '» al hacer clic');
  }
  await Promise.all([p.waitForNavigation(), p.locator('.mvA-head .mvA-btn.p').click()]); await p.waitForSelector('.mvA-app');
  afirma(await p.locator('#jform_title').count() === 1, '«Nuevo album» abre el formulario de album');
  await open('');
  await Promise.all([p.waitForNavigation(), p.locator('.mvA-kpi').nth(1).click()]); await p.waitForSelector('.mvA-app');
  afirma(p.url().includes('view=trips'), 'las tarjetas del panel llevan a su lista');
  await open('');
  await Promise.all([p.waitForNavigation(), p.locator('.mvA-links a').first().click()]); await p.waitForSelector('.mvA-app');
  afirma(p.url().includes('view=trips'), 'los accesos del panel derecho funcionan');
  await open('');
  afirma((await p.locator('.mvA-kpi').count()) === 5 && (await p.locator('.mvA-side a').count()) >= 7, 'panel: 5 tarjetas de datos y menu lateral propio');
  afirma((await p.locator('.mvA-sw').count()) >= 8 && (await p.locator('select[data-name="map_style"]').count()) === 1, 'panel: interruptores y selector de estilo del mapa');
  afirma((await p.locator('.mvA-head .mvA-btn.p').count()) === 1, 'panel: boton «Nuevo album»');

  await open('&view=trips&filter[published]=1');
  const total = await p.locator('article.mvA-trip').count();
  afirma(total > 3, 'viajes: se pintan como tarjetas (' + total + ')');
  afirma((await p.locator('.mvA-chip').count()) === 7 && (await p.locator('.mvA-chip.on').count()) === 1, 'viajes: 7 filtros en «chips», uno activo');
  const publ0 = await chipN('Published'), hid0 = await chipN('Hidden');
  afirma(!(await p.locator('#mvA-bulk.on').count()), 'viajes: la barra flotante no se ve sin seleccion');
  await p.locator('.mvA-pick').nth(0).click(); await p.locator('.mvA-pick').nth(1).click(); await p.waitForTimeout(200);
  afirma((await p.locator('#mvA-bulk.on').count()) === 1 && (await p.textContent('#mvA-bulkN')) === '2', 'viajes: al marcar 2 aparece la barra con «2»');
  await Promise.all([p.waitForNavigation(), p.locator('[data-mv-bulk="trips.archive"]').click()]); await p.waitForSelector('.mvA-app');
  afirma((await chipN('Hidden')) === hid0 + 2 && (await chipN('Published')) === publ0 - 2, 'viajes: «Ocultar» de la barra oculta los 2 (Ocultos ' + (hid0 + 2) + ')');
  await open('&view=trips&filter[published]=2');
  afirma((await p.locator('article.mvA-trip').count()) === hid0 + 2, 'viajes: el chip «Hidden» lista los ocultos');
  await Promise.all([p.waitForNavigation(), p.locator('[data-mv-task="trips.publish"]').first().click()]); await p.waitForSelector('.mvA-app');
  await open('&view=trips&filter[published]=2'); const left = await p.locator('article.mvA-trip').count();
  afirma(left === hid0 + 1, 'viajes: «Publicar» de una tarjeta publica ese viaje (quedan ' + left + ' ocultos)');
  if (left > hid0) { await p.locator('.mvA-pick').nth(0).click(); await Promise.all([p.waitForNavigation(), p.locator('[data-mv-bulk="trips.publish"]').click()]); await p.waitForSelector('.mvA-app'); }
  // trash and restore from a card
  await open('&view=trips&filter[published]=');
  const before = await chipN('Trashed');
  await Promise.all([p.waitForNavigation(), p.locator('[data-mv-task="trips.trash"]').first().click()]); await p.waitForSelector('.mvA-app');
  afirma((await chipN('Trashed')) === before + 1, 'viajes: la papelera de una tarjeta la manda a «Trashed»');
  await open('&view=trips&filter[published]=-2');
  await Promise.all([p.waitForNavigation(), p.locator('[data-mv-task="trips.unpublish"]').first().click()]); await p.waitForSelector('.mvA-app');
  afirma((await chipN('Trashed')) === before, 'viajes: «Restaurar» la saca de la papelera');
  await open('&view=trips&filter[published]=0'); if (await p.locator('[data-mv-task="trips.publish"]').count()) { await Promise.all([p.waitForNavigation(), p.locator('[data-mv-task="trips.publish"]').first().click()]); }

  await open('&view=albums&filter[published]=');
  afirma((await p.locator('article.mvA-album').count()) >= 1 && (await p.locator('.mvA-chip').count()) === 4, 'albumes: tarjetas y 4 filtros');
  await open('&task=album.edit&id=1');
  afirma((await p.locator('#jform_title').count()) === 1 && (await p.locator('input[name="logo_upload"]').count()) === 1, 'edicion de album: formulario completo con subida de logo');
  // the three buttons of the album form, really clicked
  const title0 = await p.inputValue('#jform_title');
  await p.fill('#jform_title', title0 + ' ');
  await Promise.all([p.waitForNavigation(), p.locator('[data-mv-submit="album.apply"]').click()]); await p.waitForSelector('.mvA-app');
  afirma(p.url().includes('layout=edit') && /saved|guardad/i.test(await p.locator('#system-message-container').innerText()), 'album: «Guardar» guarda y se queda en el formulario');
  await Promise.all([p.waitForNavigation(), p.locator('[data-mv-submit="album.save"]').click()]); await p.waitForSelector('.mvA-app');
  afirma(p.url().includes('view=albums') && !p.url().includes('layout=edit'), 'album: «Guardar y cerrar» vuelve a la lista');
  await open('&task=album.edit&id=1');
  await Promise.all([p.waitForNavigation(), p.locator('[data-mv-submit="album.cancel"]').click()]); await p.waitForSelector('.mvA-app');
  afirma(p.url().includes('view=albums') && !p.url().includes('layout=edit'), 'album: «Cancelar» vuelve a la lista');
  // full life of a new album: create, trash, restore, trash, delete for good (own confirmation window)
  await Promise.all([p.waitForNavigation(), p.locator('.mvA-head .mvA-btn.p').click()]); await p.waitForSelector('.mvA-app');
  const nm = 'Prueba ' + Date.now();
  await p.fill('#jform_title', nm);
  await Promise.all([p.waitForNavigation(), p.locator('[data-mv-submit="album.save"]').click()]); await p.waitForSelector('.mvA-app');
  await open('&view=albums&filter[published]=*');
  const card = () => p.locator('article.mvA-album', { hasText: nm });
  afirma((await card().count()) === 1, 'album nuevo: creado y listado');
  await Promise.all([p.waitForNavigation(), card().locator('[data-mv-task="albums.trash"]').click()]); await p.waitForSelector('.mvA-app');
  await open('&view=albums&filter[published]=-2');
  afirma((await card().count()) === 1, 'album: la papelera de la tarjeta lo manda a «Trashed»');
  await Promise.all([p.waitForNavigation(), card().locator('[data-mv-task="albums.unpublish"]').click()]); await p.waitForSelector('.mvA-app');
  await open('&view=albums&filter[published]=*');
  afirma((await card().count()) === 1 && (await card().locator('[data-mv-task="albums.publish"]').count()) === 1, 'album: «Restaurar» lo saca de la papelera (queda sin publicar)');
  await Promise.all([p.waitForNavigation(), card().locator('[data-mv-task="albums.publish"]').click()]); await p.waitForSelector('.mvA-app');
  await open('&view=albums&filter[published]=1');
  afirma((await card().count()) === 1, 'album: «Publicar» de la tarjeta lo publica');
  await Promise.all([p.waitForNavigation(), card().locator('[data-mv-task="albums.trash"]').click()]); await p.waitForSelector('.mvA-app');
  await open('&view=albums&filter[published]=-2');
  await card().locator('[data-mv-task="albums.delete"]').click(); await p.waitForSelector('dialog#mvA-dlg[open]');
  await Promise.all([p.waitForNavigation(), p.locator('#mvA-dlg .mvA-btn.p').click()]); await p.waitForSelector('.mvA-app');
  await open('&view=albums&filter[published]=*');
  afirma((await card().count()) === 0, 'album: «Eliminar definitivamente» (con ventana de confirmacion) lo borra');
  await open('&view=settings');
  afirma((await p.locator('.mvA-side .on').count()) === 1 && (await p.locator('input[name="jform[max_photos]"]').count()) === 1, 'opciones: pantalla propia con el menu lateral y los campos');
  // out-of-range values are refused with a message (the test site keeps the abuse brakes at 1000 on purpose, which the form rejects)
  await Promise.all([p.waitForNavigation(), p.locator('[data-mv-submit="settings.save"]').click()]); await p.waitForSelector('.mvA-app');
  afirma(/Invalid|inv/i.test(await p.locator('#system-message-container').innerText()), 'opciones: un valor fuera de rango se rechaza con un aviso');
  const prevPhotos = await p.inputValue('input[name="jform[max_photos]"]');
  await p.fill('input[name="jform[trips_per_hour]"]', '50'); await p.fill('input[name="jform[trips_per_day]"]', '200'); await p.fill('input[name="jform[max_photos]"]', '4');
  await Promise.all([p.waitForNavigation(), p.locator('[data-mv-submit="settings.save"]').click()]); await p.waitForSelector('.mvA-app');
  afirma((await p.inputValue('input[name="jform[max_photos]"]')) === '4' && (await p.inputValue('input[name="jform[trips_per_hour]"]')) === '50', 'opciones: los cambios válidos se guardan desde la pantalla propia');
  afirma((await p.locator('select[name="jform[map_style]"] option').count()) >= 6, 'opciones: el selector de estilos lista los mapas');
  if (process.env.MV_PSQL) { require('child_process').execSync(process.env.MV_PSQL + ' -c "UPDATE jt_extensions SET params = (params::jsonb || \'{\\"trips_per_hour\\":1000,\\"trips_per_day\\":1000,\\"max_photos\\":' + prevPhotos + '}\'::jsonb)::text WHERE element = \'com_mapaviajes\' AND type = \'component\'"'); }
  afirma(!(await p.locator('.sidebar-wrapper, #header, .header').count()), 'sin el menu ni la barra de Joomla (la pantalla es de la app)');
  await open('&view=ranking');
  afirma((await p.locator('.mvA-table').count()) >= 1, 'ranking: tablas con el diseño nuevo');
  await Promise.all([p.waitForNavigation(), p.locator('form[action*="ranking.close"] button').click()]); await p.waitForSelector('.mvA-app');
  afirma(p.url().includes('view=ranking') && (await p.locator('#system-message-container .alert-danger, #system-message-container .alert-error').count()) === 0, 'ranking: «Cerrar semanas pendientes» funciona (vuelve al ranking sin error)');
  await open('&view=raffle');
  if (await p.locator('form[action*="raffle.draw"] button:not([disabled])').count()) {
    await Promise.all([p.waitForNavigation(), p.locator('form[action*="raffle.draw"] button').click()]); await p.waitForSelector('.mvA-app');
    afirma(await p.locator('.mvA-winners li').count() >= 1, 'sorteo: «Sortear» muestra a los ganadores');
  }
  afirma((await p.locator('.mvA-card').count()) >= 2 && (await p.locator('form[action*="raffle.draw"] button').count()) === 1, 'sorteo: filtros y boton de sorteo');
  afirma(errs.length === 0, 'sin errores de JavaScript en las pantallas ' + JSON.stringify(errs.slice(0, 2)));
  await browser.close();
  console.log(`\n${ok} correctas, ${fail} fallos`); process.exit(fail ? 1 : 0);
})();
