<?php
declare(strict_types=1);
/** Live tests of the administrator side and of URL tampering (layout / view injection, CSRF, ACL). */
require __DIR__ . '/lib/http.php';

$base    = getenv('MV_BASE') ?: 'http://127.0.0.1:8081';
$storage = rtrim((string) getenv('MV_STORAGE'), '/');
$db      = new Bd(getenv('MV_PREFIX') ?: 'jt_');
$T = $db->t('mapaviajes_trips'); $P = $db->t('mapaviajes_photos');
$db->setParams(['trips_per_hour' => 1000, 'trips_per_day' => 1000, 'review_first' => 1, 'notify_admin' => 0]);

$ana = new Cliente($base, 'ana'); $admin = new Cliente($base, 'admin'); $guest = new Cliente($base, 'g');
afirma($ana->login('ana', getenv('MV_ANA') ?: 'cambia-esta-clave-de-prueba'), 'ana inicia sesion');
afirma($admin->login('admin', getenv('MV_ADMIN') ?: 'cambia-esta-clave-de-prueba', true), 'el administrador inicia sesion en el panel');

// three trips by ana
$o = $ana->opciones(); $ids = [];
for ($i = 1; $i <= 3; $i++) {
	$f = ['album_id' => (string) $o['albumId'], 'title' => "Moderacion $i", 'place' => 'Lisboa', 'lat' => '38.72', 'lon' => '-9.14', 'allow_comments' => '1', 'notify' => '0', 'website' => '', $o['token'] => '1'];
	$ct = ''; $body = multipart($f, [['field' => 'photos[]', 'filename' => 'a.jpg', 'type' => 'image/jpeg', 'data' => jpegBytes(1200, 800)]], $ct);
	$r = $ana->post('/index.php?option=com_mapaviajes&task=trip.submit&format=json', $body, ['X-Requested-With: XMLHttpRequest', "Content-Type: $ct"]);
	$ids[] = (int) json_decode($r['body'], true)['id'];
}
afirma(count(array_filter($ids)) === 3, 'tres viajes de prueba creados');

seccion('Manipulacion de la URL: layout y view');
foreach (['../../../../configuration', '..%2f..%2fconfiguration', '/etc/passwd', 'edit', 'default%00', '....//....//configuration'] as $l) {
	$r = $ana->get('/index.php?option=com_mapaviajes&layout=' . $l);
	afirma($r['code'] === 200 && !str_contains($r['body'], 'public $secret') && !str_contains($r['body'], 'root:x:0') && str_contains($r['body'], 'mv-root'), "sitio: layout=[$l] se ignora y sale el mapa de siempre");
}
foreach (['../../../../configuration', 'trips&layout=../../x', 'users', 'map%00'] as $v) {
	$r = $ana->get('/index.php?option=com_mapaviajes&view=' . $v);
	afirma(!str_contains($r['body'], 'public $secret') && !str_contains($r['body'], 'root:x:0'), "sitio: view=[$v] no filtra nada");
}
foreach (['../../../../configuration', '..%2f..%2fconfiguration', '/etc/passwd', 'edit'] as $l) {
	$r = $admin->get('/administrator/index.php?option=com_mapaviajes&view=trips&layout=' . $l);
	afirma(!str_contains($r['body'], 'public $secret') && !str_contains($r['body'], 'root:x:0') && str_contains($r['body'], 'tripList'), "admin: layout=[$l] se ignora");
}
foreach (['../../../../configuration', 'comments', 'trips%00'] as $v) {
	$r = $admin->get('/administrator/index.php?option=com_mapaviajes&view=' . $v);
	afirma(!str_contains($r['body'], 'public $secret') && !str_contains($r['body'], 'root:x:0'), "admin: view=[$v] no filtra nada");
}

seccion('Panel de administracion: acceso');
$g = $guest->get('/administrator/index.php?option=com_mapaviajes&view=trips');
afirma(!str_contains($g['body'], 'tripList') && !str_contains($g['body'], 'Moderacion 1'), 'sin sesion no se ve nada del panel');
$a = $ana->get('/administrator/index.php?option=com_mapaviajes&view=trips');
afirma(!str_contains($a['body'], 'tripList') && !str_contains($a['body'], 'Moderacion 1'), 'una usuaria registrada (ana) tampoco entra al panel');
$p = $admin->get('/administrator/index.php?option=com_mapaviajes&view=trips');
afirma($p['code'] === 200 && str_contains($p['body'], 'tripList') && str_contains($p['body'], 'Moderacion 1'), 'el administrador ve la lista de publicaciones');
afirma(str_contains($p['body'], 'name="filter[search]"') && substr_count($p['body'], 'cb') > 0, 'filtros y casillas presentes');
afirma(!preg_match('~Moderacion 1</th>.*?<script>alert~s', $p['body']), 'sin XSS en la lista');
preg_match('/name="([a-f0-9]{32})" value="1"/', $p['body'], $m); $tok = $m[1] ?? '';
afirma($tok !== '', 'la pagina trae token CSRF');
$adm = fn (string $task, array $cid, bool $token = true) => $admin->post('/administrator/index.php?option=com_mapaviajes&view=trips', http_build_query(['task' => $task, 'cid' => $cid, 'boxchecked' => count($cid)] + ($token ? [$tok => '1'] : [])));
$estado = fn (int $id) => (int) $db->uno("SELECT published FROM $T WHERE id = :i", [':i' => $id]);

seccion('Acciones de moderacion');
$r = $adm('trips.publish', [$ids[0]], false);
afirma($estado($ids[0]) === 0, 'publicar SIN token CSRF: no cambia nada');
$adm('trips.publish', [$ids[0]]); afirma($estado($ids[0]) === 1, 'publicar con token: pasa a publicado');
$d = json_decode($ana->get('/index.php?option=com_mapaviajes&task=trips.data&album=' . $o['albumId'])['body'], true);
afirma(in_array('Moderacion 1', array_column($d['trips'], 't'), true), 'y ya sale en el mapa');
$adm('trips.unpublish', [$ids[0]]); afirma($estado($ids[0]) === 0, 'despublicar');
$adm('trips.archive', [$ids[0]]); afirma($estado($ids[0]) === 2, 'ocultar (archivar)');
$b = new Cliente($base, 'beto'); $b->login('beto', getenv('MV_BETO') ?: 'cambia-esta-clave-de-prueba');
$d = json_decode($b->get('/index.php?option=com_mapaviajes&task=trips.data&album=' . $o['albumId'])['body'], true);
afirma(!in_array('Moderacion 1', array_column($d['trips'], 't'), true), 'un viaje oculto no lo ve nadie mas');
$adm('trips.report', [$ids[1]]); afirma($estado($ids[1]) === -3, 'marcar como spam (-3)');
$ph = (int) $db->uno("SELECT id FROM $P WHERE trip_id = :t", [':t' => $ids[1]]);
$r = $admin->get('/administrator/index.php?option=com_mapaviajes&task=photo.view&id=' . $ph);
afirma($r['code'] === 200 && ($r['headers']['content-type'] ?? '') === 'image/webp', 'el moderador ve la foto de un spam para juzgarla');
$ra = $ana->get('/administrator/index.php?option=com_mapaviajes&task=photo.view&id=' . $ph);
afirma(($ra['headers']['content-type'] ?? '') !== 'image/webp' && substr($ra['body'], 0, 4) !== 'RIFF', 'ana NO puede pedir fotos por el panel (no hay sesion de administracion)');
afirma($b->get('/index.php?option=com_mapaviajes&task=photo.view&id=' . $ph)['code'] === 404, 'la foto de un spam no se sirve en el sitio publico');
$f2 = $db->uno("SELECT filename FROM $P WHERE trip_id = :t", [':t' => $ids[1]]);
$adm('trips.delete', [$ids[1]]); afirma($db->uno("SELECT COUNT(*) FROM $T WHERE id = :i", [':i' => $ids[1]]) == 1, 'borrar algo que NO esta en la papelera: no se borra');
$adm('trips.trash', [$ids[1]]); afirma($estado($ids[1]) === -2, 'a la papelera');
$adm('trips.delete', [$ids[1]]);
afirma($db->uno("SELECT COUNT(*) FROM $T WHERE id = :i", [':i' => $ids[1]]) == 0 && $db->uno("SELECT COUNT(*) FROM $P WHERE trip_id = :t", [':t' => $ids[1]]) == 0 && !is_file("$storage/photos/$f2"), 'eliminar de la papelera borra el viaje, sus filas de fotos Y el archivo del disco');
$sinPermiso = $db->uno('SELECT rules FROM ' . $db->t('assets') . " WHERE name = 'com_mapaviajes'");
afirma($estado($ids[2]) === 0, 'tercer viaje intacto');

seccion('Ajustes (Opciones) con interruptores');
$o2 = $admin->get('/administrator/index.php?option=com_config&view=component&component=com_mapaviajes');
afirma($o2['code'] === 200 && str_contains($o2['body'], 'review_first') && str_contains($o2['body'], 'switcher'), 'las Opciones usan interruptores Si/No de Joomla');
afirma(str_contains($o2['body'], 'storage_path') && str_contains($o2['body'], 'max_photos'), 'estan los ajustes de fotos y carpeta privada');
$albums = $admin->get('/administrator/index.php?option=com_mapaviajes&view=albums');
afirma($albums['code'] === 200 && str_contains($albums['body'], 'albumList'), 'lista de albumes del mapa');
$edit = $admin->get('/administrator/index.php?option=com_mapaviajes&task=album.add');
afirma(in_array($edit['code'], [200, 303, 302], true), 'crear album: ' . $edit['code']);
$db->setParams(['trips_per_hour' => 3, 'trips_per_day' => 10, 'notify_admin' => 1]);
fin();
