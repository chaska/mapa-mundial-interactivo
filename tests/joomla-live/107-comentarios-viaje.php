<?php
declare(strict_types=1);
/** Live tests: the author opens / closes the comments of their own trip afterwards (an administrator may too; nobody else). */
require __DIR__ . '/lib/http.php';

$base = getenv('MV_BASE') ?: 'http://127.0.0.1:8081';
$db   = new Bd(getenv('MV_PREFIX') ?: 'jt_');
$T = $db->t('mapaviajes_trips'); $A = $db->t('assets'); $EC = $db->t('engage_comments');
$db->pdo->exec(file_get_contents(__DIR__ . '/engage-pg.sql'));
$db->setParams(['trips_per_hour' => 1000, 'trips_per_day' => 1000, 'review_first' => 0, 'notify_admin' => 0, 'comments_provider' => 'engage']);
$rules = $db->uno("SELECT rules FROM $A WHERE name = 'com_engage'");
$db->exec("UPDATE $A SET rules = '{\"core.create\":{\"2\":1}}' WHERE name = 'com_engage'");
$db->exec("UPDATE " . $db->t('mapaviajes_albums') . " SET allow_comments = 1 WHERE id = (SELECT MIN(id) FROM " . $db->t('mapaviajes_albums') . ")");
$db->exec("DELETE FROM " . $db->t('mapaviajes_events'));

$guest = new Cliente($base, 'g'); $ana = new Cliente($base, 'ana'); $beto = new Cliente($base, 'beto'); $adm = new Cliente($base, 'adm');
afirma($ana->login('ana', getenv('MV_ANA') ?: 'cambia-esta-clave-de-prueba'), 'ana inicia sesion');
afirma($beto->login('beto', getenv('MV_BETO') ?: 'cambia-esta-clave-de-prueba'), 'beto inicia sesion');
afirma($adm->login('admin', getenv('MV_ADMIN') ?: 'cambia-esta-clave-de-prueba'), 'el administrador entra en el mapa');
$oa = $ana->opciones(); $ob = $beto->opciones(); $oadm = $adm->opciones();
afirma(($oadm['canEditState'] ?? false) === true && ($oa['canEditState'] ?? true) === false && ($oa['commentsProvider'] ?? false) === true, 'el mapa sabe quien puede abrir y cerrar comentarios de otros');

$f = ['trip_year' => '2024', 'album_id' => (string) $oa['albumId'], 'title' => 'Sin comentarios', 'place' => 'Lisboa', 'lat' => '38.72', 'lon' => '-9.14', 'allow_comments' => '0', 'notify' => '0', 'website' => '', $oa['token'] => '1'];
$ct = ''; $body = multipart($f, [['field' => 'photos[]', 'filename' => 'a.jpg', 'type' => 'image/jpeg', 'data' => jpegBytes(900, 600)]], $ct);
$r = $ana->post('/index.php?option=com_mapaviajes&task=trip.submit&format=json', $body, ['X-Requested-With: XMLHttpRequest', "Content-Type: $ct"]);
$id = (int) (json_decode($r['body'], true)['id'] ?? 0);
afirma($id > 0 && (int) $db->uno("SELECT allow_comments FROM $T WHERE id = :i", [':i' => $id]) === 0, 'ana crea un viaje con los comentarios cerrados');

$url = '/index.php?option=com_mapaviajes&task=trip.comments&format=json'; $xhr = ['X-Requested-With: XMLHttpRequest'];
$sw = fn (Cliente $c, array $o, $trip, $on, bool $x = true, bool $t = true) => $c->post($url, http_build_query(['id' => $trip, 'on' => $on] + ($t ? [$o['token'] => '1'] : [])), $x ? $xhr : []);
$state = fn () => (int) $db->uno("SELECT allow_comments FROM $T WHERE id = :i", [':i' => $GLOBALS['id']]);
$page = fn (Cliente $c) => $c->get('/index.php?option=com_mapaviajes&view=tripcomments&tmpl=component&id=' . $GLOBALS['id']);

seccion('Puertas');
$guest->get('/index.php?option=com_users&view=login');
afirma($sw($guest, ['token' => str_repeat('a', 32)], $id, '1')['code'] === 403, 'invitado: 403');
afirma($sw($ana, $oa, $id, '1', false)['code'] === 403, 'sin X-Requested-With: 403');
afirma($sw($ana, $oa, $id, '1', true, false)['code'] === 403, 'sin token CSRF: 403');
afirma($ana->get($url)['code'] === 405, 'GET: 405');
foreach (['2', 'x', '', '-1', '1 '] as $v) { afirma($sw($ana, $oa, $id, $v)['code'] === 400, 'valor [' . $v . '] rechazado (400)'); }
foreach (['0', '-5', 'abc', '99999999999', "$id\0"] as $v) { afirma(in_array($sw($ana, $oa, $v, '1')['code'], [400, 404], true), 'id raro [' . addcslashes($v, "\0") . '] rechazado'); }
afirma($state() === 0, 'nada cambia con las peticiones rechazadas');
afirma($sw($beto, $ob, $id, '1')['code'] === 404 && $state() === 0, 'beto no puede abrir los comentarios del viaje de ana (404)');

seccion('Cerrados -> abiertos');
$p = $page($ana);
afirma($p['code'] === 200 && !str_contains($p['body'], 'akengageCommentForm'), 'con los comentarios cerrados la pagina no tiene formulario');
$r = $sw($ana, $oa, $id, '1');
afirma($r['code'] === 200 && (json_decode($r['body'], true)['on'] ?? null) === 1 && $state() === 1, 'ana abre los comentarios de su viaje');
$p = $page($ana);
afirma($p['code'] === 200 && str_contains($p['body'], 'akengageCommentForm'), 'ahora la pagina de comentarios trae el formulario de Engage');
afirma(str_contains($ana->get('/index.php?option=com_mapaviajes&task=trips.data&album=' . (int) $oa['albumId'] . '&format=json')['body'], '"cm":true'), 'y los datos del mapa lo reflejan');

seccion('Abiertos -> cerrados');
$r = $sw($ana, $oa, $id, '0');
afirma($r['code'] === 200 && $state() === 0, 'ana vuelve a cerrarlos');
afirma(!str_contains($page($ana)['body'], 'akengageCommentForm'), 'y desaparece el formulario');
$sw($ana, $oa, $id, '1'); afirma($state() === 1, 'y puede reabrirlos');

seccion('Administrador');
$r = $sw($adm, $oadm, $id, '0'); afirma($r['code'] === 200 && $state() === 0, 'el administrador cierra los comentarios de un viaje ajeno');
$r = $sw($adm, $oadm, $id, '1'); afirma($r['code'] === 200 && $state() === 1, 'y los abre');
afirma((int) $db->uno("SELECT COUNT(*) FROM " . $db->t('mapaviajes_events') . " WHERE event IN ('comments_on', 'comments_off')") >= 5, 'quedan anotados los cambios (solo codigo)');

seccion('Album sin comentarios');
$db->exec("UPDATE " . $db->t('mapaviajes_albums') . " SET allow_comments = 0 WHERE id = :i", [':i' => $oa['albumId']]);
afirma(!str_contains($page($ana)['body'], 'akengageCommentForm'), 'si el ALBUM cierra los comentarios, el viaje tampoco los muestra aunque los tenga abiertos');
$db->exec("UPDATE " . $db->t('mapaviajes_albums') . " SET allow_comments = 1 WHERE id = :i", [':i' => $oa['albumId']]);

$db->exec("DELETE FROM $T WHERE id = :i", [':i' => $id]);
$db->exec("UPDATE $A SET rules = :r WHERE name = 'com_engage'", [':r' => $rules]);
fin();
