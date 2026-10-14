<?php
declare(strict_types=1);
/** Live tests of "follow a traveller": who can follow whom, tampering, limits, the news state, the weekly summary mail (HTML, no links, logo, confidentiality). */
require __DIR__ . '/lib/http.php';

$base = getenv('MV_BASE') ?: 'http://127.0.0.1:8081';
$db   = new Bd(getenv('MV_PREFIX') ?: 'jt_');
$store = getenv('MV_STORAGE') ?: '';
$maildir = getenv('MV_MAILDIR') ?: '/tmp/mv-mails';
$T = $db->t('mapaviajes_trips'); $F = $db->t('mapaviajes_follows'); $PR = $db->t('mapaviajes_prefs'); $U = $db->t('users'); $A = $db->t('mapaviajes_albums');
$db->setParams(['trips_per_hour' => 1000, 'trips_per_day' => 1000, 'review_first' => 0, 'notify_admin' => 0, 'notify_follows' => 1]);
$db->exec("DELETE FROM $F"); $db->exec("DELETE FROM $PR WHERE user_id = 0 OR user_id IN (SELECT id FROM $U WHERE username IN ('ana','beto'))");
$mails = fn () => array_values(array_filter(glob($maildir . '/*.eml') ?: []));
$flush = function () use ($mails) { foreach ($mails() as $f) { @unlink($f); } };

$guest = new Cliente($base, 'g'); $ana = new Cliente($base, 'ana'); $beto = new Cliente($base, 'beto');
afirma($ana->login('ana', getenv('MV_ANA') ?: 'cambia-esta-clave-de-prueba'), 'ana inicia sesion');
afirma($beto->login('beto', getenv('MV_BETO') ?: 'cambia-esta-clave-de-prueba'), 'beto inicia sesion');
$oa = $ana->opciones(); $ob = $beto->opciones();
$anaId = (int) $db->uno("SELECT id FROM $U WHERE username = 'ana'"); $betoId = (int) $db->uno("SELECT id FROM $U WHERE username = 'beto'");

$make = function (Cliente $c, array $o, string $title) use ($db, $T): int {
	$f = ['trip_year' => '2024', 'album_id' => (string) $o['albumId'], 'title' => $title, 'place' => 'Lisboa', 'lat' => '38.72', 'lon' => '-9.14', 'allow_comments' => '1', 'notify' => '0', 'website' => '', $o['token'] => '1'];
	$ct = ''; $body = multipart($f, [['field' => 'photos[]', 'filename' => 'a.jpg', 'type' => 'image/jpeg', 'data' => jpegBytes(900, 600)]], $ct);
	$r = $c->post('/index.php?option=com_mapaviajes&task=trip.submit&format=json', $body, ['X-Requested-With: XMLHttpRequest', "Content-Type: $ct"]);
	return (int) (json_decode($r['body'], true)['id'] ?? 0);
};
$idB = $make($beto, $ob, 'Viaje de Beto'); $idA = $make($ana, $oa, 'Viaje de Ana');
afirma($idB > 0 && $idA > 0, 'viajes de beto y de ana publicados');
$data = fn (Cliente $c, array $o) => json_decode($c->get('/index.php?option=com_mapaviajes&task=trips.data&album=' . (int) $o['albumId'] . '&format=json')['body'], true) ?: [];
$keyOf = function (array $d, string $title) { foreach ($d['trips'] ?? [] as $t) { if ($t['t'] === $title) { return $t['u']; } } return ''; };
$kB = $keyOf($data($ana, $oa), 'Viaje de Beto'); $kA = $keyOf($data($ana, $oa), 'Viaje de Ana');
afirma(preg_match('/^[a-f0-9]{12}$/', $kB) === 1 && $kA !== $kB, 'cada persona viaja con una clave opaca (no su id)');

$url = '/index.php?option=com_mapaviajes&task=follow.toggle&format=json'; $xhr = ['X-Requested-With: XMLHttpRequest'];
$tog = fn (Cliente $c, array $o, $key, bool $x = true, bool $t = true) => $c->post($url, http_build_query(['u' => $key] + ($t ? [$o['token'] => '1'] : [])), $x ? $xhr : []);
$j = fn (array $r) => json_decode($r['body'], true) ?: [];

seccion('Puertas');
$guest->get('/index.php?option=com_users&view=login');
afirma($tog($guest, ['token' => str_repeat('a', 32)], $kB)['code'] === 403, 'invitado: 403');
afirma($tog($ana, $oa, $kB, false)['code'] === 403, 'sin X-Requested-With: 403');
afirma($tog($ana, $oa, $kB, true, false)['code'] === 403, 'sin token CSRF: 403');
afirma($ana->get($url)['code'] === 405, 'GET: 405');
afirma((int) $db->uno("SELECT COUNT(*) FROM $F") === 0, 'nada se guarda con las peticiones rechazadas');
foreach (['', 'zzzzzzzzzzzz', 'ABCDEF123456', str_repeat('a', 12), str_repeat('a', 40), "$kB\0", '../x', $kA] as $bad) { $r = $tog($ana, $oa, $bad); afirma(in_array($r['code'], [404, 400, 403], true), 'clave rara [' . addcslashes(substr($bad, 0, 14), "\0") . '] rechazada (' . $r['code'] . ')'); }
afirma((int) $db->uno("SELECT COUNT(*) FROM $F") === 0, 'ni claves raras ni seguirse a una misma crean filas');

seccion('Seguir y dejar de seguir');
$r = $tog($ana, $oa, $kB); $x = $j($r);
afirma($r['code'] === 200 && ($x['following'] ?? null) === true && in_array($kB, $x['follow']['keys'] ?? [], true), 'ana sigue a beto');
afirma((int) $db->uno("SELECT COUNT(*) FROM $F WHERE follower_id = :a AND followed_id = :b", [':a' => $anaId, ':b' => $betoId]) === 1, 'queda una fila');
afirma(in_array($kB, $data($ana, $oa)['follow']['keys'] ?? [], true), 'los datos del mapa traen a quien sigue');
afirma(!in_array($kB, $data($beto, $ob)['follow']['keys'] ?? [], true), 'beto no ve que ana le sigue (cada cual ve solo lo suyo)');
$x = $j($tog($ana, $oa, $kB));
afirma(($x['following'] ?? null) === false && (int) $db->uno("SELECT COUNT(*) FROM $F") === 0, 'pulsar otra vez deja de seguir (sin duplicados)');
$tog($ana, $oa, $kB);
// the limit
$db->exec("INSERT INTO $F (follower_id, followed_id, created) SELECT :a, g, NOW() FROM generate_series(1000000, 1000298) AS g", [':a' => $anaId]);
$tog($ana, $oa, $kB);
$tog($ana, $oa, $kB); // back to 300 rows: following beto again would be the 301st
$n = (int) $db->uno("SELECT COUNT(*) FROM $F WHERE follower_id = :a", [':a' => $anaId]);
$r = $tog($ana, $oa, $kB); $after = (int) $db->uno("SELECT COUNT(*) FROM $F WHERE follower_id = :a", [':a' => $anaId]);
afirma($n <= 300 && $after <= 300, 'nadie pasa de 300 seguidos (' . $n . ' -> ' . $after . ')');
$db->exec("DELETE FROM $F WHERE followed_id >= 1000000"); $db->exec("DELETE FROM $F"); $tog($ana, $oa, $kB);

seccion('Resumen semanal por correo');
$dig = fn (Cliente $c, array $o, $on) => $c->post('/index.php?option=com_mapaviajes&task=follow.digest&format=json', http_build_query(['on' => $on, $o['token'] => '1']), $xhr);
afirma($dig($ana, $oa, '2')['code'] === 400 && $dig($ana, $oa, 'x')['code'] === 400, 'el interruptor solo acepta 0 o 1');
$flush();
afirma($dig($ana, $oa, '1')['code'] === 200 && (int) $db->uno("SELECT digest FROM $PR WHERE user_id = :a", [':a' => $anaId]) === 1, 'ana activa el resumen semanal');
$db->exec("DELETE FROM $PR WHERE user_id = 0");
$data($ana, $oa); usleep(600000);
$files = $mails(); $raw = $files ? (string) file_get_contents($files[0]) : ''; $body = correoTexto($raw);
afirma(count($files) === 1 && stripos($raw, (string) $db->uno("SELECT email FROM $U WHERE id = :a", [':a' => $anaId])) !== false, 'sale UN correo, a ana');
afirma(str_contains($body, 'beto') || str_contains($body, 'Beto'), 'dice de quien son los viajes nuevos');
afirma(!str_contains($body, 'Viaje de Beto'), 'pero no el texto de los viajes');
afirma(!str_contains($body, 'http') && !str_contains($body, 'www.') && !str_contains($body, '<a '), 'SIN enlaces ni direcciones web');
afirma(str_contains($raw, 'text/html') && str_contains($raw, 'text/plain') && str_contains($body, '<table'), 'es HTML con su version en texto plano');
afirma(stripos($body, 'confidencialidad') !== false || stripos($body, 'Confidentiality') !== false, 'con el aviso de confidencialidad abajo');
afirma(stripos($body, 'Resumen semanal') !== false || stripos($body, 'Weekly summary') !== false || stripos($body, 'desmarca') !== false || stripos($body, 'untick') !== false, 'y como dejar de recibirlo (sin enlace)');
$flush(); $data($ana, $oa); usleep(400000);
afirma(count($mails()) === 0, 'cargar el mapa otra vez no manda otro (un resumen por semana)');
$db->exec("DELETE FROM $PR WHERE user_id = 0"); $data($ana, $oa); usleep(400000);
afirma(count($mails()) === 0, 'ni siquiera si el control general se reinicia: ya se le mando esta semana');
$db->exec("UPDATE $PR SET digest_sent = NOW() - INTERVAL '8 days' WHERE user_id = :a", [':a' => $anaId]); $db->exec("DELETE FROM $PR WHERE user_id = 0");
$dig($ana, $oa, '0'); $flush(); $data($ana, $oa); usleep(400000);
afirma(count($mails()) === 0, 'con el resumen apagado no se escribe a nadie');
$dig($ana, $oa, '1'); $db->exec("UPDATE $PR SET digest_sent = NOW() - INTERVAL '8 days' WHERE user_id = :a", [':a' => $anaId]); $db->exec("DELETE FROM $PR WHERE user_id = 0");
$db->setParams(['notify_follows' => 0]); $flush(); $data($ana, $oa); usleep(400000);
afirma(count($mails()) === 0, 'con el interruptor general del administrador apagado no sale nada');
$db->setParams(['notify_follows' => 1]);
$db->exec("DELETE FROM $F"); $db->exec("DELETE FROM $PR WHERE user_id = 0"); $db->exec("UPDATE $PR SET digest_sent = NULL WHERE user_id = :a", [':a' => $anaId]); $flush(); $data($ana, $oa); usleep(400000);
afirma(count($mails()) === 0, 'sin seguir a nadie no hay nada que resumir');
$tog($ana, $oa, $kB); $db->exec("UPDATE $U SET block = 1 WHERE id = :a", [':a' => $anaId]); $db->exec("DELETE FROM $PR WHERE user_id = 0"); $db->exec("UPDATE $PR SET digest_sent = NULL WHERE user_id = :a", [':a' => $anaId]);
$beto->get('/index.php?option=com_mapaviajes&task=trips.data&album=' . (int) $ob['albumId'] . '&format=json'); usleep(400000);
afirma(count($mails()) === 0, 'a una cuenta bloqueada no se le escribe');
$db->exec("UPDATE $U SET block = 0 WHERE id = :a", [':a' => $anaId]);

seccion('Logo en el correo y privacidad');
if ($store !== '') {
	$png = $store . '/photos/logo_seguir01.png'; file_put_contents($png, pngBytes(200, 100)); $db->exec("UPDATE $A SET logo = 'logo_seguir01.png' WHERE id = :i", [':i' => $oa['albumId']]);
	$db->exec("DELETE FROM $PR WHERE user_id = 0"); $db->exec("UPDATE $PR SET digest_sent = NULL WHERE user_id = :a", [':a' => $anaId]); $flush();
	$data($ana, $oa); usleep(600000); $files = $mails(); $raw = $files ? (string) file_get_contents($files[0]) : '';
	afirma(count($files) === 1 && stripos($raw, 'Content-ID: <mvlogo>') !== false && str_contains(correoTexto($raw), 'cid:mvlogo'), 'el logo del mapa va DENTRO del correo (adjunto incrustado, no se descarga de internet)');
	$db->exec("UPDATE $A SET logo = '' WHERE id = :i", [':i' => $oa['albumId']]); @unlink($png);
}
$db->exec("DELETE FROM $F"); $db->exec("DELETE FROM $PR WHERE user_id IN (0, :a, :b)", [':a' => $anaId, ':b' => $betoId]);
$db->exec("DELETE FROM $T WHERE id IN (:a, :b)", [':a' => $idA, ':b' => $idB]);
fin();
