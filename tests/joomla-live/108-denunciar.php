<?php
declare(strict_types=1);
/** Live tests of "Report": signed-in people flag a trip (one report each, hourly cap), moderators are told by mail without links, cascade deletes, and the rights declaration is required to upload. */
require __DIR__ . '/lib/http.php';

$base = getenv('MV_BASE') ?: 'http://127.0.0.1:8081';
$db   = new Bd(getenv('MV_PREFIX') ?: 'jt_');
$maildir = getenv('MV_MAILDIR') ?: '/tmp/mv-mails';
$T = $db->t('mapaviajes_trips'); $RP = $db->t('mapaviajes_reports');
$db->setParams(['trips_per_hour' => 1000, 'trips_per_day' => 1000, 'review_first' => 0, 'notify_admin' => 1, 'notify_emails' => 'moderacion@example.test', 'contact_email' => 'avisos@example.test', 'sender_line' => 'Empresa Test · Persona Responsable · contacto@example.test']);
$db->exec("DELETE FROM " . $db->t('mapaviajes_events'));
$db->exec("DELETE FROM $RP");
$mails = fn () => array_values(array_filter(glob($maildir . '/*.eml') ?: []));
$flush = function () use ($mails) { foreach ($mails() as $f) { @unlink($f); } };

$guest = new Cliente($base, 'g'); $ana = new Cliente($base, 'ana'); $beto = new Cliente($base, 'beto');
afirma($ana->login('ana', getenv('MV_ANA') ?: 'cambia-esta-clave-de-prueba'), 'ana inicia sesion');
afirma($beto->login('beto', getenv('MV_BETO') ?: 'cambia-esta-clave-de-prueba'), 'beto inicia sesion');
$oa = $ana->opciones(); $ob = $beto->opciones();
afirma(($ob['contactEmail'] ?? '') === 'avisos@example.test', 'el correo de contacto llega al mapa');
afirma(str_contains((string) ($ob['reportUrl'] ?? ''), 'task=trip.report'), 'hay URL de denuncia');

seccion('Declaracion de derechos al subir');
$f = ['trip_year' => '2024', 'album_id' => (string) $oa['albumId'], 'title' => 'Con derechos', 'place' => 'Lisboa', 'lat' => '38.72', 'lon' => '-9.14', 'allow_comments' => '0', 'notify' => '0', 'website' => '', $oa['token'] => '1'];
$send = function (array $fields) use ($ana): array {
	$ct = ''; $body = multipart($fields, [['field' => 'photos[]', 'filename' => 'a.jpg', 'type' => 'image/jpeg', 'data' => jpegBytes(900, 600)]], $ct);
	return $ana->post('/index.php?option=com_mapaviajes&task=trip.submit&format=json', $body, ['X-Requested-With: XMLHttpRequest', "Content-Type: $ct"]);
};
$before = (int) $db->uno("SELECT COUNT(*) FROM $T");
$r = $send($f); $j = json_decode($r['body'], true);
afirma($r['code'] === 422 && ($j['ok'] ?? true) === false && ($j['field'] ?? '') === 'rights' && (int) $db->uno("SELECT COUNT(*) FROM $T") === $before, 'sin la casilla: 422 y no se crea nada');
$r = $send($f + ['rights' => '0']); afirma($r['code'] === 422, 'rights=0: 422');
$r = $send($f + ['rights' => '1']); $idA = (int) (json_decode($r['body'], true)['id'] ?? 0);
afirma($r['code'] === 201 && $idA > 0, 'con la casilla: 201');
$f['title'] = 'Del otro'; $f[$ob['token']] = '1'; unset($f[$oa['token']]);
$flush();
$ct = ''; $body = multipart($f + ['album_id' => (string) $ob['albumId'], 'rights' => '1'], [['field' => 'photos[]', 'filename' => 'a.jpg', 'type' => 'image/jpeg', 'data' => jpegBytes(900, 600)]], $ct);
$r = $beto->post('/index.php?option=com_mapaviajes&task=trip.submit&format=json', $body, ['X-Requested-With: XMLHttpRequest', "Content-Type: $ct"]);
$idB = (int) (json_decode($r['body'], true)['id'] ?? 0);
afirma($idB > 0, 'beto tiene su viaje');
usleep(400000); $files = $mails(); $raw = $files ? (string) file_get_contents($files[0]) : ''; $body = correoTexto($raw);
afirma(count($files) === 1 && !str_contains($raw . $body, 'COM_MAPAVIAJES') && str_contains($body, 'Del otro') && str_contains($body, 'beto'), 'el aviso de viaje nuevo a los moderadores sale con sus textos de verdad (ninguna clave COM_MAPAVIAJES_ a la vista)');

seccion('Puertas de la denuncia');
$url = '/index.php?option=com_mapaviajes&task=trip.report&format=json'; $xhr = ['X-Requested-With: XMLHttpRequest'];
$rep = fn (Cliente $c, array $o, $id, string $reason = 'illegal', string $note = '', bool $x = true, bool $t = true) => $c->post($url, http_build_query(['id' => $id, 'reason' => $reason, 'note' => $note] + ($t ? [$o['token'] => '1'] : [])), $x ? $xhr : []);
$n = fn () => (int) $db->uno("SELECT COUNT(*) FROM $RP");
$guest->get('/index.php?option=com_users&view=login');
afirma($rep($guest, ['token' => str_repeat('a', 32)], $idA)['code'] === 403, 'invitado: 403');
afirma($rep($beto, $ob, $idA, 'illegal', '', false)['code'] === 403, 'sin X-Requested-With: 403');
afirma($rep($beto, $ob, $idA, 'illegal', '', true, false)['code'] === 403, 'sin token CSRF: 403');
afirma($beto->get($url)['code'] === 405, 'GET: 405');
afirma($rep($beto, $ob, $idA, 'pirata')['code'] === 422, 'motivo desconocido: 422');
afirma($rep($beto, $ob, $idA, 'other', '')['code'] === 422, '«otro motivo» sin explicacion: 422');
afirma($rep($beto, $ob, 999999)['code'] === 404, 'viaje inexistente: 404');
afirma($rep($ana, $oa, $idA)['code'] === 404, 'no puedes denunciar tu propio viaje: 404');
afirma($rep($beto, $ob, 'x')['code'] === 404, 'id basura: 404');
afirma($n() === 0, 'nada guardado por los intentos malos');

seccion('Denuncia valida');
$flush();
$r = $rep($beto, $ob, $idA, 'rights', 'Esta foto es mia y no la he dado');
$j = json_decode($r['body'], true);
afirma($r['code'] === 200 && ($j['ok'] ?? false) === true && $n() === 1, 'beto denuncia el viaje de ana: 200 y una fila');
$row = $db->filas("SELECT * FROM $RP")[0] ?? [];
afirma(($row['reason'] ?? '') === 'rights' && ($row['note'] ?? '') === 'Esta foto es mia y no la he dado', 'se guarda el motivo y la nota');
usleep(400000); $files = $mails(); $raw = $files ? (string) file_get_contents($files[0]) : ''; $body = correoTexto($raw);
afirma(count($files) === 1 && str_contains($raw, 'moderacion@example.test') && str_contains($body, '#' . $idA) && !preg_match('#https?://#i', $body), 'los moderadores reciben un correo con el viaje y SIN enlaces');
afirma(str_contains($body, 'Persona Responsable') && str_contains($raw, 'Persona Responsable'), 'el pie del correo dice quien lo envia (texto plano y HTML)');
$flush();
$rep($beto, $ob, $idA, 'illegal', 'otra vez');
usleep(300000);
afirma($n() === 1 && count($mails()) === 0, 'la misma persona no puede denunciar dos veces el mismo viaje (ni se repite el correo)');
afirma((int) $db->uno("SELECT COUNT(*) FROM " . $db->t('mapaviajes_events') . " WHERE event = 'report'") === 1, 'queda anotado solo el codigo del motivo');

afirma($rep($beto, $ob, $idA, 'illegal', 'con <etiqueta>')['code'] === 422, 'una nota con codigo se rechaza (422)');

seccion('Tope por hora');
for ($i = 0; $i < 12; $i++) {
	$g = '9' . $i; $db->exec("INSERT INTO $T (album_id, user_id, title, place, lat, lon, allow_comments, notify, published, photo_count, trip_year, created) SELECT album_id, user_id, 'Relleno $i', 'x', 1, 1, 0, 0, 1, 0, 2024, NOW() FROM $T WHERE id = :i", [':i' => $idA]);
}
$ids = array_column($db->filas("SELECT id FROM $T WHERE title LIKE 'Relleno %' ORDER BY id"), 'id');
$codes = []; foreach ($ids as $id) { $codes[] = $rep($beto, $ob, (int) $id, 'illegal')['code']; }
afirma(count(array_filter($codes, fn ($c) => $c === 200)) === 9 && in_array(429, $codes, true), 'a partir de 10 avisos por hora: 429');
$db->exec("DELETE FROM $T WHERE title LIKE 'Relleno %'"); $db->exec("DELETE FROM $RP WHERE trip_id NOT IN (SELECT id FROM $T)");

seccion('Borrar el viaje borra sus denuncias');
$rep($ana, $oa, $idB, 'illegal'); // ana denuncia el de beto
afirma((int) $db->uno("SELECT COUNT(*) FROM $RP WHERE trip_id = :t", [':t' => $idB]) === 1, 'ana denuncia el viaje de beto');
$d = $beto->post('/index.php?option=com_mapaviajes&task=trip.remove&format=json', http_build_query(['id' => $idB, $ob['token'] => '1']), $xhr);
afirma($d['code'] === 200 && (int) $db->uno("SELECT COUNT(*) FROM $RP WHERE trip_id = :t", [':t' => $idB]) === 0, 'beto borra su viaje y se van las denuncias');
$db->exec("DELETE FROM $RP");
$db->setParams(['sender_line' => '', 'notify_admin' => 0]);
fin();
