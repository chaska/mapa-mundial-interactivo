<?php
declare(strict_types=1);
/**
 * Live tests of the administrator side added in 1.3.0: the control panel and its self-saving switches, the ranking view,
 * the raffle list (filters, CSV, draw, permissions) and the e-mails to authors. Mails are captured as files by
 * tests/joomla-live (MV_MAILDIR, default /tmp/mv-mails): the test site's PHP is started with sendmail_path pointing at a script.
 */
require __DIR__ . '/lib/http.php';
if (!defined('_JEXEC')) { define('_JEXEC', 1); }
$site = rtrim((string) getenv('MV_SITE'), '/');
require_once $site . '/libraries/vendor/autoload.php';
require_once __DIR__ . '/../../src/component/backend/src/Service/RaffleService.php';
use Chaska\Component\Mapaviajes\Administrator\Service\RaffleService;

$base = getenv('MV_BASE') ?: 'http://127.0.0.1:8081';
$maildir = getenv('MV_MAILDIR') ?: '/tmp/mv-mails';
$db = new Bd(getenv('MV_PREFIX') ?: 'jt_');
$T = $db->t('mapaviajes_trips'); $U = $db->t('users'); $X = $db->t('extensions'); $EV = $db->t('mapaviajes_events');
$params = fn () => json_decode((string) $db->uno("SELECT params FROM $X WHERE element = 'com_mapaviajes' AND type = 'component'"), true) ?: [];
passthru('php ' . escapeshellarg(__DIR__ . '/lib/semilla-podio.php'));   // pd_* users, trips with photos, closed week
$admin = new Cliente($base, 'admin'); $ana = new Cliente($base, 'ana'); $guest = new Cliente($base, 'g');
afirma($admin->login('admin', getenv('MV_ADMIN') ?: 'cambia-esta-clave-de-prueba', true) && $ana->login('ana', getenv('MV_ANA') ?: 'cambia-esta-clave-de-prueba'), 'admin y ana inician sesion');

seccion('Panel de control');
$p = $admin->get('/administrator/index.php?option=com_mapaviajes');
afirma($p['code'] === 200 && str_contains($p['body'], 'mvA-kpi') && str_contains($p['body'], 'mvA-sw') && str_contains($p['body'], 'data-name="review_first"'), 'el panel es la pagina de entrada, con tarjetas e interruptores');
afirma(preg_match('~mvA-n">(\d+)</span>~', $p['body']) === 1, 'las tarjetas traen numeros');
afirma(!str_contains($ana->get('/administrator/index.php?option=com_mapaviajes')['body'], 'mvA-tiles'), 'una usuaria registrada no ve el panel');
preg_match('/"com_mapaviajes.admin":\{[^}]*"token":"([a-f0-9]{32})"/', $p['body'], $m); $tok = $m[1] ?? '';
afirma($tok !== '', 'el panel lleva el token CSRF para guardar');
$set = fn (Cliente $c, array $f, array $h = ['X-Requested-With: XMLHttpRequest']) => $c->post('/administrator/index.php?option=com_mapaviajes&task=cpanel.setting&format=json', http_build_query($f), $h);
$before = $params()['review_first'] ?? null;
$r = $set($admin, ['name' => 'review_first', 'value' => '0', $tok => '1']);
afirma($r['code'] === 200 && json_decode($r['body'], true)['ok'] === true && (int) ($params()['review_first'] ?? 9) === 0, 'apagar «revisar primero»: se guarda en el momento');
$r = $set($admin, ['name' => 'review_first', 'value' => '1', $tok => '1']);
afirma((int) ($params()['review_first'] ?? 9) === 1, 'y se vuelve a encender');
$r = $set($admin, ['name' => 'comments_provider', 'value' => 'events', $tok => '1']); afirma(($params()['comments_provider'] ?? '') === 'events', 'el proveedor de comentarios se cambia desde el panel');
$set($admin, ['name' => 'comments_provider', 'value' => 'none', $tok => '1']);
afirma($set($admin, ['name' => 'review_first', 'value' => '0'])['code'] === 403, 'sin token: 403');
afirma($set($admin, ['name' => 'review_first', 'value' => '0', $tok => '1'], [])['code'] === 403, 'sin X-Requested-With: 403');
afirma($admin->get('/administrator/index.php?option=com_mapaviajes&task=cpanel.setting&format=json')['code'] === 403, 'GET: 403');
foreach ([['storage_path', '/etc'], ['review_first', '2'], ['review_first', ''], ['comments_provider', 'x'], ['comments_provider', 'engage<'], ['', '1'], ['review_first[]', '1'], ['rank_w_trip', '9999']] as [$n, $v]) {
	$r = $set($admin, ['name' => $n, 'value' => $v, $tok => '1']); afirma($r['code'] === 400, "ajuste no permitido [$n=$v]: 400");
}
afirma(!isset($params()['storage_path']) || $params()['storage_path'] !== '/etc', 'ni la ruta de almacenamiento ni nada fuera de la lista se puede tocar desde aqui');
$r = $ana->post('/administrator/index.php?option=com_mapaviajes&task=cpanel.setting&format=json', http_build_query(['name' => 'review_first', 'value' => '0', $tok => '1']), ['X-Requested-With: XMLHttpRequest']);
afirma($r['code'] !== 200 || json_decode($r['body'], true)['ok'] !== true, 'una usuaria registrada no puede cambiar ajustes');
afirma((int) ($params()['review_first'] ?? 9) === 1, 'y nada cambio');

seccion('Ranking (vista del administrador)');
$ana->get('/index.php?option=com_mapaviajes&task=ranking.data&format=json'); // the first person to open the podium closes the finished weeks
$p = $admin->get('/administrator/index.php?option=com_mapaviajes&view=ranking');
afirma($p['code'] === 200 && str_contains($p['body'], 'pd_alba'), 'se ve el podio de la semana cerrada y los nombres');
$r = $admin->get('/administrator/index.php?option=com_mapaviajes&view=ranking&week=' . rawurlencode("2026-01-05' OR '1'='1"));
afirma($r['code'] === 200 && !str_contains($r['body'], 'SQLSTATE'), 'una semana inventada se ignora sin error');
$weeks = (int) $db->uno('SELECT COUNT(*) FROM ' . $db->t('mapaviajes_weeks'));
$r = $admin->post('/administrator/index.php?option=com_mapaviajes&task=ranking.close', http_build_query([]));
afirma((int) $db->uno('SELECT COUNT(*) FROM ' . $db->t('mapaviajes_weeks')) === $weeks, 'cerrar semanas sin token no hace nada');
afirma(!str_contains($ana->get('/administrator/index.php?option=com_mapaviajes&view=ranking')['body'], 'pd_alba'), 'ana no ve el ranking del administrador');

seccion('Sorteo: participantes');
$db->exec("UPDATE $U SET block = 1 WHERE username = 'pd_carla'");
$r = $admin->get('/administrator/index.php?option=com_mapaviajes&view=raffle');
afirma($r['code'] === 200 && str_contains($r['body'], 'pd_alba') && str_contains($r['body'], 'pd_bruno') && str_contains($r['body'], 'id="raffleList"'), 'lista a quien tiene viajes publicados con fotos');
afirma(!str_contains($r['body'], 'pd_carla'), 'una cuenta bloqueada no participa');
$r = $admin->get('/administrator/index.php?option=com_mapaviajes&view=raffle&min=2');
afirma(str_contains($r['body'], 'pd_alba') && !str_contains($r['body'], 'pd_bruno'), 'minimo 2 viajes: solo alba');
$future = gmdate('Y-m-d', time() + 86400 * 400);
afirma(!str_contains($admin->get('/administrator/index.php?option=com_mapaviajes&view=raffle&from=' . $future)['body'], 'pd_alba'), 'un periodo sin viajes no lista a nadie');
$r = $admin->get('/administrator/index.php?option=com_mapaviajes&view=raffle&from=' . rawurlencode("x'; DROP TABLE a;--") . '&min=' . rawurlencode('1 OR 1=1') . '&album=' . rawurlencode('9 UNION SELECT 1'));
afirma($r['code'] === 200 && !str_contains($r['body'], 'SQLSTATE') && str_contains($r['body'], 'pd_alba'), 'filtros hostiles: se descartan y se usa lo valido');
// a trip without photos does not make anybody a participant; an unpublished one neither
$uid = (int) $db->uno("SELECT id FROM $U WHERE username = 'pd_bruno'");
$db->exec("UPDATE $T SET photo_count = 0 WHERE user_id = :u AND title = 'PD t'", [':u' => $uid]);
afirma(!str_contains($admin->get('/administrator/index.php?option=com_mapaviajes&view=raffle')['body'], 'pd_bruno'), 'un viaje sin fotos no cuenta');
$db->exec("UPDATE $T SET photo_count = 5 WHERE user_id = :u AND title = 'PD t'", [':u' => $uid]); $db->exec("UPDATE $T SET published = 0 WHERE user_id = :u AND title = 'PD t'", [':u' => $uid]);
afirma(!str_contains($admin->get('/administrator/index.php?option=com_mapaviajes&view=raffle')['body'], 'pd_bruno'), 'un viaje sin publicar tampoco');
$db->exec("UPDATE $T SET published = 1 WHERE user_id = :u AND title = 'PD t'", [':u' => $uid]);
afirma(str_contains($ana->get('/administrator/index.php?option=com_mapaviajes&view=raffle')['body'], 'pd_alba') === false, 'ana no ve el listado');
afirma($guest->get('/administrator/index.php?option=com_mapaviajes&view=raffle')['code'] !== 200 || !str_contains($guest->get('/administrator/index.php?option=com_mapaviajes&view=raffle')['body'], 'pd_alba'), 'un invitado tampoco');

seccion('Sorteo: CSV y sorteo');
$page = $admin->get('/administrator/index.php?option=com_mapaviajes&view=raffle')['body']; preg_match('/name="([a-f0-9]{32})" value="1"/', $page, $m); $rt = $m[1] ?? '';
$db->exec("UPDATE $U SET name = :n WHERE username = 'pd_alba'", [':n' => '=HYPERLINK("http://x.test","clic")']);
$csv = $admin->post('/administrator/index.php?option=com_mapaviajes&task=raffle.csv', http_build_query(['from' => '', 'to' => '', 'min' => '1', 'album' => '', $rt => '1']));
afirma($csv['code'] === 200 && str_contains($csv['headers']['content-type'] ?? '', 'text/csv') && str_starts_with($csv['body'], "\xEF\xBB\xBF\"id\",\"username\""), 'CSV con cabecera y BOM para Excel');
afirma(str_contains($csv['body'], '"pd_alba"') && str_contains($csv['body'], '"\'=HYPERLINK(""http://x.test"",""clic"")"') && !preg_match('/,"=/', $csv['body']), 'una celda que empieza por = se neutraliza (inyeccion de formulas)');
afirma(substr_count($csv['body'], "\r\n") === 1 + (int) preg_match_all('/^"\d+","/m', $csv['body']) && !str_contains($csv['body'], 'pd_carla'), 'una linea por persona y sin bloqueados');
$noTok = $admin->post('/administrator/index.php?option=com_mapaviajes&task=raffle.csv', http_build_query(['min' => '1']));
afirma(!str_contains($noTok['headers']['content-type'] ?? '', 'text/csv'), 'sin token no hay descarga');
$ana->post('/administrator/index.php?option=com_mapaviajes&task=raffle.csv', http_build_query(['min' => '1', $rt => '1'])); 
afirma(!str_contains($ana->post('/administrator/index.php?option=com_mapaviajes&task=raffle.csv', http_build_query(['min' => '1', $rt => '1']))['headers']['content-type'] ?? '', 'text/csv'), 'ana no puede descargar');
$r = $admin->post('/administrator/index.php?option=com_mapaviajes&task=raffle.draw', http_build_query(['from' => '', 'to' => '', 'min' => '1', 'album' => '', 'winners' => '2', $rt => '1']));
afirma(in_array($r['code'], [302, 303], true) && str_contains($r['location'], 'view=raffle'), 'sortear devuelve al listado');
$after = $admin->get('/administrator/index.php?option=com_mapaviajes&view=raffle');
afirma(str_contains($after['body'], 'mvA-winners') && substr_count($after['body'], '<li><strong>') === 2, 'se muestran exactamente 2 ganadores');
afirma(!str_contains($admin->get('/administrator/index.php?option=com_mapaviajes&view=raffle')['body'], 'mvA-winners'), 'y solo se muestran una vez');
$ev = $db->filas("SELECT event, detail FROM $EV WHERE event IN ('raffle_draw', 'raffle_export') ORDER BY id DESC LIMIT 2");
afirma(count($ev) === 2 && !preg_grep('/@|pd_/', array_column($ev, 'detail')), 'queda constancia en el registro (solo numeros, ningun dato personal)');
// the draw itself
$people = [['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4]]; $seen = [];
for ($i = 0; $i < 400; $i++) { $w = RaffleService::draw($people, 2); $ids = array_column($w, 'id'); if (count($ids) !== 2 || $ids[0] === $ids[1]) { $seen = null; break; } foreach ($ids as $x) { $seen[$x] = ($seen[$x] ?? 0) + 1; } }
afirma($seen !== null && count($seen) === 4 && min($seen) > 140, 'el sorteo no repite personas y reparte parejo (400 sorteos: ' . json_encode($seen) . ')');
afirma(count(RaffleService::draw($people, 99)) === 4 && RaffleService::draw($people, 0) === [] && RaffleService::draw([], 3) === [], 'pedir mas ganadores que participantes, cero o una lista vacia no rompe nada');
$cells = ['=1+1', '+1', '-1', '@x', "\tx", 'ok', 'a"b'];
afirma(array_map([RaffleService::class, 'cell'], $cells) === ['"\'=1+1"', '"\'+1"', '"\'-1"', '"\'@x"', "\"'\tx\"", '"ok"', '"a""b"'], 'celdas CSV: formulas neutralizadas, comillas duplicadas');

seccion('Correos a quien envia el viaje');
$mails = fn () => array_values(array_filter(glob($maildir . '/*.eml') ?: []));
$flush = function () use ($mails) { foreach ($mails() as $f) { @unlink($f); } };
$db->exec("UPDATE $X SET params = :p WHERE element = 'com_mapaviajes' AND type = 'component'", [':p' => json_encode(array_merge($params(), ['review_first' => 1, 'notify_admin' => 0, 'notify_author' => 1]))]);
$anaId = (int) $db->uno("SELECT id FROM $U WHERE username = 'ana'"); $anaMail = (string) $db->uno("SELECT email FROM $U WHERE id = :i", [':i' => $anaId]);
$db->exec("INSERT INTO $T (album_id, user_id, title, place, lat, lon, allow_comments, notify, published, photo_count, created) VALUES ((SELECT id FROM " . $db->t('mapaviajes_albums') . " ORDER BY id LIMIT 1), :u, 'Mi viaje a Gante', 'Gante', 51.05, 3.7, 1, 0, 0, 1, now())", [':u' => $anaId]);
$tid = (int) $db->uno("SELECT MAX(id) FROM $T WHERE user_id = :u", [':u' => $anaId]);
$p = $admin->get('/administrator/index.php?option=com_mapaviajes&view=trips'); preg_match('/name="([a-f0-9]{32})" value="1"/', $p['body'], $m); $tt = $m[1] ?? '';
$act = fn (string $task) => $admin->post('/administrator/index.php?option=com_mapaviajes&view=trips', http_build_query(['task' => $task, 'cid' => [$tid], 'boxchecked' => 1, $tt => '1']));
$flush(); $act('trips.publish'); usleep(300000);
$files = $mails(); $mail = $files ? (string) file_get_contents($files[0]) : '';
afirma(count($files) === 1 && stripos($mail, 'To: ' . $anaMail) !== false || stripos($mail, $anaMail) !== false, 'publicar: sale un correo a la autora');
afirma(str_contains($mail, 'Mi viaje a Gante') && str_contains($mail, 'trip=' . $tid), 'con el titulo y un enlace a SU viaje en el mapa');
afirma(substr_count($mail, 'http') === 1, 'un solo enlace en todo el correo');
$flush(); $act('trips.publish'); usleep(200000); afirma(count($mails()) === 0, 'publicar otra vez lo ya publicado no manda otro correo');
$act('trips.trash'); usleep(300000); $files = $mails(); $mail = $files ? (string) file_get_contents($files[0]) : '';
afirma(count($files) === 1 && str_contains($mail, 'Mi viaje a Gante') && !str_contains($mail, 'trip='), 'despublicar (papelera): correo neutro, sin enlace');
$flush(); $db->exec("UPDATE $X SET params = :p WHERE element = 'com_mapaviajes' AND type = 'component'", [':p' => json_encode(array_merge($params(), ['notify_author' => 0]))]);
$act('trips.report'); usleep(200000); afirma(count($mails()) === 0, 'con el aviso a la autora apagado no se manda nada');
$db->exec("UPDATE $X SET params = :p WHERE element = 'com_mapaviajes' AND type = 'component'", [':p' => json_encode(array_merge($params(), ['notify_author' => 1]))]);
$db->exec("UPDATE $U SET name = :n WHERE id = :i", [':n' => "Ana\r\nBcc: spam@example.test", ':i' => $anaId]);
$act('trips.publish'); usleep(300000); $files = $mails(); $mail = $files ? (string) file_get_contents($files[0]) : '';
afirma(!preg_match('/^Bcc:/mi', $mail), 'un nombre con saltos de linea no puede colar cabeceras en el correo');
$db->exec("UPDATE $U SET name = 'Ana' WHERE id = :i", [':i' => $anaId]);
$db->exec("UPDATE $U SET block = 1 WHERE id = :i", [':i' => $anaId]); $flush(); $act('trips.trash'); usleep(200000); $act('trips.publish'); usleep(200000);
afirma(count($mails()) === 0, 'a una cuenta bloqueada no se le escribe');
$db->exec("UPDATE $U SET block = 0 WHERE id = :i", [':i' => $anaId]);

seccion('Correo del podio (apagado por defecto)');
passthru('php ' . escapeshellarg(__DIR__ . '/lib/semilla-podio.php'));
$flush(); $ana->get('/index.php?option=com_mapaviajes&task=ranking.data&format=json'); usleep(300000);
afirma(count($mails()) === 0, 'con el aviso del podio apagado, cerrar una semana no escribe a nadie');
passthru('php ' . escapeshellarg(__DIR__ . '/lib/semilla-podio.php'));
$db->exec("UPDATE $X SET params = :p WHERE element = 'com_mapaviajes' AND type = 'component'", [':p' => json_encode(array_merge($params(), ['notify_podium' => 1]))]);
$flush(); $ana->get('/index.php?option=com_mapaviajes&task=ranking.data&format=json'); usleep(500000);
$all = array_map('file_get_contents', $mails()); $to = [];
foreach ($all as $m) { if (preg_match('/^To:\s*(\S+)/mi', $m, $mm)) { $to[] = strtolower($mm[1]); } }
sort($to);
afirma(count($all) === 3 && $to === [strtolower($anaMail), 'pd_alba@example.test', 'pd_bruno@example.test'], 'encendido: un correo a cada una de las tres personas del podio (' . implode(',', $to) . ')');
afirma(count(array_filter($all, fn ($m) => str_contains($m, 'puesto 1') || str_contains($m, 'number 1'))) === 1, 'y solo una dice que quedo la primera');
$flush(); $ana->get('/index.php?option=com_mapaviajes&task=ranking.data&format=json'); usleep(200000);
afirma(count($mails()) === 0, 'una semana ya cerrada no vuelve a avisar');
$db->exec("UPDATE $X SET params = :p WHERE element = 'com_mapaviajes' AND type = 'component'", [':p' => json_encode(array_merge($params(), ['notify_podium' => 0]))]);

// tidy
$db->exec("DELETE FROM $T WHERE id = :i", [':i' => $tid]);
$db->exec("UPDATE $U SET block = 0 WHERE username LIKE 'pd_%'");
$db->exec("UPDATE $X SET params = :p WHERE element = 'com_mapaviajes' AND type = 'component'", [':p' => json_encode(array_merge($params(), ['review_first' => 1, 'comments_provider' => 'none', 'notify_author' => 1]))]);
fin();
