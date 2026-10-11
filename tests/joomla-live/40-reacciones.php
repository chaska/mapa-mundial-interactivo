<?php
declare(strict_types=1);
/** Live tests of reactions (heart "like" and star "fan"): who may react, tampering, limits, counts, cleanup. */
require __DIR__ . '/lib/http.php';

$base = getenv('MV_BASE') ?: 'http://127.0.0.1:8081';
$db   = new Bd(getenv('MV_PREFIX') ?: 'jt_');
$T = $db->t('mapaviajes_trips'); $R = $db->t('mapaviajes_reactions');
$db->setParams(['trips_per_hour' => 1000, 'trips_per_day' => 1000, 'review_first' => 0, 'notify_admin' => 0, 'reactions_per_day' => 300]);

$db->exec("DELETE FROM $R");
$guest = new Cliente($base, 'g'); $ana = new Cliente($base, 'ana'); $beto = new Cliente($base, 'beto');
afirma($ana->login('ana', getenv('MV_ANA') ?: 'cambia-esta-clave-de-prueba'), 'ana inicia sesion');
afirma($beto->login('beto', getenv('MV_BETO') ?: 'cambia-esta-clave-de-prueba'), 'beto inicia sesion');

$oa = $ana->opciones(); $ob = $beto->opciones();
$f = ['trip_year' => '2024', 'album_id' => (string) $oa['albumId'], 'title' => 'Reacciones', 'place' => 'Lisboa', 'lat' => '38.72', 'lon' => '-9.14', 'allow_comments' => '1', 'notify' => '0', 'website' => '', $oa['token'] => '1'];
$ct = ''; $body = multipart($f, [['field' => 'photos[]', 'filename' => 'a.jpg', 'type' => 'image/jpeg', 'data' => jpegBytes(900, 600)]], $ct);
$r = $ana->post('/index.php?option=com_mapaviajes&task=trip.submit&format=json', $body, ['X-Requested-With: XMLHttpRequest', "Content-Type: $ct"]);
$id = (int) (json_decode($r['body'], true)['id'] ?? 0);
afirma($id > 0, 'viaje de ana creado');
$db->exec("UPDATE $T SET published = 1 WHERE id = :i", [':i' => $id]);

$url = '/index.php?option=com_mapaviajes&task=reaction.toggle&format=json';
$xhr = ['X-Requested-With: XMLHttpRequest'];
$tok = fn (array $o) => $o['token'];
$react = fn (Cliente $c, array $o, $trip, $kind, bool $x = true, bool $t = true) => $c->post($url, http_build_query(['trip_id' => $trip, 'kind' => $kind] + ($t ? [$o['token'] => '1'] : [])), $x ? $xhr : []);
$j = fn (array $r) => json_decode($r['body'], true) ?: [];
$rows = fn () => (int) $db->uno("SELECT COUNT(*) FROM $R WHERE trip_id = :t AND active = 1", [':t' => $GLOBALS['id']]);

seccion('Quien puede reaccionar');
$guest->get('/index.php?option=com_users&view=login');
$r = $react($guest, ['token' => str_repeat('a', 32)], $id, 'like');
afirma($r['code'] === 403 && (json_decode($r['body'], true)['ok'] ?? true) === false, 'invitado: 403');
afirma((int) $db->uno("SELECT COUNT(*) FROM $R") === 0, 'el invitado no deja ninguna fila');
afirma($react($beto, $ob, $id, 'like', false)['code'] === 403, 'sin X-Requested-With: 403');
afirma($react($beto, $ob, $id, 'like', true, false)['code'] === 403, 'sin token CSRF: 403');
afirma($beto->get($url)['code'] === 405, 'GET: 405');
afirma($rows() === 0, 'nada guardado por los intentos rechazados');
foreach (['', 'LIKE', 'love', 'like ', '../x', "like\0"] as $k) { afirma(in_array($react($beto, $ob, $id, $k)['code'], [400, 403, 422], true), 'tipo invalido [' . addcslashes($k, "\0") . '] rechazado'); }
foreach (['0', '-1', 'abc', '1e3', '99999999999', '1 OR 1=1', ''] as $t) { afirma(in_array($react($beto, $ob, $t, 'like')['code'], [400, 404, 403], true), "trip_id [$t] rechazado"); }
afirma($ana->post($url, http_build_query(['trip_id' => [1], 'kind' => ['like'], $oa['token'] => '1']), $xhr)['code'] >= 400, 'parametros como lista: rechazados');
$r = $react($ana, $oa, $id, 'like');
afirma($r['code'] === 403 && ($j($r)['ok'] ?? true) === false, 'no se puede reaccionar al propio viaje');
afirma($rows() === 0, 'sigue sin haber filas');

seccion('Alternar corazon y estrella');
$r = $react($beto, $ob, $id, 'like'); $d = $j($r);
afirma($r['code'] === 200 && $d['ok'] === true && $d['active'] === true && $d['count'] === 1, 'corazon: activo, cuenta 1');
$r = $react($beto, $ob, $id, 'fan'); $d = $j($r);
afirma($d['active'] === true && $d['count'] === 1, 'estrella: activa, cuenta 1');
$data = $j($beto->get('/index.php?option=com_mapaviajes&task=trips.data&album=' . $oa['albumId']));
$t = $data['trips'][0] ?? [];
afirma(($t['lk'] ?? -1) === 1 && ($t['fn'] ?? -1) === 1 && ($t['ml'] ?? null) == true && ($t['mf'] ?? null) == true, 'datos del mapa: contadores y mis marcas');
$dataAna = $j($ana->get('/index.php?option=com_mapaviajes&task=trips.data&album=' . $oa['albumId']));
afirma(($dataAna['trips'][0]['ml'] ?? 1) == false, 'ana ve el contador pero no marcas ajenas como suyas');
$r = $react($beto, $ob, $id, 'like'); $d = $j($r);
afirma($d['active'] === false && $d['count'] === 0, 'segundo clic: se quita, cuenta 0');
afirma((int) $db->uno("SELECT COUNT(*) FROM $R WHERE trip_id = :t AND kind = 'like'", [':t' => $id]) === 1, 'la fila se conserva (una sola por usuario y tipo)');
$r = $react($beto, $ob, $id, 'like'); afirma($j($r)['active'] === true, 'tercer clic: vuelve a activarse');

seccion('Carreras y limites');
$mh = curl_multi_init(); $hs = [];
for ($i = 0; $i < 12; $i++) {
	$ch = curl_init($base . $url . '&x=' . $i);
	curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(['trip_id' => $id, 'kind' => 'fan', $ob['token'] => '1']), CURLOPT_HTTPHEADER => $xhr, CURLOPT_COOKIEFILE => $beto->jar, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60]);
	curl_multi_add_handle($mh, $ch); $hs[] = $ch;
}
do { curl_multi_exec($mh, $run); curl_multi_select($mh); } while ($run);
$codes = array_map(fn ($c) => curl_getinfo($c, CURLINFO_RESPONSE_CODE), $hs);
afirma(!array_filter($codes, fn ($c) => $c >= 500), '12 clics simultaneos: ningun error 500');
afirma((int) $db->uno("SELECT COUNT(*) FROM $R WHERE trip_id = :t AND user_id = (SELECT user_id FROM $R WHERE kind='like' AND trip_id = :t2 LIMIT 1) AND kind = 'fan'", [':t' => $id, ':t2' => $id]) === 1, 'sigue habiendo una sola fila de estrella');

$db->exec("DELETE FROM $R WHERE trip_id = :t", [':t' => $id]);
$db->setParams(['reactions_per_day' => 1]);
afirma($react($beto, $ob, $id, 'like')['code'] === 200, 'limite 1: la primera reaccion nueva pasa');
$r = $react($beto, $ob, $id, 'fan');
afirma($r['code'] === 429, 'limite 1: la segunda reaccion NUEVA se frena (429)');
afirma($react($beto, $ob, $id, 'like')['code'] === 200, 'quitar una existente sigue permitido');
$db->setParams(['reactions_per_day' => 300]);

seccion('Visibilidad');
$db->exec("UPDATE $T SET published = 0 WHERE id = :i", [':i' => $id]);
$r = $react($beto, $ob, $id, 'fan'); afirma(in_array($r['code'], [403, 404], true), 'viaje no publicado: no se puede reaccionar');
$db->exec("UPDATE $T SET published = 1 WHERE id = :i", [':i' => $id]);

seccion('Limpieza al borrar');
$db->exec("DELETE FROM $R WHERE trip_id = :t", [':t' => $id]);
$react($beto, $ob, $id, 'like');
$admin = new Cliente($base, 'admin'); $admin->login('admin', getenv('MV_ADMIN') ?: 'cambia-esta-clave-de-prueba', true);
$p = $admin->get('/administrator/index.php?option=com_mapaviajes&view=trips'); preg_match('/name="([a-f0-9]{32})" value="1"/', $p['body'], $m); $at = $m[1] ?? '';
$adm = fn (string $task) => $admin->post('/administrator/index.php?option=com_mapaviajes&view=trips', http_build_query(['task' => $task, 'cid' => [$id], 'boxchecked' => 1, $at => '1']));
$adm('trips.trash'); $adm('trips.delete');
afirma((int) $db->uno("SELECT COUNT(*) FROM $T WHERE id = :i", [':i' => $id]) === 0, 'viaje borrado');
afirma((int) $db->uno("SELECT COUNT(*) FROM $R WHERE trip_id = :i", [':i' => $id]) === 0, 'sus reacciones se borran con el');

$db->setParams(['review_first' => 1]);
fin();
