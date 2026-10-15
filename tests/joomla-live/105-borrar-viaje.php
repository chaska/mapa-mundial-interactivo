<?php
declare(strict_types=1);
/** Live tests of deleting a trip from the map: its author deletes their own, administrators any; nobody else; rows, files and reactions all go. */
require __DIR__ . '/lib/http.php';

$base = getenv('MV_BASE') ?: 'http://127.0.0.1:8081';
$db   = new Bd(getenv('MV_PREFIX') ?: 'jt_');
$store = getenv('MV_STORAGE') ?: '';
$T = $db->t('mapaviajes_trips'); $P = $db->t('mapaviajes_photos'); $R = $db->t('mapaviajes_reactions');
$db->setParams(['trips_per_hour' => 1000, 'trips_per_day' => 1000, 'review_first' => 0, 'notify_admin' => 0, 'reactions_per_day' => 300]);
$db->exec("DELETE FROM " . $db->t('mapaviajes_events'));

$guest = new Cliente($base, 'g'); $ana = new Cliente($base, 'ana'); $beto = new Cliente($base, 'beto'); $adm = new Cliente($base, 'adm');
afirma($ana->login('ana', getenv('MV_ANA') ?: 'cambia-esta-clave-de-prueba'), 'ana inicia sesion');
afirma($beto->login('beto', getenv('MV_BETO') ?: 'cambia-esta-clave-de-prueba'), 'beto inicia sesion');
afirma($adm->login('admin', getenv('MV_ADMIN') ?: 'cambia-esta-clave-de-prueba'), 'el administrador entra en el mapa');
$oa = $ana->opciones(); $ob = $beto->opciones(); $oadm = $adm->opciones();
afirma(($oa['canDelete'] ?? true) === false && ($oadm['canDelete'] ?? false) === true, 'el mapa sabe quien puede borrar cualquier viaje (ana no, el administrador si)');

$make = function (Cliente $c, array $o, string $title) use ($db, $T): int {
	$f = ['trip_year' => '2024', 'album_id' => (string) $o['albumId'], 'title' => $title, 'place' => 'Lisboa', 'lat' => '38.72', 'lon' => '-9.14', 'allow_comments' => '1', 'notify' => '0', 'website' => '', 'rights' => '1', $o['token'] => '1'];
	$ct = ''; $body = multipart($f, [['field' => 'photos[]', 'filename' => 'a.jpg', 'type' => 'image/jpeg', 'data' => jpegBytes(900, 600)], ['field' => 'photos[]', 'filename' => 'b.jpg', 'type' => 'image/jpeg', 'data' => jpegBytes(700, 500)]], $ct);
	$r = $c->post('/index.php?option=com_mapaviajes&task=trip.submit&format=json', $body, ['X-Requested-With: XMLHttpRequest', "Content-Type: $ct"]);
	if (!isset(json_decode($r['body'], true)['id'])) { fwrite(STDERR, 'SUBMIT: ' . substr($r['body'], 0, 200) . "\n"); }
	return (int) (json_decode($r['body'], true)['id'] ?? 0);
};
$url = '/index.php?option=com_mapaviajes&task=trip.remove&format=json';
$xhr = ['X-Requested-With: XMLHttpRequest'];
$del = fn (Cliente $c, array $o, $id, bool $x = true, bool $t = true) => $c->post($url, http_build_query(['id' => $id] + ($t ? [$o['token'] => '1'] : [])), $x ? $xhr : []);
$names = fn (int $id) => array_column($db->filas("SELECT filename FROM $P WHERE trip_id = :t", [':t' => $id]), 'filename');
$exists = fn (int $id) => (int) $db->uno("SELECT COUNT(*) FROM $T WHERE id = :t", [':t' => $id]);

$idA = $make($ana, $oa, 'Borrar A'); $idB = $make($beto, $ob, 'Borrar B'); $idP = $make($ana, $oa, 'Borrar pendiente');
afirma($idA > 0 && $idB > 0 && $idP > 0, 'tres viajes creados');
$db->exec("UPDATE $T SET published = 0 WHERE id = :i", [':i' => $idP]);
$files = $names($idA); afirma(count($files) === 2 && $store !== '' && is_file($store . '/photos/' . $files[0]), 'el viaje A tiene 2 fotos en disco');
$db->exec("INSERT INTO $R (trip_id, user_id, kind, active, created, changed) VALUES (:t, (SELECT id FROM " . $db->t('users') . " WHERE username = 'beto'), 'like', 1, NOW(), NOW())", [':t' => $idA]);

seccion('Puertas');
$guest->get('/index.php?option=com_users&view=login');
afirma($del($guest, ['token' => str_repeat('a', 32)], $idA)['code'] === 403, 'invitado: 403');
afirma($del($ana, $oa, $idA, false)['code'] === 403, 'sin X-Requested-With: 403');
afirma($del($ana, $oa, $idA, true, false)['code'] === 403, 'sin token CSRF: 403');
afirma($ana->get($url)['code'] === 405, 'GET: 405');
afirma($exists($idA) === 1, 'nada se borra con las peticiones rechazadas');
foreach (['', '0', '-1', '1e3', '1 OR 1=1', "1\0", '99999999999', 'abc'] as $x) { $c = $del($ana, $oa, $x)['code']; afirma(in_array($c, [404, 403, 400], true), 'id raro [' . addcslashes($x, "\0") . '] rechazado (' . $c . ')'); }
afirma($exists($idA) === 1 && $exists($idB) === 1, 'los ids raros no borran nada');

seccion('Cada cual lo suyo');
$r = $del($beto, $ob, $idA);
afirma($r['code'] === 404 && $exists($idA) === 1, 'beto no puede borrar el viaje de ana (404, sigue ahi)');
$r = $del($ana, $oa, $idB);
afirma($r['code'] === 404 && $exists($idB) === 1, 'ana no puede borrar el viaje de beto');
$r = $del($ana, $oa, 987654);
afirma($r['code'] === 404, 'un viaje que no existe da el mismo 404');

seccion('El autor borra el suyo');
$r = $del($ana, $oa, $idA); $j = json_decode($r['body'], true) ?: [];
afirma($r['code'] === 200 && ($j['ok'] ?? false) === true, 'ana borra su viaje publicado');
afirma($exists($idA) === 0 && (int) $db->uno("SELECT COUNT(*) FROM $P WHERE trip_id = :t", [':t' => $idA]) === 0 && (int) $db->uno("SELECT COUNT(*) FROM $R WHERE trip_id = :t", [':t' => $idA]) === 0, 'desaparecen el viaje, sus fotos (filas) y sus reacciones');
clearstatcache();
afirma(!is_file($store . '/photos/' . $files[0]) && !is_file($store . '/photos/' . $files[1]), 'desaparecen tambien los archivos de las fotos');
$r = $del($ana, $oa, $idP);
afirma($r['code'] === 200 && $exists($idP) === 0, 'tambien puede borrar uno suyo que aun esta pendiente de revision');
$r = $del($ana, $oa, $idA);
afirma($r['code'] === 404, 'borrarlo otra vez da 404 (no hay doble borrado)');
$list = $ana->get('/index.php?option=com_mapaviajes&task=trips.data&album=' . (int) $oa['albumId'] . '&format=json');
afirma(!str_contains($list['body'], 'Borrar A') && !str_contains($list['body'], 'Borrar pendiente'), 'el viaje borrado ya no sale en los datos del mapa');

seccion('El administrador');
$files = $names($idB);
$r = $del($adm, $oadm, $idB);
afirma($r['code'] === 200 && $exists($idB) === 0, 'el administrador borra el viaje de beto');
clearstatcache(); afirma(!is_file($store . '/photos/' . $files[0]), 'y sus archivos');
afirma((int) $db->uno("SELECT COUNT(*) FROM " . $db->t('mapaviajes_events') . " WHERE event = 'trip_removed'") === 3, 'quedan anotados los 3 borrados (solo codigo, sin contenido)');
$db->setParams(['review_first' => 1]);
fin();
