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
afirma($view($beto, $pub) === 200, 'beto ve la foto publica');
afirma($view($beto, $priv1) === 404 && $view($beto, $priv2) === 404, 'beto pidiendo la foto privada por su direccion: 404');
afirma($view($guest, $priv1) === 404, 'un invitado tampoco');
afirma($view($ana, $priv1) === 200, 'la autora ve las suyas');
afirma($view($adm, $priv1) === 200 && $view($adm, $priv2) === 200, 'el administrador ve TODAS aunque no sea amigo');
$ta = $tripIn($dataOf($adm), $idA);
afirma($ta !== null && count($ta['ph']) === 3, 'y el administrador recibe las 3 en el mapa');

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

seccion('Rechazar, bloquear');
$fr($beto, $ob, 'request', ['u' => $ua]);
$r = $fr($ana, $oa, 'respond', ['u' => $ubKey, 'action' => 'decline']);
afirma($r['code'] === 200 && (int) $db->uno("SELECT COUNT(*) FROM $F") === 0, 'rechazar borra la solicitud');
$fr($beto, $ob, 'request', ['u' => $ua]);
$r = $fr($ana, $oa, 'respond', ['u' => $ubKey, 'action' => 'block']);
afirma($r['code'] === 200 && (int) $db->uno("SELECT COUNT(*) FROM $B") === 1 && (int) $db->uno("SELECT COUNT(*) FROM $F") === 0, 'bloquear borra la solicitud y bloquea');
afirma($fr($beto, $ob, 'request', ['u' => $ua])['code'] === 404, 'beto ya no puede pedirle amistad a ana (404, sin decir por que)');
$fr($ana, $oa, 'unblock', ['u' => $ubKey]);
afirma((int) $db->uno("SELECT COUNT(*) FROM $B") === 0 && $fr($beto, $ob, 'request', ['u' => $ua])['code'] === 200, 'al desbloquear puede volver a pedirla');
$fr($ana, $oa, 'respond', ['u' => $ubKey, 'action' => 'decline']);

seccion('Editar el album: cambiar quien ve cada foto');
$pp = fn (Cliente $c, array $o, int $photo, string $fo, bool $x = true, bool $t = true) => $c->post('/index.php?option=com_mapaviajes&task=trip.photoprivacy&format=json', http_build_query(['photo' => $photo, 'fo' => $fo] + ($t ? [$o['token'] => '1'] : [])), $x ? $xhr : []);
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
$rm = fn (Cliente $c, array $o, int $photo) => $c->post('/index.php?option=com_mapaviajes&task=trip.photoremove&format=json', http_build_query(['photo' => $photo, $o['token'] => '1']), $xhr);
afirma($rm($beto, $ob, $priv2)['code'] === 404, 'beto no puede borrar fotos de ana: 404');
afirma($rm($ana, $oa, $priv2)['code'] === 200 && (int) $db->uno("SELECT COUNT(*) FROM $P WHERE trip_id = :t", [':t' => $idA]) === 2 && (int) $db->uno("SELECT photo_count FROM $T WHERE id = :t", [':t' => $idA]) === 2, 'ana borra una foto: quedan 2 y el contador se actualiza');
afirma($rm($ana, $oa, $pub)['code'] === 422, 'no puede borrar la unica publica mientras haya otras');
afirma($rm($ana, $oa, $priv1)['code'] === 200 && $rm($ana, $oa, $pub)['code'] === 422 && (int) $db->uno("SELECT COUNT(*) FROM $P WHERE trip_id = :t", [':t' => $idA]) === 1, 'tampoco la ultima foto del album');
$add = function (Cliente $c, array $o, int $trip, int $n, array $priv = [], bool $rights = true) {
	$f = ['id' => (string) $trip, 'captions' => array_fill(0, $n, 'Otra'), 'priv' => $priv ?: array_fill(0, $n, '0'), $o['token'] => '1'] + ($rights ? ['rights' => '1'] : []);
	$files = []; for ($i = 0; $i < $n; $i++) { $files[] = ['field' => 'photos[]', 'filename' => "n$i.jpg", 'type' => 'image/jpeg', 'data' => jpegBytes(640 + $i * 7, 480)]; }
	$ct = ''; $body = multipart($f, $files, $ct);
	return $c->post('/index.php?option=com_mapaviajes&task=trip.photos&format=json', $body, ['X-Requested-With: XMLHttpRequest', "Content-Type: $ct"]);
};
afirma($add($ana, $oa, $idA, 2, [], false)['code'] === 422, 'anadir fotos sin la casilla de derechos: 422');
afirma($add($beto, $ob, $idA, 1)['code'] === 404, 'beto no puede anadir fotos al album de ana: 404');
$r = $add($ana, $oa, $idA, 2, ['1', '1']); $j = json_decode($r['body'], true);
afirma($r['code'] === 201 && ($j['added'] ?? 0) === 2 && ($j['pending'] ?? true) === false && (int) $db->uno("SELECT photo_count FROM $T WHERE id = :t", [':t' => $idA]) === 3, 'ana anade 2 fotos privadas (con la publica que ya tiene): 3 fotos');
$r = $add($ana, $oa, $idA, 3);
afirma($r['code'] === 422 && (int) $db->uno("SELECT COUNT(*) FROM $P WHERE trip_id = :t", [':t' => $idA]) === 3, 'con 3 fotos no caben 3 mas (max 5): 422 y no se guarda nada');
$r = $add($ana, $oa, $idA, 2); afirma($r['code'] === 201 && (int) $db->uno("SELECT COUNT(*) FROM $P WHERE trip_id = :t", [':t' => $idA]) === 5, 'hasta llegar a 5');
afirma($add($ana, $oa, $idA, 1)['code'] === 422, 'la sexta no entra');

seccion('Fotos nuevas con revision previa');
$ids5 = array_column($db->filas("SELECT id FROM $P WHERE trip_id = :t ORDER BY id", [':t' => $idA]), 'id');
foreach (array_slice($ids5, 2) as $pid) { $rm($ana, $oa, (int) $pid); }
$db->setParams(['review_first' => 1, 'notify_admin' => 1, 'notify_emails' => 'moderacion@example.test']); $flush();
$r = $add($ana, $oa, $idA, 1); $j = json_decode($r['body'], true);
$newId = (int) $db->uno("SELECT id FROM $P WHERE trip_id = :t AND state = 0", [':t' => $idA]);
afirma($r['code'] === 201 && ($j['pending'] ?? false) === true && $newId > 0 && (int) $db->uno("SELECT photo_count FROM $T WHERE id = :t", [':t' => $idA]) === 2, 'con la revision activada la foto nueva queda pendiente y no cuenta todavia');
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
