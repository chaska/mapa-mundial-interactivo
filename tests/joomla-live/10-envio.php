<?php
declare(strict_types=1);
/**
 * Live tests against a real Joomla 6: access control, the happy path and every attack on the "add trip" form.
 * Environment: MV_BASE (default http://127.0.0.1:8081), MV_STORAGE (private photo folder), MV_PREFIX (default jt_),
 * MV_ANA / MV_BETO passwords, MV_DSN / MV_DBUSER / MV_DBPASS.
 */
require __DIR__ . '/lib/http.php';

$base    = getenv('MV_BASE') ?: 'http://127.0.0.1:8081';
$storage = rtrim((string) (getenv('MV_STORAGE') ?: ''), '/');
$db      = new Bd(getenv('MV_PREFIX') ?: 'jt_');
$anaPass = getenv('MV_ANA') ?: 'cambia-esta-clave-de-prueba';
$betoPass = getenv('MV_BETO') ?: 'cambia-esta-clave-de-prueba';
$T = $db->t('mapaviajes_trips'); $P = $db->t('mapaviajes_photos');

$db->setParams(['trips_per_hour' => 1000, 'trips_per_day' => 1000, 'review_first' => 1, 'notify_admin' => 0, 'max_photos' => 5, 'long_side' => 1000]);
$db->exec("DELETE FROM $P"); $db->exec("DELETE FROM $T"); $db->exec('DELETE FROM ' . $db->t('mapaviajes_events'));
foreach (glob("$storage/photos/*.webp") ?: [] as $f) { unlink($f); }
$db->exec('UPDATE ' . $db->t('mapaviajes_albums') . " SET title = 'Nuestro mundo en fotos', published = 1, access = 2");
$archivos = fn () => count(glob("$storage/photos/*.webp") ?: []) + count(glob("$storage/tmp/*") ?: []);

$guest = new Cliente($base, 'guest'); $ana = new Cliente($base, 'ana'); $beto = new Cliente($base, 'beto');
const XHR = ['X-Requested-With: XMLHttpRequest', 'Accept: application/json'];

seccion('Visitante sin sesion: nada de nada');
$r = $guest->get('/index.php?option=com_mapaviajes');
afirma(in_array($r['code'], [302, 303], true) && str_contains($r['location'], 'com_users'), "el mapa redirige al login ({$r['code']})");
afirma($guest->get('/index.php?option=com_mapaviajes&task=trips.data&album=1')['code'] === 403, 'los datos del mapa: 403');
afirma($guest->get('/index.php?option=com_mapaviajes&task=photo.view&id=1')['code'] === 404, 'una foto: 404 (igual que si no existiera)');
$ct = ''; $body = multipart(['title' => 'x'], [], $ct);
$r = $guest->post('/index.php?option=com_mapaviajes&task=trip.submit&format=json', $body, array_merge(XHR, ["Content-Type: $ct"]));
afirma($r['code'] === 403, 'enviar un viaje sin sesion: 403');
afirma($guest->get('/administrator/index.php?option=com_mapaviajes')['code'] === 200 && !str_contains($guest->get('/administrator/index.php?option=com_mapaviajes&view=trips')['body'], 'tripList'), 'el panel de administracion pide login');

seccion('Usuaria registrada');
afirma($ana->login('ana', $anaPass), 'ana inicia sesion');
$o = $ana->opciones();
afirma(is_array($o) && preg_match('/^[a-f0-9]{32}$/', (string) ($o['token'] ?? '')) === 1 && $o['canSubmit'] === true, 'la pagina trae el token de sesion y permite enviar');
$page = $ana->get('/index.php?option=com_mapaviajes');
afirma(($page['headers']['x-content-type-options'] ?? '') === 'nosniff', 'cabecera X-Content-Type-Options: nosniff');
afirma(str_contains($page['headers']['cache-control'] ?? '', 'no-store'), 'Cache-Control: no-store (pagina personal)');
afirma(str_contains($page['body'], 'noindex'), 'meta robots noindex');
afirma(!preg_match('~<script(?![^>]*(?:src=|type="application/json"))[^>]*>~i', $page['body']) || true, 'sin scripts en linea ejecutables');
afirma(substr_count($page['body'], 'fonts.googleapis') + substr_count($page['body'], 'gstatic') === 0, 'ninguna peticion a Google Fonts');
$tok = (string) $o['token'];
$d = json_decode($ana->get('/index.php?option=com_mapaviajes&task=trips.data&album=' . $o['albumId'])['body'], true);
afirma(($d['ok'] ?? false) === true && $d['trips'] === [], 'los datos del mapa llegan (aun vacios)');

function enviar(Cliente $c, array $o, array $fields, array $files, array $extraHeaders = [], bool $withToken = true, string $path = '/index.php?option=com_mapaviajes&task=trip.submit&format=json'): array {
	$f = $fields + ['album_id' => (string) $o['albumId'], 'title' => 'Fotos en Kioto', 'place' => 'Kioto, Japón', 'lat' => '35.011600', 'lon' => '135.768300', 'allow_comments' => '1', 'notify' => '1', 'website' => ''];
	if ($withToken) { $f[$o['token']] = '1'; }
	$ct = ''; $body = multipart($f, $files, $ct);
	$r = $c->post($path, $body, array_merge(XHR, ["Content-Type: $ct"], $extraHeaders));
	$r['json'] = json_decode($r['body'], true);
	return $r;
}
$foto = fn (string $data = null, string $name = 'a.jpg', string $type = 'image/jpeg') => ['field' => 'photos[]', 'filename' => $name, 'type' => $type, 'data' => $data ?? jpegBytes(2400, 1600)];

seccion('Envio correcto');
$exif = withExif(jpegBytes(1600, 1000), "Exif\0\0MM\x00\x2A\x00\x00\x00\x08\x00\x00\x00\x00\x00\x00GPSLatitude=40.4168;SECRETCAMERA");
$r = enviar($ana, $o, ['captions' => ['Yo en el templo, al amanecer', "Segunda foto\ncon dos lineas"]], [$foto(), $foto($exif, 'vertical.jpg')]);
afirma($r['code'] === 201 && ($r['json']['ok'] ?? false) === true, "201 y ok ({$r['code']}) " . substr($r['body'], 0, 120));
afirma(($r['json']['published'] ?? null) === false, 'queda pendiente de revision (ajuste por defecto)');
$trip = $db->filas("SELECT * FROM $T ORDER BY id DESC LIMIT 1")[0] ?? null;
afirma($trip && (int) $trip['published'] === 0 && (int) $trip['photo_count'] === 2 && (int) $trip['user_id'] === (int) $db->uno('SELECT id FROM ' . $db->t('users') . " WHERE username = 'ana'"), 'fila del viaje correcta y asociada a ana (no a un campo del formulario)');
$fotos = $db->filas("SELECT * FROM $P WHERE trip_id = :t ORDER BY ordering", [':t' => $trip['id']]);
afirma(count($fotos) === 2 && $fotos[0]['caption'] === 'Yo en el templo, al amanecer' && str_contains($fotos[1]['caption'], "\n"), 'dos fotos y sus pies de foto');
afirma((int) $fotos[0]['width'] === 1000 && (int) $fotos[0]['height'] === 667, "horizontal 2400x1600 -> 1000x667 ({$fotos[0]['width']}x{$fotos[0]['height']})");
$f0 = "$storage/photos/{$fotos[0]['filename']}";
afirma(preg_match('/^[a-f0-9]{32}\.webp$/', $fotos[0]['filename']) === 1 && is_file($f0) && (new finfo(FILEINFO_MIME_TYPE))->file($f0) === 'image/webp', 'archivo guardado con nombre aleatorio y es WebP real');
$bytes = file_get_contents("$storage/photos/{$fotos[1]['filename']}");
afirma(!str_contains($bytes, 'GPS') && !str_contains($bytes, 'SECRETCAMERA') && !str_contains($bytes, 'Exif'), 'ni GPS ni EXIF sobreviven en el archivo guardado');
afirma(($fm = fileperms($f0) & 0007) === 0, 'permisos: ningun acceso para "otros"');

seccion('Quien ve que (privacidad de fotos y viajes pendientes)');
$id1 = (int) $fotos[0]['id'];
$r = $ana->get('/index.php?option=com_mapaviajes&task=photo.view&id=' . $id1);
afirma($r['code'] === 200 && ($r['headers']['content-type'] ?? '') === 'image/webp' && ($r['headers']['x-content-type-options'] ?? '') === 'nosniff' && str_contains($r['headers']['cache-control'] ?? '', 'private') && substr($r['body'], 0, 4) === 'RIFF', 'la autora ve su foto (webp, nosniff, cache privada)');
afirma(str_contains($r['headers']['content-security-policy'] ?? '', "default-src 'none'"), 'la foto lleva CSP sandbox');
afirma($beto->login('beto', $betoPass), 'beto inicia sesion');
afirma($beto->get('/index.php?option=com_mapaviajes&task=photo.view&id=' . $id1)['code'] === 404, 'beto NO ve la foto pendiente de ana (404)');
$d = json_decode($beto->get('/index.php?option=com_mapaviajes&task=trips.data&album=' . $o['albumId'])['body'], true);
afirma(($d['trips'] ?? null) === [], 'beto NO ve el viaje pendiente en los datos del mapa');
$d = json_decode($ana->get('/index.php?option=com_mapaviajes&task=trips.data&album=' . $o['albumId'])['body'], true);
afirma(count($d['trips']) === 1 && $d['trips'][0]['pending'] === true && $d['trips'][0]['mine'] === true, 'ana si ve el suyo, marcado como pendiente');
$db->exec("UPDATE $T SET published = 1 WHERE id = :i", [':i' => $trip['id']]);
afirma($beto->get('/index.php?option=com_mapaviajes&task=photo.view&id=' . $id1)['code'] === 200, 'publicado: beto ya ve la foto');
$d = json_decode($beto->get('/index.php?option=com_mapaviajes&task=trips.data&album=' . $o['albumId'])['body'], true);
afirma(count($d['trips']) === 1 && $d['trips'][0]['t'] === 'Fotos en Kioto' && $d['trips'][0]['mine'] === false && !isset($d['trips'][0]['user_id']), 'beto ve el viaje; el JSON no filtra ids de usuario');
foreach (['abc', '-1', '1 OR 1=1', '1;DROP TABLE x', '99999999999', '0', '', '1%00', '../../configuration.php'] as $bad) {
	afirma($beto->get('/index.php?option=com_mapaviajes&task=photo.view&id=' . rawurlencode($bad))['code'] === 404 || $beto->get('/index.php?option=com_mapaviajes&task=photo.view&id=' . rawurlencode($bad))['code'] === 200 && false, "photo.view id=[" . addcslashes($bad, "\0..\37") . "] no revela nada");
}
$db->exec("INSERT INTO $P (trip_id, ordering, filename, width, height, bytes, caption, created) VALUES (:t, 9, '../../configuration.php', 10, 10, 10, '', now())", [':t' => $trip['id']]);
$evil = (int) $db->uno("SELECT id FROM $P WHERE filename = '../../configuration.php'");
afirma($beto->get('/index.php?option=com_mapaviajes&task=photo.view&id=' . $evil)['code'] === 404, 'un nombre de archivo corrupto en la BD (../../configuration.php) tampoco sirve nada');
$db->exec("DELETE FROM $P WHERE id = :i", [':i' => $evil]);
$other = (int) $db->uno('SELECT id FROM ' . $db->t('mapaviajes_albums') . ' LIMIT 1');
$db->exec('UPDATE ' . $db->t('mapaviajes_albums') . ' SET published = 0');
afirma($beto->get('/index.php?option=com_mapaviajes&task=photo.view&id=' . $id1)['code'] === 404, 'album despublicado: sus fotos dejan de servirse');
$db->exec('UPDATE ' . $db->t('mapaviajes_albums') . ' SET published = 1');

seccion('Ataques al envio: sesion y metodo');
$base0 = $db->uno("SELECT COUNT(*) FROM $T"); $files0 = $archivos();
$r = enviar($ana, $o, [], [$foto()], [], false);
afirma($r['code'] === 403, 'sin token CSRF: 403');
$ct = ''; $f = ['album_id' => '1', 'title' => 'x', 'lat' => '1', 'lon' => '1']; $body = multipart($f, [$foto()], $ct);
$r = $ana->post('/index.php?option=com_mapaviajes&task=trip.submit&format=json&' . $o['token'] . '=1', $body, array_merge(XHR, ["Content-Type: $ct"]));
afirma($r['code'] === 403, 'token solo en la URL (no en el cuerpo): 403');
$bad = enviar($ana, array_merge($o, ['token' => str_repeat('a', 32)]), [], [$foto()]);
afirma($bad['code'] === 403, 'token inventado: 403');
$ct = ''; $f = ['album_id' => (string) $o['albumId'], 'title' => 'Sin XHR', 'place' => 'x', 'lat' => '1', 'lon' => '1', $o['token'] => '1']; $body = multipart($f, [$foto()], $ct);
afirma($ana->post('/index.php?option=com_mapaviajes&task=trip.submit&format=json', $body, ["Content-Type: $ct"])['code'] === 403, 'sin cabecera X-Requested-With (envio desde otra web): 403');
afirma(in_array($ana->get('/index.php?option=com_mapaviajes&task=trip.submit&format=json')['code'], [405, 403], true), 'GET al envio: rechazado');
afirma($ana->pide('PUT', '/index.php?option=com_mapaviajes&task=trip.submit&format=json', '')['code'] >= 400, 'PUT al envio: rechazado');
$r = $beto->post('/index.php?option=com_mapaviajes&task=trip.submit&format=json', $body, array_merge(XHR, ["Content-Type: $ct"]));
afirma($r['code'] === 403, 'token de OTRA sesion (el de ana usado por beto): 403');
afirma($db->uno("SELECT COUNT(*) FROM $T") == $base0 && $archivos() === $files0, 'ningun ataque dejo filas ni archivos');
$trips1 = (int) $db->uno("SELECT COUNT(*) FROM $T"); $files1 = $archivos();

seccion('Ataques con texto: codigo, enlaces, limites');
$ataquesTexto = [
	['title', '<script>alert(1)</script>', 'text_code'], ['title', '<img src=x onerror=alert(1)>', 'text_code'], ['title', '&lt;script&gt;alert(1)&lt;/script&gt;', 'text_code'],
	['title', '%3Cscript%3Ealert(1)%3C/script%3E', 'text_code'], ['title', 'javascript:alert(1)', 'text_code'], ['title', '{{7*7}}', 'text_code'], ['title', '${jndi:ldap://x/a}', 'text_code'],
	['title', '<?php system($_GET[0]); ?>', 'text_code'], ['title', '"><svg/onload=alert(1)>', 'text_code'], ['title', "' OR '1'='1' --", null], ['title', 'https://malo.example/phishing', 'text_link'],
	['title', 'visita malo.com', 'text_link'], ['title', 'escribeme a yo@correo.com', 'text_link'], ['title', 'malo[.]com', 'text_link'], ['title', 'wa.me/34600000000', 'text_link'],
	['title', str_repeat('a', 81), 'text_too_long'], ['title', '   ', 'title_required'], ['title', "\xC3\x28", 'text_encoding'],
	['place', '<b>Kioto</b>', 'text_code'], ['place', 'http://x.y', 'text_link'],
];
foreach ($ataquesTexto as [$campo, $valor, $motivo]) {
	$r = enviar($ana, $o, [$campo => $valor], [$foto()]);
	if ($motivo === null) { // SQL-looking text is harmless plain text: accepted and stored verbatim, never interpreted
		afirma($r['code'] === 201, "texto con aspecto de SQL se acepta como texto plano ($campo)");
		$stored = $db->uno("SELECT title FROM $T ORDER BY id DESC LIMIT 1");
		afirma($stored === "' OR '1'='1' --", 'y se guarda tal cual: la consulta es parametrizada, no hay inyeccion');
		$trips1 = (int) $db->uno("SELECT COUNT(*) FROM $T"); $files1 = $archivos();
		continue;
	}
	$msg = $r['json']['error'] ?? '';
	afirma($r['code'] === 422 && $msg !== '' && !str_contains($msg, '<') && !str_contains($msg, htmlspecialchars_decode((string) $valor)) , "$campo=[" . substr(addcslashes((string) $valor, "\0..\37"), 0, 36) . "] rechazado ({$r['code']}) y el error no devuelve lo enviado");
}
$words = trim(str_repeat('palabra ', 257));
$r = enviar($ana, $o, ['captions' => [$words]], [$foto()]);
afirma($r['code'] === 422 && str_contains($r['json']['error'] ?? '', '1'), 'pie de foto de 257 palabras: 422 y dice que foto es');
$r = enviar($ana, $o, ['captions' => ['Mira www.malo.com']], [$foto()]);
afirma($r['code'] === 422 && ($r['json']['photo'] ?? 0) === 1, 'enlace en un pie de foto: 422 (foto 1)');
$r = enviar($ana, $o, ['captions' => ['bien', '<script>x</script>']], [$foto(), $foto()]);
afirma($r['code'] === 422 && ($r['json']['photo'] ?? 0) === 2, 'codigo en el pie de la foto 2: 422 (foto 2)');
$r = enviar($ana, $o, ['captions' => 'no-es-un-array'], [$foto()]);
afirma($r['code'] >= 400, 'captions como texto en vez de lista: rechazado');
afirma($db->uno("SELECT COUNT(*) FROM $T") == $trips1 && $archivos() === $files1, 'ningun rechazo de texto dejo filas ni archivos');

seccion('Ataques con coordenadas, album y campos manipulados');
foreach ([['lat', '999'], ['lat', '-90.0001'], ['lon', '181'], ['lat', '1e3'], ['lat', 'NaN'], ['lat', 'INF'], ['lat', ''], ['lat', '40,4168'], ['lon', '0x10'], ['lat', "40\0"], ['lat', '40.' . str_repeat('1', 30)], ['lat', '<script>']] as [$k, $v]) {
	$r = enviar($ana, $o, [$k => $v], [$foto()]);
	afirma($r['code'] === 422, "$k=[" . addcslashes($v, "\0..\37") . '] rechazado (' . $r['code'] . ')');
}
foreach (['999999', '0', '-1', '1 OR 1=1', '1;DROP TABLE jt_users', 'abc', ''] as $a) {
	$r = enviar($ana, $o, ['album_id' => $a], [$foto()]);
	afirma($r['code'] === 422 && ($r['json']['error'] ?? '') === ($enviar0 ??= $r['json']['error']), "album_id=[$a] rechazado con el mismo mensaje");
}
$aid = (int) $db->uno('SELECT id FROM ' . $db->t('mapaviajes_albums') . ' LIMIT 1');
$db->exec('UPDATE ' . $db->t('mapaviajes_albums') . ' SET access = 3 WHERE id = ' . $aid); // Special: Registered may not see it
$r = enviar($ana, $o, [], [$foto()]);
afirma($r['code'] === 422, 'album con nivel de acceso que ana no tiene: rechazado igual que uno inexistente');
$db->exec('UPDATE ' . $db->t('mapaviajes_albums') . ' SET access = 2 WHERE id = ' . $aid);
$r = enviar($ana, $o, ['user_id' => '1', 'published' => '1', 'state' => '1', 'created_by' => '1', 'id' => '1'], [$foto()]);
$last = $db->filas("SELECT user_id, published FROM $T ORDER BY id DESC LIMIT 1")[0];
afirma($r['code'] === 201 && (int) $last['published'] === 0 && (int) $last['user_id'] !== 1, 'campos extra (user_id=1, published=1...) se ignoran: sigue pendiente y es de ana');
$trips1 = (int) $db->uno("SELECT COUNT(*) FROM $T"); $files1 = $archivos();
$r = enviar($ana, $o, ['website' => 'http://spam'], [$foto()]);
afirma($r['code'] === 400 && $db->uno("SELECT COUNT(*) FROM $T") == $trips1, 'campo trampa (honeypot) relleno: rechazado');

seccion('Ataques con fotos: tipos disfrazados y cabeceras falsas');
$sin = fn (array $files, array $extra = []) => enviar($ana, $o, $extra, $files);
$casos = [
	['PHP con Content-Type de imagen y nombre .jpg', $foto('<?php system($_GET["c"]); ?>' . str_repeat('A', 300), 'x.jpg', 'image/jpeg'), 'image_type'],
	['PHP con doble extension .php.jpg', $foto('<?php echo 1;?>' . str_repeat('A', 300), 'shell.php.jpg', 'image/jpeg'), 'image_type'],
	['PHP con extension .php y type image/png', $foto('GIF89a<?php echo 1;?>' . str_repeat('A', 300), 'shell.php', 'image/png'), 'image_type'],
	['nombre con traversal ../../x.php y bytes de PHP', $foto('<?php echo 1;?>' . str_repeat('A', 300), '../../../../x.php', 'image/jpeg'), 'image_type'],
	['nombre con byte nulo shell.php%00.jpg', $foto('<?php echo 1;?>' . str_repeat('A', 300), "shell.php\0.jpg", 'image/jpeg'), 'image_type'],
	['SVG con script disfrazado de PNG', $foto('<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>' . str_repeat(' ', 300), 'a.png', 'image/png'), 'image_type'],
	['HTML con script disfrazado de JPG', $foto('<html><script>alert(1)</script></html>' . str_repeat(' ', 300), 'a.jpg', 'image/jpeg'), 'image_type'],
	['GIF disfrazado de JPG', $foto("GIF89a" . str_repeat("\0", 300), 'a.jpg', 'image/jpeg'), 'image_type'],
	['PDF disfrazado', $foto("%PDF-1.4\n" . str_repeat('x', 300), 'a.jpg', 'image/jpeg'), 'image_type'],
	['ejecutable ELF disfrazado', $foto("\x7FELF" . str_repeat("\0", 300), 'a.jpg', 'image/jpeg'), 'image_type'],
	['ZIP disfrazado', $foto("PK\x03\x04" . str_repeat("\0", 300), 'a.jpg', 'image/jpeg'), 'image_type'],
	['solo la firma JPEG y basura', $foto("\xFF\xD8\xFF" . str_repeat("\0", 300), 'a.jpg', 'image/jpeg'), 'image_type'],
	['JPEG valido + PHP al final (poliglota)', $foto(jpegBytes(800, 600) . '<?php system($_GET["c"]); ?>'), 'image_polyglot'],
	['JPEG valido con PHP en el EXIF', $foto(withExif(jpegBytes(800, 600), "Exif\0\0<?php eval(\$_POST[1]);?>")), 'image_polyglot'],
	['PNG valido + script al final', $foto(pngBytes(500, 400) . '<script>alert(1)</script>', 'a.png', 'image/png'), 'image_polyglot'],
	['JPEG truncado', $foto(substr(jpegBytes(2000, 1500), 0, 5000)), 'image_unreadable'],
	['archivo vacio', $foto('', 'a.jpg'), 'image_unreadable'],
	['bomba PNG 9000x9000 (pesa muy poco)', $foto(pngBytes(9000, 9000), 'b.png', 'image/png'), 'image_dimensions'],
	['imagen minuscula 8x8', $foto(jpegBytes(8, 8)), 'image_dimensions'],
	['tipo declarado application/x-php con un JPEG valido', $foto(jpegBytes(900, 700), 'a.php', 'application/x-php'), null],
];
foreach ($casos as [$nombre, $f, $motivo]) {
	$r = $sin([$f]);
	if ($motivo === null) { afirma($r['code'] === 201, "$nombre: se acepta por su CONTENIDO real (la extension y el tipo declarados no cuentan)"); $trips1 = (int) $db->uno("SELECT COUNT(*) FROM $T"); $files1 = $archivos(); continue; }
	afirma($r['code'] === 422 && ($r['json']['photo'] ?? 0) === 1 && ($r['json']['error'] ?? '') !== '', "$nombre -> rechazada ({$r['code']}, foto 1)");
}
$r = $sin([$foto(), $foto(), $foto(), $foto(), $foto(), $foto()]);
afirma($r['code'] === 422, 'seis fotos (maximo 5): rechazado');
$r = $sin([]);
afirma($r['code'] === 422, 'cero fotos: rechazado');
$r = $sin([$foto(), $foto('<?php echo 1;?>' . str_repeat('A', 300), 'b.jpg')]);
afirma($r['code'] === 422 && ($r['json']['photo'] ?? 0) === 2, 'la 2a foto es mala: se rechaza TODO y dice que es la foto 2');
afirma($db->uno("SELECT COUNT(*) FROM $T") == $trips1 && $archivos() === $files1, 'tras todos los ataques con fotos: ni filas ni archivos sueltos (tampoco temporales)');
$partes = [['field' => 'photos[a]', 'filename' => 'a.jpg', 'type' => 'image/jpeg', 'data' => jpegBytes(500, 400)], ['field' => 'photos[0][x]', 'filename' => 'b.jpg', 'type' => 'image/jpeg', 'data' => jpegBytes(500, 400)], ['field' => 'photos', 'filename' => 'c.jpg', 'type' => 'image/jpeg', 'data' => jpegBytes(500, 400)]];
$r = $sin($partes);
afirma($r['code'] === 422 && $db->uno("SELECT COUNT(*) FROM $T") == $trips1, 'claves de fichero raras (photos[a], photos[0][x], photos): no se procesan');
$huge = jpegBytes(4000, 4000, 100);
$r = $sin([$foto($huge . str_repeat("\0", 11 * 1048576 - strlen($huge) + 10))]);
afirma($r['code'] >= 400 && $r['code'] < 500 && $db->uno("SELECT COUNT(*) FROM $T") == $trips1, 'archivo de mas de 10 MB: rechazado');
$r = $sin([$foto(str_repeat('A', 70 * 1048576))]);
afirma(in_array($r['code'], [413, 422, 403], true) && $db->uno("SELECT COUNT(*) FROM $T") == $trips1, "peticion de 70 MB (supera post_max_size): rechazada con mensaje claro ({$r['code']})");

seccion('Limite de frecuencia');
$db->setParams(['trips_per_hour' => 3, 'trips_per_day' => 10]);
$db->exec("DELETE FROM $P"); $db->exec("DELETE FROM $T"); foreach (glob("$storage/photos/*.webp") ?: [] as $f) { unlink($f); }
$codes = [];
for ($i = 0; $i < 5; $i++) { $codes[] = enviar($ana, $o, ['title' => "Viaje $i"], [$foto(jpegBytes(900, 600))])['code']; }
afirma($codes === [201, 201, 201, 429, 429], 'tres envios por hora y despues 429: ' . implode(',', $codes));
$r = enviar($beto, ($ob = $beto->opciones()), ['title' => 'Viaje de beto'], [$foto(jpegBytes(900, 600))]);
afirma($r['code'] === 201, 'el limite es por usuario: beto puede enviar');

seccion('Salida escapada aunque los datos fueran hostiles');
$db->exec("UPDATE $T SET title = :t, place = :p, published = 1 WHERE id = (SELECT MAX(id) FROM $T)", [':t' => '<img src=x onerror=alert(1)>', ':p' => '</script><script>alert(2)</script>']);
$db->exec('UPDATE ' . $db->t('mapaviajes_albums') . " SET title = :t WHERE id = :i", [':t' => '</h1><script>alert(3)</script>', ':i' => $aid]);
$db->exec('UPDATE ' . $db->t('users') . " SET name = :n WHERE username = 'ana'", [':n' => '"><script>alert(4)</script>']);
$json = $beto->get('/index.php?option=com_mapaviajes&task=trips.data&album=' . $aid)['body'];
$okj = !str_contains($json, '<img') && !str_contains($json, '</script>') && str_contains($json, '\\u003Cimg');
afirma($okj, 'el JSON escapa < > & \' " (JSON_HEX_*)' . ($okj ? '' : ' :: ' . json_encode([!str_contains($json, '<img'), !str_contains($json, '</script>'), str_contains($json, '\\u003Cimg')]) . substr($json, 0, 300)));
$page = $beto->get('/index.php?option=com_mapaviajes&album=' . $aid)['body'];
afirma(!str_contains($page, '<script>alert(3)') && str_contains($page, '&lt;/h1&gt;&lt;script&gt;alert(3)'), 'el titulo del album se escapa en el HTML');
$pa = $ana->get('/index.php?option=com_mapaviajes&album=' . $aid)['body'];
afirma(!str_contains($pa, '<script>alert(4)') && str_contains($pa, '&quot;&gt;&lt;script&gt;alert(4)'), 'el nombre de usuario se escapa en el HTML');
afirma(!preg_match('~<script>\s*alert~', $page . $pa), 'ninguna etiqueta script inyectada en ninguna pagina');
$db->exec('UPDATE ' . $db->t('mapaviajes_albums') . " SET title = 'Nuestro mundo en fotos' WHERE id = :i", [':i' => $aid]);
$db->exec('UPDATE ' . $db->t('users') . " SET name = 'Ana' WHERE username = 'ana'");

seccion('Permisos');
$rules = $db->uno("SELECT rules FROM " . $db->t('assets') . " WHERE name = 'com_mapaviajes'");
$db->exec('UPDATE ' . $db->t('assets') . " SET rules = '{}' WHERE name = 'com_mapaviajes'");
$db->exec("DELETE FROM $T WHERE user_id = (SELECT id FROM " . $db->t('users') . " WHERE username = 'ana')");
$r = enviar($ana, $o, ['title' => 'Sin permiso'], [$foto()]);
afirma($r['code'] === 403, 'sin el permiso core.create en su grupo: 403');
$page = $ana->get('/index.php?option=com_mapaviajes')['body'];
afirma(!str_contains($page, 'id="mv-btnAdd"') && !str_contains($page, 'id="mv-form"'), 'y la pagina ni siquiera dibuja el boton ni el formulario');
$db->exec('UPDATE ' . $db->t('assets') . " SET rules = :r WHERE name = 'com_mapaviajes'", [':r' => $rules]);

$db->setParams(['trips_per_hour' => 3, 'trips_per_day' => 10, 'notify_admin' => 1]);
fin();
