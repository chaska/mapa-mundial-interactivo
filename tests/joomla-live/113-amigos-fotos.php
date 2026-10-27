<?php
declare(strict_types=1);
/** Live tests: friends (request, accept, cancel, block), "friends only" photos (server-side visibility), at least one public photo, adding photos to a live trip, moderators see everything. */
require __DIR__ . '/lib/http.php';

$base = getenv('MV_BASE') ?: 'http://127.0.0.1:8081';
$db   = new Bd(getenv('MV_PREFIX') ?: 'jt_');
$maildir = getenv('MV_MAILDIR') ?: '/tmp/mv-mails';
$T = $db->t('mapaviajes_trips'); $P = $db->t('mapaviajes_photos'); $F = $db->t('mapaviajes_friends'); $B = $db->t('mapaviajes_blocks');
$db->setParams(['trips_per_hour' => 1000, 'trips_per_day' => 1000, 'review_first' => 0, 'notify_admin' => 0, 'max_photos' => 5, 'sender_line' => '']);
$db->exec("DELETE FROM $F"); $db->exec("DELETE FROM $B"); $db->exec("DELETE FROM " . $db->t('mapaviajes_events'));
$mails = fn () => array_values(array_filter(glob($maildir . '/*.eml') ?: []));
$flush = function () use ($mails) { foreach ($mails() as $f) { @unlink($f); } };

$ana = new Cliente($base, 'ana'); $beto = new Cliente($base, 'beto'); $adm = new Cliente($base, 'adm'); $admB = new Cliente($base, 'admb'); $guest = new Cliente($base, 'g');
afirma($ana->login('ana', getenv('MV_ANA') ?: 'cambia-esta-clave-de-prueba'), 'ana inicia sesion');
afirma($beto->login('beto', getenv('MV_BETO') ?: 'cambia-esta-clave-de-prueba'), 'beto inicia sesion');
afirma($adm->login('admin', getenv('MV_ADMIN') ?: 'cambia-esta-clave-de-prueba'), 'el administrador entra en el mapa');
afirma($admB->login('admin', getenv('MV_ADMIN') ?: 'cambia-esta-clave-de-prueba', true), 'y en el panel de administracion');
$oa = $ana->opciones(); $ob = $beto->opciones(); $oadm = $adm->opciones();
$xhr = ['X-Requested-With: XMLHttpRequest'];

$send = function (Cliente $c, array $o, string $title, array $priv, int $n = 0) {
	$n = $n ?: count($priv);
	$f = ['trip_year' => '2024', 'album_id' => (string) $o['albumId'], 'title' => $title, 'place' => 'Lisboa', 'lat' => '38.72', 'lon' => '-9.14', 'allow_comments' => '0', 'notify' => '0', 'website' => '', 'rights' => '1', 'priv' => $priv, $o['token'] => '1'];
	$files = []; for ($i = 0; $i < $n; $i++) { $files[] = ['field' => 'photos[]', 'filename' => "p$i.jpg", 'type' => 'image/jpeg', 'data' => jpegBytes(700 + $i * 10, 500)]; }
	$ct = ''; $body = multipart($f, $files, $ct);
	return $c->post('/index.php?option=com_mapaviajes&task=trip.submit&format=json', $body, ['X-Requested-With: XMLHttpRequest', "Content-Type: $ct"]);
};

$edit = function (Cliente $c, array $o, int $trip, array $delete = [], array $ep = [], int $n = 0, array $newPriv = [], bool $rights = true, bool $x = true, bool $t = true, array $extra = []) {
	$f = $extra + ['id' => (string) $trip, 'delete' => array_map('strval', $delete)] + ($n ? ['captions' => array_fill(0, $n, 'Otra'), 'priv' => $newPriv ?: array_fill(0, $n, '0')] : []) + ($t ? [$o['token'] => '1'] : []) + ($rights ? ['rights' => '1'] : []);
	foreach ($ep as $pid => $v) { $f['ep[' . $pid . ']'] = (string) $v; }
	$files = []; for ($i = 0; $i < $n; $i++) { $files[] = ['field' => 'photos[]', 'filename' => "n$i.jpg", 'type' => 'image/jpeg', 'data' => jpegBytes(640 + $i * 7, 480)]; }
	$ct = ''; $body = multipart($f, $files, $ct);
	return $c->post('/index.php?option=com_mapaviajes&task=trip.photos&format=json', $body, ($x ? ['X-Requested-With: XMLHttpRequest'] : []) + [1 => "Content-Type: $ct"]);
};

seccion('Al menos una foto publica');
$r = $send($ana, $oa, 'Todo privado', ['1', '1']);
afirma($r['code'] === 422 && (int) $db->uno("SELECT COUNT(*) FROM $T WHERE title = 'Todo privado'") === 0, 'todas las fotos «solo amigos»: 422 y no se crea nada');
$r = $send($ana, $oa, 'Una sola privada', ['1']);
afirma($r['code'] === 422, 'una unica foto no puede ser privada');
$r = $send($ana, $oa, 'Mixto', ['0', '1', '1']); $idA = (int) (json_decode($r['body'], true)['id'] ?? 0);
afirma($r['code'] === 201 && $idA > 0, 'tres fotos, una publica y dos «solo amigos»: 201');
$ph = $db->filas("SELECT id, friends_only FROM $P WHERE trip_id = :t ORDER BY ordering", [':t' => $idA]);
afirma(count($ph) === 3 && array_column($ph, 'friends_only') === [0, 1, 1], 'se guarda cual es privada (' . json_encode(array_column($ph, 'friends_only')) . ')');
$pub = (int) $ph[0]['id']; $priv1 = (int) $ph[1]['id']; $priv2 = (int) $ph[2]['id'];
$dataOf = fn (Cliente $c) => json_decode($c->get('/index.php?option=com_mapaviajes&task=trips.data&album=' . $GLOBALS['oa']['albumId'])['body'], true);
$tripIn = function (array $d, int $id) { foreach ($d['trips'] ?? [] as $t) { if ((int) $t['id'] === $id) { return $t; } } return null; };

seccion('Quien no es amigo: chincheta si, fotos privadas no');
$tb = $tripIn($dataOf($beto), $idA);
afirma($tb !== null && $tb['p'] === 'Lisboa', 'beto (no amigo) ve el viaje en el mapa');
afirma($tb !== null && count($tb['ph']) === 1 && $tb['ph'][0]['id'] === $pub && ($tb['lk2'] ?? 0) === 2, 'solo recibe la foto publica y sabe que hay 2 mas (sin ids)');

$bodyB = json_encode($dataOf($beto)); afirma(!preg_match('/"id":(' . $priv1 . '|' . $priv2 . ')[,}]/', $bodyB), 'los ids de las fotos privadas NO viajan a beto');
$view = fn (Cliente $c, int $id) => $c->get('/index.php?option=com_mapaviajes&task=photo.view&id=' . $id)['code'];
$thumbGet = fn (Cliente $c, int $id) => $c->get('/index.php?option=com_mapaviajes&task=photo.view&id=' . $id . '&t=1');
$thumbsDir = rtrim((string) getenv('MV_STORAGE'), '/') . '/thumbs';
afirma($view($beto, $pub) === 200, 'beto ve la foto publica');
afirma($view($beto, $priv1) === 404 && $view($beto, $priv2) === 404, 'beto pidiendo la foto privada por su direccion: 404');
afirma($view($guest, $priv1) === 404, 'un invitado tampoco');
afirma($view($ana, $priv1) === 200, 'la autora ve las suyas');
afirma($view($adm, $priv1) === 200 && $view($adm, $priv2) === 200, 'el administrador ve TODAS aunque no sea amigo');
$ta = $tripIn($dataOf($adm), $idA);
afirma($ta !== null && count($ta['ph']) === 3, 'y el administrador recibe las 3 en el mapa');

seccion('Moderar: solo quien tiene el permiso propio, no los grupos que Joomla hereda');
$U = $db->t('users'); $UG = $db->t('user_usergroup_map'); $hash = password_hash('cambia-esta-clave-de-prueba', PASSWORD_BCRYPT);
foreach (['mv_pub' => 5, 'mv_mgr' => 6, 'mv_adm7' => 7] as $uname => $grp) {
	$db->exec("DELETE FROM $UG WHERE user_id IN (SELECT id FROM $U WHERE username = '$uname')"); $db->exec("DELETE FROM $U WHERE username = '$uname'");
	$db->exec("INSERT INTO $U (name, username, email, password, block, \"sendEmail\", \"registerDate\", params) VALUES ('$uname', '$uname', '$uname@example.test', '$hash', 0, 0, now(), '{}')");
	$uid2 = (int) $db->uno("SELECT id FROM $U WHERE username = '$uname'"); $db->exec("INSERT INTO $UG (user_id, group_id) VALUES ($uid2, $grp)");
}
$cPub = new Cliente($base, 'cpub'); $cMgr = new Cliente($base, 'cmgr'); $cAdm7 = new Cliente($base, 'cadm7');
afirma($cPub->login('mv_pub', 'cambia-esta-clave-de-prueba') && $cMgr->login('mv_mgr', 'cambia-esta-clave-de-prueba') && $cAdm7->login('mv_adm7', 'cambia-esta-clave-de-prueba'), 'entran un Publisher (5), un Manager (6) y un Administrator (7)');
afirma($view($cPub, $priv1) === 404 && $view($cMgr, $priv1) === 404, 'Publisher y Manager NO ven las fotos privadas de otros (Joomla les da «editar estado» por herencia, pero no es moderar aqui)');
afirma($view($cAdm7, $priv1) === 200, 'un Administrator (grupo 7) si: el permiso propio «Moderar en el mapa» se le da al instalar');
$tPub = $tripIn($dataOf($cPub), $idA); afirma($tPub !== null && ($tPub['lk2'] ?? 0) === 2 && count($tPub['ph']) === 1, 'y en el mapa el Publisher recibe solo la foto publica y el numero de privadas');
foreach (['mv_pub', 'mv_mgr', 'mv_adm7'] as $uname) { $db->exec("DELETE FROM $UG WHERE user_id IN (SELECT id FROM $U WHERE username = '$uname')"); $db->exec("DELETE FROM $U WHERE username = '$uname'"); }

seccion('Amistad: pedir, aceptar, cancelar');
$ua = $tb['u'] ?? ''; // opaque key of ana
$ubKey = ''; foreach ($dataOf($ana)['trips'] as $t) { if (($t['by'] ?? '') === 'beto') { $ubKey = $t['u']; } }
if ($ubKey === '') { $send($beto, $ob, 'Viaje de beto', ['0']); foreach ($dataOf($ana)['trips'] as $t) { if (($t['by'] ?? '') === 'beto') { $ubKey = $t['u']; } } }
afirma($ua !== '' && $ubKey !== '', 'cada uno conoce la clave opaca del otro');
$fr = fn (Cliente $c, array $o, string $act, array $f = [], bool $x = true, bool $t = true) => $c->post('/index.php?option=com_mapaviajes&task=friend.' . $act . '&format=json', http_build_query($f + ($t ? [$o['token'] => '1'] : [])), $x ? ['X-Requested-With: XMLHttpRequest'] : []);
afirma($fr($beto, $ob, 'request', ['u' => $ua], false)['code'] === 403, 'pedir amistad sin cabecera AJAX: 403');
afirma($fr($beto, $ob, 'request', ['u' => $ua], true, false)['code'] === 403, 'sin token CSRF: 403');
afirma($fr($guest, ['token' => str_repeat('a', 32)], 'request', ['u' => $ua])['code'] === 403, 'un invitado: 403');
afirma($beto->get('/index.php?option=com_mapaviajes&task=friend.request&format=json')['code'] === 405, 'GET: 405');
afirma($fr($beto, $ob, 'request', ['u' => 'zzzz'])['code'] === 404 && $fr($beto, $ob, 'request', ['u' => $ubKey])['code'] === 404, 'clave inventada y pedirte amistad a ti mismo: 404');
$flush();
$r = $fr($beto, $ob, 'request', ['u' => $ua]); $j = json_decode($r['body'], true);
afirma($r['code'] === 200 && ($j['result'] ?? '') === 'pending' && (int) $db->uno("SELECT COUNT(*) FROM $F WHERE status = 0") === 1, 'beto pide amistad a ana: queda pendiente');
afirma(count($j['friend']['outgoing'] ?? []) === 1, 'beto ve su solicitud enviada');
usleep(500000); $files = $mails(); $raw = $files ? (string) file_get_contents($files[0]) : ''; $body = correoTexto($raw);
afirma(count($files) === 1 && !preg_match('#https?://#i', $body) && str_contains($body, 'beto') && !str_contains($raw . $body, 'COM_MAPAVIAJES'), 'ana recibe UN correo de aviso, con el nombre de beto, sin enlaces ni claves sin traducir');
$flush(); $fr($beto, $ob, 'request', ['u' => $ua]);
afirma((int) $db->uno("SELECT COUNT(*) FROM $F") === 1 && count($mails()) === 0, 'pedirla otra vez no duplica ni manda otro correo');
$ja = json_decode($ana->get('/index.php?option=com_mapaviajes&task=trips.data&album=' . $oa['albumId'])['body'], true);
afirma(count($ja['friend']['incoming'] ?? []) === 1 && ($ja['friend']['incoming'][0]['n'] ?? '') === 'beto', 'ana ve la solicitud recibida con el nombre de beto');
afirma($tripIn($dataOf($beto), $idA)['lk2'] === 2, 'mientras esta pendiente beto sigue sin ver las privadas');
afirma($fr($beto, $ob, 'respond', ['u' => $ua, 'action' => 'accept'])['code'] === 404, 'beto no puede aceptar su propia solicitud: 404');
$r = $fr($ana, $oa, 'respond', ['u' => $ubKey, 'action' => 'accept']);
afirma($r['code'] === 200 && (int) $db->uno("SELECT status FROM $F") === 1, 'ana acepta: son amigos');
$tb = $tripIn($dataOf($beto), $idA);
afirma($tb !== null && count($tb['ph']) === 3 && ($tb['lk2'] ?? 0) === 0, 'beto ya recibe las 3 fotos');
afirma($view($beto, $priv1) === 200 && $view($beto, $priv2) === 200, 'y puede abrir las privadas');
$jb = $dataOf($beto); afirma(count($jb['friend']['friends'] ?? []) === 1, 'cada uno ve a su amigo');
$r = $fr($beto, $ob, 'cancel', ['u' => $ua]);
afirma($r['code'] === 200 && (int) $db->uno("SELECT COUNT(*) FROM $F") === 0, 'beto cancela la amistad: se borra');
afirma($view($beto, $priv1) === 404, 'y deja de ver las fotos privadas al instante');
$fr($beto, $ob, 'request', ['u' => $ua]); $fr($ana, $oa, 'respond', ['u' => $ubKey, 'action' => 'accept']);
$r = $fr($ana, $oa, 'cancel', ['u' => $ubKey]);
afirma($r['code'] === 200 && (int) $db->uno("SELECT COUNT(*) FROM $F") === 0, 'la otra parte tambien puede quitar la amistad');

seccion('Abuso de solicitudes: rechazadas y retiradas');
$db->exec("DELETE FROM $F"); $flush();
$fr($beto, $ob, 'request', ['u' => $ua]); usleep(300000); $flush();
$fr($ana, $oa, 'respond', ['u' => $ubKey, 'action' => 'decline']);
afirma((int) $db->uno("SELECT status FROM $F") === 2, 'rechazar deja la fila marcada como rechazada (no la borra)');
$r = $fr($beto, $ob, 'request', ['u' => $ua]);
afirma($r['code'] === 404 && (int) $db->uno("SELECT status FROM $F") === 2 && count($mails()) === 0, 'tras un rechazo, beto no puede volver a pedirla en 30 dias (404 neutro) ni se manda otro correo');
$fr($beto, $ob, 'block', ['u' => $ua]); $fr($beto, $ob, 'unblock', ['u' => $ua]); $flush();
$r = $fr($beto, $ob, 'request', ['u' => $ua]); usleep(300000);
afirma($r['code'] === 404 && (int) $db->uno("SELECT status FROM $F") === 2 && count($mails()) === 0, 'bloquear y desbloquear a quien te rechazo NO borra la espera de 30 dias: sigue 404 y sin correo');
$db->exec("DELETE FROM " . $db->t('mapaviajes_blocks'));
$r = $fr($ana, $oa, 'request', ['u' => $ubKey]); $j = json_decode($r['body'], true);
afirma($r['code'] === 200 && ($j['result'] ?? '') === 'pending' && (int) $db->uno("SELECT COUNT(*) FROM $F WHERE status = 1") === 0, 'una solicitud rechazada NO se convierte en amistad si luego la pide la otra persona');
$db->exec("DELETE FROM $F"); $flush();
$fr($beto, $ob, 'request', ['u' => $ua]); usleep(300000); afirma(count($mails()) === 1, 'primera solicitud: un correo'); $flush();
$fr($beto, $ob, 'cancel', ['u' => $ua]);
afirma((int) $db->uno("SELECT status FROM $F") === 3, 'retirar la solicitud deja la fila como retirada');
$r = $fr($beto, $ob, 'request', ['u' => $ua]); usleep(300000);
afirma($r['code'] === 200 && (int) $db->uno("SELECT status FROM $F") === 0 && count($mails()) === 0, 'volver a pedirla enseguida funciona pero NO manda otro correo (ni 10 al dia a la misma persona)');
$db->exec("UPDATE $F SET status = 2, answered = NOW() - INTERVAL '40 days'"); $flush();
$r = $fr($beto, $ob, 'request', ['u' => $ua]); usleep(300000);
afirma($r['code'] === 200 && (int) $db->uno("SELECT status FROM $F") === 0 && count($mails()) === 1, 'pasados los 30 dias de un rechazo se puede pedir otra vez y avisa de nuevo');
$db->exec("DELETE FROM $F"); $flush();

seccion('Rechazar, bloquear');
$fr($beto, $ob, 'request', ['u' => $ua]);
$r = $fr($ana, $oa, 'respond', ['u' => $ubKey, 'action' => 'decline']);
afirma($r['code'] === 200 && (int) $db->uno("SELECT COUNT(*) FROM $F WHERE status = 0") === 0 && (int) $db->uno("SELECT COUNT(*) FROM $F WHERE status = 2") === 1, 'rechazar cierra la solicitud (queda anotada como rechazada)');
$db->exec("DELETE FROM $F");
$fr($beto, $ob, 'request', ['u' => $ua]);
$r = $fr($ana, $oa, 'respond', ['u' => $ubKey, 'action' => 'block']);
afirma($r['code'] === 200 && (int) $db->uno("SELECT COUNT(*) FROM $B") === 1 && (int) $db->uno("SELECT COUNT(*) FROM $F") === 0, 'bloquear borra la solicitud y bloquea');
afirma($fr($beto, $ob, 'request', ['u' => $ua])['code'] === 404, 'beto ya no puede pedirle amistad a ana (404, sin decir por que)');
$fr($ana, $oa, 'unblock', ['u' => $ubKey]);
afirma((int) $db->uno("SELECT COUNT(*) FROM $B") === 0 && $fr($beto, $ob, 'request', ['u' => $ua])['code'] === 200, 'al desbloquear puede volver a pedirla');
$fr($ana, $oa, 'respond', ['u' => $ubKey, 'action' => 'decline']);

seccion('Editar el album: cambiar quien ve cada foto');
$pp = fn (Cliente $c, array $o, int $photo, string $fo, bool $x = true, bool $t = true) => $GLOBALS['edit']($c, $o, $GLOBALS['idA'], [], [$photo => $fo], 0, [], true, $x, $t);
afirma($pp($beto, $ob, $priv1, '0')['code'] === 404, 'beto no puede cambiar las fotos de ana: 404');
afirma($pp($ana, $oa, $priv1, '0', false)['code'] === 403 && $pp($ana, $oa, $priv1, '0', true, false)['code'] === 403, 'sin AJAX o sin token: 403');
afirma($pp($ana, $oa, $pub, '1')['code'] === 422 && (int) $db->uno("SELECT friends_only FROM $P WHERE id = :i", [':i' => $pub]) === 0, 'no se puede esconder la unica foto publica: 422');
afirma($pp($ana, $oa, $priv1, '0')['code'] === 200 && (int) $db->uno("SELECT friends_only FROM $P WHERE id = :i", [':i' => $priv1]) === 0, 'ana hace publica la segunda foto');
$tb = $tripIn($dataOf($beto), $idA);
afirma(count($tb['ph']) === 2 && ($tb['lk2'] ?? 0) === 1, 'beto (no amigo) ve ahora 2 y sabe que hay 1 mas');
afirma($pp($ana, $oa, $pub, '1')['code'] === 200, 'ya puede esconder la primera (queda otra publica)');
$tb = $tripIn($dataOf($beto), $idA);
afirma($tb['ph'][0]['id'] === $priv1, 'la miniatura (primera foto de la lista) es SIEMPRE una publica aunque la primera sea privada');
$ta = $tripIn($dataOf($ana), $idA);
afirma($ta['ph'][0]['id'] === $priv1 && count($ta['ph']) === 3, 'tambien para la autora (la miniatura es la publica) y recibe sus 3');
$pp($ana, $oa, $pub, '0'); $pp($ana, $oa, $priv1, '1');

seccion('Borrar fotos y anadir hasta 5');
$rm = fn (Cliente $c, array $o, int $photo) => $GLOBALS['edit']($c, $o, $GLOBALS['idA'], [$photo]);
afirma($rm($beto, $ob, $priv2)['code'] === 404, 'beto no puede borrar fotos de ana: 404');
$thumbGet($ana, $priv2); $before = count(glob($thumbsDir . '/*.webp') ?: []);
afirma($rm($ana, $oa, $priv2)['code'] === 200 && (int) $db->uno("SELECT COUNT(*) FROM $P WHERE trip_id = :t", [':t' => $idA]) === 2 && (int) $db->uno("SELECT photo_count FROM $T WHERE id = :t", [':t' => $idA]) === 2, 'ana borra una foto: quedan 2 y el contador se actualiza');
afirma(count(glob($thumbsDir . '/*.webp') ?: []) === $before - 1, 'y su miniatura guardada se borra con ella');
afirma($rm($ana, $oa, $pub)['code'] === 422, 'no puede borrar la unica publica mientras haya otras');
afirma($rm($ana, $oa, $priv1)['code'] === 200 && $rm($ana, $oa, $pub)['code'] === 422 && (int) $db->uno("SELECT COUNT(*) FROM $P WHERE trip_id = :t", [':t' => $idA]) === 1, 'tampoco la ultima foto del album');
$add = fn (Cliente $c, array $o, int $trip, int $n, array $priv = [], bool $rights = true) => $GLOBALS['edit']($c, $o, $trip, [], [], $n, $priv, $rights);
afirma($add($ana, $oa, $idA, 2, [], false)['code'] === 422, 'anadir fotos sin la casilla de derechos: 422');
afirma($add($beto, $ob, $idA, 1)['code'] === 404, 'beto no puede anadir fotos al album de ana: 404');
$r = $add($ana, $oa, $idA, 2, ['1', '1']); $j = json_decode($r['body'], true);
afirma($r['code'] === 200 && ($j['added'] ?? 0) === 2 && ($j['pending'] ?? true) === false && (int) $db->uno("SELECT photo_count FROM $T WHERE id = :t", [':t' => $idA]) === 3, 'ana anade 2 fotos privadas (con la publica que ya tiene): 3 fotos');
$r = $add($ana, $oa, $idA, 3);
afirma($r['code'] === 422 && (int) $db->uno("SELECT COUNT(*) FROM $P WHERE trip_id = :t", [':t' => $idA]) === 3, 'con 3 fotos no caben 3 mas (max 5): 422 y no se guarda nada');
$r = $add($ana, $oa, $idA, 2); afirma($r['code'] === 200 && (int) $db->uno("SELECT COUNT(*) FROM $P WHERE trip_id = :t", [':t' => $idA]) === 5, 'hasta llegar a 5');
afirma($add($ana, $oa, $idA, 1)['code'] === 422, 'la sexta no entra');

seccion('Miniaturas (listas y vistas previas)');
$thumbGet = fn (Cliente $c, int $id) => $c->get('/index.php?option=com_mapaviajes&task=photo.view&id=' . $id . '&t=1');
$full = $beto->get('/index.php?option=com_mapaviajes&task=photo.view&id=' . $pub); $small = $thumbGet($beto, $pub);
afirma($small['code'] === 200 && ($small['headers']['content-type'] ?? '') === 'image/webp' && strlen($small['body']) > 100 && strlen($small['body']) < strlen($full['body']), 'la miniatura de una foto publica sale mas pequena que la foto (' . strlen($small['body']) . ' < ' . strlen($full['body']) . ' bytes)');
afirma(str_contains((string) ($small['headers']['cache-control'] ?? ''), 'max-age=600') && str_contains((string) ($full['headers']['cache-control'] ?? ''), 'no-cache'), 'la miniatura se puede reutilizar unos minutos; la foto completa se revalida siempre');
$im = @imagecreatefromstring($small['body']); afirma($im !== false && max(imagesx($im), imagesy($im)) <= 320, 'y mide como mucho 320 px de lado');
afirma($thumbGet($beto, $priv1) === null || $thumbGet($beto, $priv1)['code'] === 404, 'pedir la miniatura de una foto privada sin ser amigo: 404 (la puerta es la misma)');
afirma($thumbGet($guest, $pub)['code'] === 404, 'y un invitado tampoco');
$thumbsDir = rtrim((string) getenv('MV_STORAGE'), '/') . '/thumbs';
afirma(is_dir($thumbsDir) && count(glob($thumbsDir . '/*.webp') ?: []) >= 1, 'la miniatura queda guardada en el disco privado para no volver a calcularla');
$nThumbs = count(glob($thumbsDir . '/*.webp') ?: []);

seccion('Un solo guardado: borrar + cambiar + anadir a la vez');
$cur = array_column($db->filas("SELECT id FROM $P WHERE trip_id = :t ORDER BY id", [':t' => $idA]), 'id');
$flip = 1 - (int) $db->uno("SELECT friends_only FROM $P WHERE id = :i", [':i' => (int) $cur[2]]);
$r = $edit($ana, $oa, $idA, [(int) $cur[0]], [(int) $cur[2] => $flip], 1, ['0']);
$j = json_decode($r['body'], true);
afirma($r['code'] === 200 && ($j['removed'] ?? 0) === 1 && ($j['changed'] ?? 0) === 1 && ($j['added'] ?? 0) === 1 && (int) $db->uno("SELECT COUNT(*) FROM $P WHERE trip_id = :t", [':t' => $idA]) === count($cur), 'con el album lleno: borrar una, esconder otra y anadir una en UN guardado (el orden en pantalla no importa)');
afirma($edit($ana, $oa, $idA, array_map('intval', array_column($db->filas("SELECT id FROM $P WHERE trip_id = :t", [':t' => $idA]), 'id')))['code'] === 422, 'no se pueden borrar todas a la vez: 422 (queda el album como estaba)');
$todas = array_map('intval', array_column($db->filas("SELECT id FROM $P WHERE trip_id = :t", [':t' => $idA]), 'id'));
afirma($edit($ana, $oa, $idA, [], array_fill_keys($todas, 1))['code'] === 422, 'ni esconder todas a la vez: 422');
afirma($edit($ana, $oa, $idA, [999999999])['code'] === 404, 'una foto que no es del album: 404');
afirma($edit($ana, $oa, $idA)['code'] === 200, 'guardar sin cambios no hace nada y no falla');
$db->exec("UPDATE $P SET friends_only = 0 WHERE trip_id = :t", [':t' => $idA]);
$sob = array_map('intval', array_column($db->filas("SELECT id FROM $P WHERE trip_id = :t ORDER BY id", [':t' => $idA]), 'id'));
foreach (array_slice($sob, 2) as $pid) { $rm($ana, $oa, $pid); }

seccion('Cambiar lugar, ano y titulo desde la edicion');
$row = fn () => $db->filas("SELECT title, place, lat, lon, trip_year, published FROM $T WHERE id = :t", [':t' => $idA])[0];
$orig = $row();
$r = $edit($ana, $oa, $idA, [], [], 0, [], false, true, true, ['place' => 'Kioto, Japon', 'lat' => '35.011600', 'lon' => '135.768300', 'trip_year' => '2019']);
$n = $row();
afirma($r['code'] === 200 && $n['place'] === 'Kioto, Japon' && abs((float) $n['lat'] - 35.0116) < 1e-6 && abs((float) $n['lon'] - 135.7683) < 1e-6 && (int) $n['trip_year'] === 2019 && (int) $n['published'] === 1 && $n['title'] === $orig['title'], 'ana cambia la ciudad (y las coordenadas) y el ano: se guarda y el album sigue publicado');
$bad = [['lat' => '99.5', 'lon' => '10'], ['lat' => 'abc', 'lon' => '10'], ['trip_year' => '1800'], ['trip_year' => '2999'], ['title' => '<script>alert(1)</script>']];
$all = true; foreach ($bad as $b) { $c = $edit($ana, $oa, $idA, [], [], 0, [], false, true, true, $b)['code']; $all = $all && $c === 422; }
$n2 = $row(); afirma($all && $n2 === $n, 'latitud imposible, coordenadas basura, ano imposible o un titulo con codigo: 422 y no cambia nada');
afirma($edit($beto, $ob, $idA, [], [], 0, [], false, true, true, ['place' => 'Hackeado', 'lat' => '1', 'lon' => '1'])['code'] === 404 && $row() === $n, 'beto no puede cambiar el lugar del album de ana: 404');
afirma($edit($ana, $oa, $idA, [], [], 0, [], false, true, true, ['place' => 'Kioto, Japon', 'lat' => '35.011600', 'lon' => '135.768300', 'trip_year' => '2019'])['code'] === 200 && $row() === $n, 'guardar lo mismo no cambia nada');
$db->setParams(['review_first' => 1, 'notify_admin' => 1, 'notify_emails' => 'moderacion@example.test']); $flush();
$r = $edit($ana, $oa, $idA, [], [], 0, [], false, true, true, ['place' => 'Osaka, Japon', 'lat' => '34.693700', 'lon' => '135.502300']); $j = json_decode($r['body'], true);
afirma($r['code'] === 200 && ($j['review'] ?? true) === false && (int) $row()['published'] === 1 && $row()['place'] === 'Osaka, Japon', 'con la revision activada, cambiar el lugar a una ciudad de la lista no vuelve a revision');
$r = $edit($ana, $oa, $idA, [], [], 0, [], false, true, true, ['place' => 'Point chosen on the map', 'lat' => '10.5', 'lon' => '20.5']); $j = json_decode($r['body'], true);
afirma($r['code'] === 200 && ($j['review'] ?? true) === false && (int) $row()['published'] === 1, 'tampoco los nombres que genera el propio mapa (punto elegido, coordenadas)');
$r = $edit($ana, $oa, $idA, [], [], 0, [], false, true, true, ['place' => 'Visita mi tienda barata', 'lat' => '34.693700', 'lon' => '135.502300']); $j = json_decode($r['body'], true);
afirma($r['code'] === 200 && ($j['review'] ?? false) === true && (int) $row()['published'] === 0, 'pero un nombre de lugar escrito a mano (que no sale de la lista ni del mapa) si vuelve a revision');
$db->exec("UPDATE $T SET published = 1, place = 'Osaka, Japon' WHERE id = :t", [':t' => $idA]);
usleep(400000); $flush();
$r = $edit($ana, $oa, $idA, [], [], 0, [], false, true, true, ['title' => 'Titulo nuevo de ana']); $j = json_decode($r['body'], true);
afirma($r['code'] === 200 && ($j['review'] ?? false) === true && (int) $row()['published'] === 0 && $row()['title'] === 'Titulo nuevo de ana', 'cambiar el titulo vuelve a revision: el album queda pendiente');
afirma($tripIn($dataOf($beto), $idA) === null && $tripIn($dataOf($ana), $idA) !== null, 'beto ya no ve el album; ana si (pendiente)');
usleep(500000); $files = $mails(); $raw = $files ? (string) file_get_contents($files[0]) : ''; $body = correoTexto($raw);
afirma(count($files) === 1 && str_contains($body, 'Titulo nuevo de ana') && !str_contains($raw . $body, 'COM_MAPAVIAJES'), 'los moderadores reciben el aviso de revision');
$r = $edit($admB === null ? $ana : $adm, $oadm, $idA, [], [], 0, [], false, true, true, ['title' => 'Cambiado por admin']);
afirma($r['code'] === 404 && $row()['title'] === 'Titulo nuevo de ana', 'ni siquiera un administrador cambia el album de otra persona desde esta pantalla (404)');
$db->exec("UPDATE $T SET published = 1, title = :ti, place = :pl, lat = :la, lon = :lo, trip_year = :y WHERE id = :t", [':ti' => $orig['title'], ':pl' => $orig['place'], ':la' => $orig['lat'], ':lo' => $orig['lon'], ':y' => $orig['trip_year'], ':t' => $idA]);
$db->setParams(['review_first' => 0, 'notify_admin' => 0]);

seccion('Fotos nuevas con revision previa');
$ids5 = array_column($db->filas("SELECT id FROM $P WHERE trip_id = :t ORDER BY id", [':t' => $idA]), 'id');
foreach (array_slice($ids5, 2) as $pid) { $rm($ana, $oa, (int) $pid); }
$db->setParams(['review_first' => 1, 'notify_admin' => 1, 'notify_emails' => 'moderacion@example.test']); $flush();
$r = $add($ana, $oa, $idA, 1); $j = json_decode($r['body'], true);
$newId = (int) $db->uno("SELECT id FROM $P WHERE trip_id = :t AND state = 0", [':t' => $idA]);
afirma($r['code'] === 200 && ($j['pending'] ?? false) === true && $newId > 0 && (int) $db->uno("SELECT photo_count FROM $T WHERE id = :t", [':t' => $idA]) === 2, 'con la revision activada la foto nueva queda pendiente y no cuenta todavia');
usleep(500000); $files = $mails(); $raw = $files ? (string) file_get_contents($files[0]) : ''; $body = correoTexto($raw);
afirma(count($files) === 1 && str_contains($raw, 'moderacion@example.test') && !str_contains($raw . $body, 'COM_MAPAVIAJES') && !preg_match('#https?://#i', $body), 'los moderadores reciben un correo (sin claves sin traducir, sin enlaces)');
$ta = $tripIn($dataOf($ana), $idA); $pend = array_values(array_filter($ta['ph'], fn ($p) => $p['pd'] === 1));
afirma(count($pend) === 1 && $view($ana, $newId) === 200, 'ana la ve marcada como pendiente');
$tb = $tripIn($dataOf($beto), $idA);
afirma(!preg_match('/"id":' . $newId . '[,}]/', json_encode($dataOf($beto))) && $view($beto, $newId) === 404 && $view($guest, $newId) === 404, 'nadie mas la ve, ni por su direccion');
afirma($view($adm, $newId) === 200 && count($tripIn($dataOf($adm), $idA)['ph']) === 3, 'el administrador SI la ve para poder revisarla');
$ap = $admB->get('/administrator/index.php?option=com_mapaviajes&view=trips&filter[published]=*&filter[newphotos]=1');
preg_match('/name="([a-f0-9]{32})" value="1"/', $ap['body'], $m); $tok = $m[1] ?? '';
afirma($ap['code'] === 200 && str_contains($ap['body'], 'approvephotos') && str_contains($ap['body'], 'tripList'), 'el panel muestra el album con fotos por revisar y el boton de aprobar');
$act = fn (string $task, bool $token = true) => $admB->post('/administrator/index.php?option=com_mapaviajes&view=trips', http_build_query(['task' => $task, 'cid' => [$idA], 'boxchecked' => 1] + ($token ? [$tok => '1'] : [])));
$act('trips.approvephotos', false);
afirma((int) $db->uno("SELECT state FROM $P WHERE id = :i", [':i' => $newId]) === 0, 'aprobar SIN token CSRF: no hace nada');
$ana->get('/index.php?option=com_mapaviajes'); $act('trips.approvephotos');
afirma((int) $db->uno("SELECT state FROM $P WHERE id = :i", [':i' => $newId]) === 1 && (int) $db->uno("SELECT photo_count FROM $T WHERE id = :t", [':t' => $idA]) === 3, 'aprobar con token: la foto sale y el contador sube a 3');
afirma($view($beto, $newId) === 200, 'ahora beto la ve');
$add($ana, $oa, $idA, 1); $rej = (int) $db->uno("SELECT id FROM $P WHERE trip_id = :t AND state = 0", [':t' => $idA]);
$act('trips.rejectphotos');
afirma($rej > 0 && (int) $db->uno("SELECT COUNT(*) FROM $P WHERE id = :i", [':i' => $rej]) === 0 && (int) $db->uno("SELECT COUNT(*) FROM $P WHERE trip_id = :t", [':t' => $idA]) === 3, 'rechazar borra solo las fotos pendientes (las aprobadas se quedan)');
$db->setParams(['review_first' => 0, 'notify_admin' => 0]);

seccion('Quien puede moderar ve todo: un usuario normal no');
afirma($oadm['canEditState'] === true && ($ob['canEditState'] ?? true) === false, 'solo los moderadores tienen el permiso de ver todas las fotos');
$db->exec("DELETE FROM $F"); $db->exec("DELETE FROM $B");
$db->setParams(['review_first' => 1, 'notify_emails' => '']); // the state the other tests expect
fin();
