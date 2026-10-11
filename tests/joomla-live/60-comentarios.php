<?php
declare(strict_types=1);
/**
 * Live tests of the comments bridge: Engage (needs Engage installed; its tables are created for PostgreSQL by engage-pg.sql),
 * the generic "content events" provider (a tiny test plugin is installed for it) and the provider "none".
 * Environment: MV_SITE = the Joomla folder (to install the test plugin), plus the usual MV_* variables.
 */
require __DIR__ . '/lib/http.php';

$base = getenv('MV_BASE') ?: 'http://127.0.0.1:8081';
$site = rtrim((string) getenv('MV_SITE'), '/');
$db   = new Bd(getenv('MV_PREFIX') ?: 'jt_');
$T = $db->t('mapaviajes_trips'); $A = $db->t('assets'); $EC = $db->t('engage_comments');
$db->pdo->exec(file_get_contents(__DIR__ . '/engage-pg.sql'));
$db->setParams(['trips_per_hour' => 1000, 'trips_per_day' => 1000, 'review_first' => 0, 'notify_admin' => 0, 'comments_provider' => 'engage']);
// Registered users may comment in Engage (its own permission: core.create on com_engage)
$engageRules = $db->uno("SELECT rules FROM $A WHERE name = 'com_engage'");
$db->exec("UPDATE $A SET rules = '{\"core.create\":{\"2\":1}}' WHERE name = 'com_engage'");
$db->exec("DELETE FROM $EC");

// the test plugin that plays the part of a comment extension listening to the content events
$zip = sys_get_temp_dir() . '/plg_mvtest.zip'; @unlink($zip);
$z = new ZipArchive(); $z->open($zip, ZipArchive::CREATE);
$root = __DIR__ . '/fixtures/plg_content_mvtest';
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $f) { $z->addFile($f->getPathname(), substr($f->getPathname(), strlen($root) + 1)); }
$z->close();
exec('php ' . escapeshellarg($site . '/cli/joomla.php') . ' extension:install --path=' . escapeshellarg($zip) . ' 2>&1', $o, $rc);
$db->exec("UPDATE " . $db->t('extensions') . " SET enabled = 1 WHERE element = 'mvtest' AND folder = 'content'");
afirma((int) $db->uno('SELECT COUNT(*) FROM ' . $db->t('extensions') . " WHERE element = 'mvtest' AND enabled = 1") === 1, 'plugin de prueba (extension de comentarios simulada) instalado y activo');

$guest = new Cliente($base, 'g'); $ana = new Cliente($base, 'ana'); $beto = new Cliente($base, 'beto'); $admin = new Cliente($base, 'admin');
afirma($ana->login('ana', getenv('MV_ANA') ?: 'cambia-esta-clave-de-prueba') && $beto->login('beto', getenv('MV_BETO') ?: 'cambia-esta-clave-de-prueba'), 'ana y beto inician sesion');
$o = $ana->opciones();
afirma(($o['commentsOn'] ?? false) === true && str_contains((string) ($o['commentsUrl'] ?? ''), 'tripcomments'), 'el mapa sabe que hay comentarios y a que pagina ir');

$mk = function (string $title, string $comments) use ($ana, $o): int {
	$f = ['trip_year' => '2024', 'album_id' => (string) $o['albumId'], 'title' => $title, 'place' => 'Lisboa', 'lat' => '38.72', 'lon' => '-9.14', 'allow_comments' => $comments, 'notify' => '0', 'website' => '', $o['token'] => '1'];
	$ct = ''; $body = multipart($f, [['field' => 'photos[]', 'filename' => 'a.jpg', 'type' => 'image/jpeg', 'data' => jpegBytes(900, 600)]], $ct);
	$r = $ana->post('/index.php?option=com_mapaviajes&task=trip.submit&format=json', $body, ['X-Requested-With: XMLHttpRequest', "Content-Type: $ct"]);
	return (int) (json_decode($r['body'], true)['id'] ?? 0);
};
$idA = $mk('Con comentarios', '1'); $idB = $mk('Sin comentarios', '0'); $idC = $mk('Pendiente', '1');
$db->exec("UPDATE $T SET published = 0 WHERE id = :i", [':i' => $idC]);
afirma($idA > 0 && $idB > 0 && $idC > 0, 'tres viajes de ana creados');
$page = fn (Cliente $c, $id) => $c->get('/index.php?option=com_mapaviajes&view=tripcomments&tmpl=component&id=' . rawurlencode((string) $id));

seccion('Engage: quien ve que');
$r = $guest->get('/index.php?option=com_mapaviajes'); $r = $page($guest, $idA);
afirma($r['code'] !== 200 || !str_contains($r['body'], 'akengage'), 'invitado: no ve los comentarios');
$r = $page($beto, $idA);
afirma($r['code'] === 200 && str_contains($r['body'], 'akengage-comments-section') && str_contains($r['body'], 'Con comentarios'), 'beto ve el hilo de Engage del viaje A y su titulo');
afirma(str_contains($r['body'], 'akengageCommentForm'), 'y el formulario para comentar (Engage deja comentar a registrados)');
afirma(!str_contains($r['body'], 'mv-events-ok'), 'ninguna otra extension de contenido ve el viaje (despachador privado)');
$assetA = (int) $db->uno("SELECT asset_id FROM $T WHERE id = :i", [':i' => $idA]);
afirma($assetA > 0 && $db->uno("SELECT name FROM $A WHERE id = :a", [':a' => $assetA]) === "com_mapaviajes.trip.$idA", 'el viaje tiene su activo "com_mapaviajes.trip.N" (no se hace pasar por articulo)');
$r = $page($beto, $idB);
afirma($r['code'] === 200 && !str_contains($r['body'], 'akengageCommentForm') && str_contains($r['body'], 'not available'), 'viaje con comentarios cerrados: mensaje, sin formulario');
afirma($page($beto, $idC)['code'] === 404, 'viaje pendiente de revision: 404');
foreach (['0', '999999', 'abc', '1e3', '-1', '1 OR 1=1', ''] as $x) { afirma($page($beto, $x)['code'] === 404, "id [$x]: 404"); }
$r = $beto->get('/index.php?option=com_mapaviajes&view=tripcomments&tmpl=component&id[]=' . $idA); afirma($r['code'] === 404, 'id como lista: 404');
afirma($page($ana, $idA)['code'] === 200, 'la autora tambien ve su hilo');

seccion('Engage: comentar de verdad');
$r = $page($beto, $idA); $h = $r['body'];
preg_match('~<form[^>]*akengageCommentForm.*?</form>~s', $h, $fm);
preg_match_all('~<input[^>]*name="([^"]+)"[^>]*value="([^"]*)"~', $fm[0] ?? '', $in, PREG_SET_ORDER);
$fields = []; foreach ($in as $m) { $fields[html_entity_decode($m[1])] = html_entity_decode($m[2]); }
$fields['jform[body]'] = 'Que foto tan bonita, gracias por compartirla'; $fields['jform[asset_id]'] = (string) $assetA;
$r = $beto->post('/index.php?option=com_engage&task=comment.save', http_build_query($fields));
afirma(in_array($r['code'], [302, 303], true), 'Engage acepta el comentario de beto (redireccion ' . $r['code'] . ')');
afirma(str_contains($r['location'], 'view=tripcomments') && str_contains($r['location'], 'id=' . $idA), 'y vuelve a la pagina de comentarios del mismo viaje (' . $r['location'] . ')');
afirma((int) $db->uno("SELECT COUNT(*) FROM $EC WHERE asset_id = :a", [':a' => $assetA]) === 1, 'el comentario esta guardado en el hilo de ese viaje');
$db->exec("UPDATE $EC SET enabled = 1 WHERE asset_id = :a", [':a' => $assetA]);
$ap = $page($ana, $idA)['body']; file_put_contents('/tmp/ana.html', $ap);
afirma(str_contains($ap, 'Que foto tan bonita'), 'ana lo ve publicado en su viaje');
$h2 = $beto->post('/index.php?option=com_engage&task=comment.save', http_build_query(['jform[body]' => 'intento', 'jform[asset_id]' => '1', 'returnurl' => ''] + array_diff_key($fields, ['jform[body]' => 1, 'jform[asset_id]' => 1])));
afirma((int) $db->uno("SELECT COUNT(*) FROM $EC WHERE asset_id = 1") === 0, 'no se puede comentar en un activo ajeno inventado (asset 1)');
$db->exec("UPDATE $T SET published = 0 WHERE id = :i", [':i' => $idA]);
$h3 = $beto->post('/index.php?option=com_engage&task=comment.save', http_build_query(['jform[body]' => 'con el viaje oculto'] + array_diff_key($fields, ['jform[body]' => 1])));
afirma((int) $db->uno("SELECT COUNT(*) FROM $EC WHERE asset_id = :a", [':a' => $assetA]) === 1, 'con el viaje despublicado Engage ya no admite comentarios');
$db->exec("UPDATE $T SET published = 1 WHERE id = :i", [':i' => $idA]);

seccion('Proveedor: eventos de contenido');
$db->setParams(['comments_provider' => 'events']);
$r = $page($beto, $idA);
afirma(str_contains($r['body'], 'id="mv-events-ok"') && str_contains($r['body'], 'data-context="com_mapaviajes.trip"') && str_contains($r['body'], 'data-id="' . $idA . '"'), 'una extension que escucha los eventos recibe contexto com_mapaviajes.trip y el id del viaje');
afirma(!str_contains($r['body'], 'akengage-comments-section'), 'y Engage no interviene');
afirma($page($beto, $idB)['code'] === 200 && !str_contains($page($beto, $idB)['body'], 'mv-events-ok'), 'con comentarios cerrados en el viaje no se llama a nadie');

seccion('Proveedor: ninguno');
$db->setParams(['comments_provider' => 'none']);
$r = $page($beto, $idA);
afirma(!str_contains($r['body'], 'mv-events-ok') && !str_contains($r['body'], 'akengage-comments-section') && str_contains($r['body'], 'not available'), 'sin proveedor: mensaje y nada mas');
$o2 = $ana->opciones(); afirma(($o2['commentsOn'] ?? true) === false, 'y el mapa no muestra el boton de comentarios');

seccion('Borrar un viaje se lleva su hilo');
$db->setParams(['comments_provider' => 'engage']);
$admin->login('admin', getenv('MV_ADMIN') ?: 'cambia-esta-clave-de-prueba', true);
$p = $admin->get('/administrator/index.php?option=com_mapaviajes&view=trips'); preg_match('/name="([a-f0-9]{32})" value="1"/', $p['body'], $m); $tok = $m[1] ?? '';
$adm = fn (string $task, int $id) => $admin->post('/administrator/index.php?option=com_mapaviajes&view=trips', http_build_query(['task' => $task, 'cid' => [$id], 'boxchecked' => 1, $tok => '1']));
$adm('trips.trash', $idA); $adm('trips.delete', $idA);
afirma((int) $db->uno("SELECT COUNT(*) FROM $T WHERE id = :i", [':i' => $idA]) === 0, 'viaje A borrado');
afirma((int) $db->uno("SELECT COUNT(*) FROM $EC WHERE asset_id = :a", [':a' => $assetA]) === 0, 'sus comentarios de Engage tambien');
afirma((int) $db->uno("SELECT COUNT(*) FROM $A WHERE id = :a", [':a' => $assetA]) === 0, 'y su fila de activos');

// clean up what the test installed
$db->exec("UPDATE $A SET rules = :r WHERE name = 'com_engage'", [':r' => $engageRules ?: '{}']);
$db->exec("UPDATE " . $db->t('extensions') . " SET enabled = 0 WHERE element = 'mvtest' AND folder = 'content'");
$db->setParams(['comments_provider' => 'none', 'review_first' => 1]);
fin();
