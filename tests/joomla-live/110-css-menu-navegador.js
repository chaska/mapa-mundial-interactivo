// Real browser test: the site's own CSS for the map and extra entries in the menu (click an element of another extension, or open an address).
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = process.env.MV_BASE || 'http://127.0.0.1:8081';
let ok = 0, fail = 0;
const afirma = (c, m) => { c ? (ok++, console.log('  ok    ' + m)) : (fail++, console.log('  FALLA ' + m)); };
const psql = (sql) => execSync(process.env.MV_PSQL + ' -Atc "' + sql + '"').toString().trim();
const setParams = (o) => psql("UPDATE jt_extensions SET params=(params::jsonb || '" + JSON.stringify(o).replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/'/g, "''") + "'::jsonb)::text WHERE element='com_mapaviajes' AND type='component'");
(async () => {
  if (!process.env.MV_PSQL) { console.log('Falta MV_PSQL'); process.exit(2); }
  setParams({ custom_css: '.fake-fab{display:none!important} body{--probe:1} @import url(https://evil.example/x.css); .fake-x{background:url(https://evil.example/p.png)} .fake-y{background:u\\72l(https://evil.example/q.png)} </style><script>alert(1)</script>', menu_extras: 'Cookies | .fake-fab\nAyuda | /ayuda\nMala | javascript:alert(1)\nOtra | .no-existe\nSin valor |' });
  const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' }).catch(() => chromium.launch());
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 } }); const p = await ctx.newPage();
  const errs = []; p.on('pageerror', (e) => errs.push(String(e))); p.on('dialog', (d) => { errs.push('dialog'); d.dismiss(); });
  await p.goto(BASE + '/index.php?option=com_users&view=login'); await p.fill('#username', 'ana'); await p.fill('#password', process.env.MV_ANA || 'cambia-esta-clave-de-prueba');
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit].btn-primary, .btn-primary[type=submit]')]);
  await p.goto(BASE + '/index.php?option=com_mapaviajes'); await p.waitForSelector('#mv-svg path.mv-sphere[d]'); await p.waitForTimeout(600);
  // a button from "another extension"
  await p.evaluate(() => { const b = document.createElement('button'); b.className = 'fake-fab'; b.textContent = 'galleta'; b.style.cssText = 'position:fixed;right:10px;bottom:10px'; b.onclick = () => { window.__fab = (window.__fab || 0) + 1; }; document.body.appendChild(b); });
  afirma(await p.locator('.fake-fab').isHidden(), 'el CSS propio oculta el boton de la otra extension');
  { const html = await p.content(); afirma(!/@import/i.test(html) && !/url\(\s*['"]?\s*(https?:)?\/\//i.test(html), 'el CSS propio no puede llamar a otras webs: ni @import, ni url() remoto, ni con escapes de CSS'); }
  afirma(!(await p.content()).includes('<script>alert(1)'), 'el CSS propio no puede sacar etiquetas (</style><script> neutralizado)');
  await p.click('#mv-btnMenu');
  const items = await p.locator('#mv-menu .mv-xmenu:visible').allTextContents();
  afirma(JSON.stringify(items.map((s) => s.trim())) === JSON.stringify(['Cookies', 'Ayuda']), 'el menu muestra «Cookies» (existe) y «Ayuda» (direccion); no «Otra» (no existe), ni la de javascript:, ni la vacia: ' + JSON.stringify(items));
  afirma((await p.locator('#mv-menu a.mv-xmenu').getAttribute('href')) === '/ayuda', 'la entrada de direccion apunta a /ayuda');
  await p.click('#mv-menu button[data-sel=".fake-fab"]'); await p.waitForTimeout(300);
  afirma((await p.evaluate(() => window.__fab || 0)) === 1, 'pulsar «Cookies» del menu pulsa el boton oculto de la otra extension');
  afirma(await p.locator('#mv-menu').isHidden(), 'y el menu se cierra');
  afirma(errs.length === 0, 'sin errores de JavaScript ni ventanas ' + JSON.stringify(errs));
  setParams({ custom_css: '', menu_extras: '' });
  await p.goto(BASE + '/index.php?option=com_mapaviajes'); await p.waitForSelector('#mv-svg path.mv-sphere[d]');
  afirma(await p.locator('.mv-xmenu').count() === 0, 'sin configurar nada, el menu queda como antes');
  await browser.close();
  console.log(`\n${ok} correctas, ${fail} fallos`); process.exit(fail ? 1 : 0);
})();
