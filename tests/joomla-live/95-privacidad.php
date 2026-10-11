<?php
declare(strict_types=1);
/**
 * Privacy: Joomla's access (export) and erasure requests, and deleting an account from the Users manager.
 * Real photo files are created in the private store and must be gone afterwards; so must the trip's Engage thread and asset.
 */
require __DIR__ . '/lib/http.php';
$base = getenv('MV_BASE') ?: 'http://127.0.0.1:8081';
$storage = rtrim((string) getenv('MV_STORAGE'), '/');
$db = new Bd(getenv('MV_PREFIX') ?: 'jt_');
$U = $db->t('users'); $T = $db->t('mapaviajes_trips'); $P = $db->t('mapaviajes_photos'); $R = $db->t('mapaviajes_reactions'); $K = $db->t('mapaviajes_ranking'); $PF = $db->t('mapaviajes_prefs'); $EV = $db->t('mapaviajes_events'); $A = $db->t('assets'); $EC = $db->t('engage_comments'); $PR = $db->t('privacy_requests'); $UG = $db->t('user_usergroup_map');
$db->pdo->exec(file_get_contents(__DIR__ . '/engage-pg.sql'));
$album = (int) $db->uno('SELECT id FROM ' . $db->t('mapaviajes_albums') . ' ORDER BY id LIMIT 1');
$anaId = (int) $db->uno("SELECT id FROM $U WHERE username = 'ana'");
foreach (['pv_a', 'pv_b'] as $n) { $db->exec("DELETE FROM $U WHERE username = :u", [':u' => $n]); }
$mk = function (string $n) use ($db, $U, $UG): int {
	$db->exec("INSERT INTO $U (name, username, email, password, block, \"sendEmail\", \"registerDate\", params) VALUES (:n, :u, :e, 'x', 0, 0, now(), '{}')", [':n' => "PV $n", ':u' => $n, ':e' => "$n@example.test"]);
	$id = (int) $db->uno("SELECT id FROM $U WHERE username = :u", [':u' => $n]);
	$db->exec("INSERT INTO $UG (user_id, group_id) VALUES (:i, 2)", [':i' => $id]);
	return $id;
};
$seed = function (int $uid, string $tag) use ($db, $T, $P, $R, $K, $PF, $EV, $A, $EC, $album, $storage, $anaId): array {
	$files = []; $trips = [];
	foreach ([1, 2] as $k) {
		$db->exec("INSERT INTO $T (album_id, user_id, title, place, lat, lon, allow_comments, notify, published, photo_count, created) VALUES (:a, :u, :t, 'Lisboa', 1, 1, 1, 0, 1, 2, now())", [':a' => $album, ':u' => $uid, ':t' => "Privado $tag $k"]);
		$tid = (int) $db->uno("SELECT MAX(id) FROM $T WHERE user_id = :u", [':u' => $uid]); $trips[] = $tid;
		for ($i = 0; $i < 2; $i++) {
			$name = bin2hex(random_bytes(16)) . '.webp'; file_put_contents($storage . '/photos/' . $name, 'RIFFxxxxWEBPfake');
			$db->exec("INSERT INTO " . $P . " (trip_id, ordering, filename, width, height, bytes, caption, created) VALUES (:t, :o, :f, 10, 10, 1, :c, now())", [':t' => $tid, ':o' => $i, ':f' => $name, ':c' => "pie $tag"]);
			$files[] = $name;
		}
	}
	$db->exec("INSERT INTO $A (parent_id, lft, rgt, level, name, title, rules) VALUES (1, 9990, 9991, 1, :n, 'x', '{}')", [':n' => "com_mapaviajes.trip.$trips[0]"]);
	$asset = (int) $db->uno("SELECT id FROM $A WHERE name = :n", [':n' => "com_mapaviajes.trip.$trips[0]"]);
	$db->exec("UPDATE $T SET asset_id = :a WHERE id = :t", [':a' => $asset, ':t' => $trips[0]]);
	$db->exec("INSERT INTO $EC (asset_id, body, user_agent, enabled, created, created_by) VALUES (:a, 'hilo', 'x', 1, now(), :u)", [':a' => $asset, ':u' => $anaId]);
	$other = (int) $db->uno("SELECT MIN(id) FROM $T WHERE user_id = :u AND published = 1", [':u' => $anaId]);
	if ($other) { $db->exec("INSERT INTO $R (trip_id, user_id, kind, active, created, changed) VALUES (:t, :u, 'like', 1, now(), now())", [':t' => $other, ':u' => $uid]); }
	$db->exec("INSERT INTO $R (trip_id, user_id, kind, active, created, changed) VALUES (:t, :u, 'fan', 1, now(), now())", [':t' => $trips[0], ':u' => $anaId]); // somebody else's reaction to his trip
	$db->exec("INSERT INTO $K (week_start, user_id, position, score, stars, points) VALUES ('2026-01-05', :u, 1, 50, 3, 10)", [':u' => $uid]);
	$db->exec("INSERT INTO $PF (user_id, hide_ranking) VALUES (:u, 1)", [':u' => $uid]);
	$db->exec("INSERT INTO $EV (user_id, event, detail, created) VALUES (:u, 'test_event', 'x', now())", [':u' => $uid]);
	return ['files' => $files, 'trips' => $trips, 'asset' => $asset];
};
$count = fn (int $uid) => [
	'trips' => (int) $db->uno("SELECT COUNT(*) FROM $T WHERE user_id = :u", [':u' => $uid]),
	'photos' => (int) $db->uno("SELECT COUNT(*) FROM $P WHERE trip_id IN (SELECT id FROM $T WHERE user_id = :u)", [':u' => $uid]),
	'react' => (int) $db->uno("SELECT COUNT(*) FROM $R WHERE user_id = :u", [':u' => $uid]),
	'rank' => (int) $db->uno("SELECT COUNT(*) FROM $K WHERE user_id = :u", [':u' => $uid]),
	'prefs' => (int) $db->uno("SELECT COUNT(*) FROM $PF WHERE user_id = :u", [':u' => $uid]),
	'events' => (int) $db->uno("SELECT COUNT(*) FROM $EV WHERE user_id = :u", [':u' => $uid]),
];
$admin = new Cliente($base, 'admin');
afirma($admin->login('admin', getenv('MV_ADMIN') ?: 'cambia-esta-clave-de-prueba', true), 'el administrador inicia sesion');
$page = $admin->get('/administrator/index.php?option=com_users&view=users')['body']; preg_match('/name="([a-f0-9]{32})" value="1"/', $page, $m); $tok = $m[1] ?? '';
afirma($tok !== '', 'hay token de administracion');

seccion('Peticion de acceso (exportar) de Joomla');
$a = $mk('pv_a'); $sa = $seed($a, 'A');
$db->exec("INSERT INTO $PR (email, requested_at, status, request_type, confirm_token) VALUES ('pv_a@example.test', now(), 1, 'export', 'x')");
$rid = (int) $db->uno("SELECT MAX(id) FROM $PR");
$x = $admin->get('/administrator/index.php?option=com_privacy&task=request.export&format=xml&id=' . $rid);
afirma($x['code'] === 200 && str_contains($x['body'], 'mapaviajes_trips') && str_contains($x['body'], 'Privado A 1') && str_contains($x['body'], 'Privado A 2'), 'la exportacion incluye sus viajes');
afirma(str_contains($x['body'], 'mapaviajes_photos') && str_contains($x['body'], 'pie A') && str_contains($x['body'], 'mapaviajes_reactions') && str_contains($x['body'], 'mapaviajes_ranking') && str_contains($x['body'], 'mapaviajes_settings'), 'sus fotos (datos), reacciones, ranking y ajustes');
afirma(!str_contains($x['body'], $sa['files'][0]) && !str_contains($x['body'], 'hilo'), 'sin nombres de archivo internos y sin comentarios de otras personas');
afirma(!str_contains($x['body'], 'Privado B'), 'nada de otra persona');

seccion('Peticion de borrado de Joomla');
$db->exec("INSERT INTO $PR (email, requested_at, status, request_type, confirm_token) VALUES ('pv_a@example.test', now(), 1, 'remove', 'x')");
$rm = (int) $db->uno("SELECT MAX(id) FROM $PR");
afirma(max($count($a)) > 0 && is_file($storage . '/photos/' . $sa['files'][0]), 'antes: tiene datos y archivos');
$r = $admin->get('/administrator/index.php?option=com_privacy&task=request.remove&id=' . $rm . '&' . $tok . '=1');
afirma(in_array($r['code'], [302, 303], true), 'el administrador ejecuta el borrado (' . $r['code'] . ')');
$c = $count($a);
afirma($c['trips'] === 0 && $c['photos'] === 0 && $c['react'] === 0 && $c['rank'] === 0 && $c['prefs'] === 0 && $c['events'] === 0, 'se borran viajes, fotos, reacciones, ranking, ajustes y se desvincula el registro: ' . json_encode($c));
clearstatcache(); // another process deleted them: do not trust PHP's cached answer
$gone = true; foreach ($sa['files'] as $f) { if (is_file($storage . '/photos/' . $f)) { $gone = false; } }
afirma($gone, 'y los archivos de las fotos desaparecen del almacen privado');
afirma((int) $db->uno("SELECT COUNT(*) FROM $A WHERE id = :a", [':a' => $sa['asset']]) === 0 && (int) $db->uno("SELECT COUNT(*) FROM $EC WHERE asset_id = :a", [':a' => $sa['asset']]) === 0, 'la fila de activos y el hilo de comentarios de su viaje tambien');
afirma((int) $db->uno("SELECT COUNT(*) FROM $R WHERE user_id = :u", [':u' => $anaId]) >= 0 && (int) $db->uno("SELECT COUNT(*) FROM $R WHERE trip_id = :t", [':t' => $sa['trips'][0]]) === 0, 'las reacciones de otras personas a sus viajes se van con ellos');
afirma((int) $db->uno("SELECT COUNT(*) FROM $T WHERE user_id = :u", [':u' => $anaId]) > 0, 'los viajes de ana siguen ahi');

seccion('Borrar la cuenta desde Usuarios');
$b = $mk('pv_b'); $sb = $seed($b, 'B');
afirma(max($count($b)) > 0, 'pv_b tiene datos');
$r = $admin->post('/administrator/index.php?option=com_users&view=users', http_build_query(['task' => 'users.delete', 'cid' => [$b], 'boxchecked' => 1, $tok => '1']));
afirma((int) $db->uno("SELECT COUNT(*) FROM $U WHERE id = :i", [':i' => $b]) === 0, 'la cuenta se borra del gestor de usuarios');
$c = $count($b);
afirma($c['trips'] === 0 && $c['photos'] === 0 && $c['react'] === 0 && $c['rank'] === 0 && $c['prefs'] === 0, 'no queda nada suyo con un id muerto: ' . json_encode($c));
clearstatcache();
$gone = true; foreach ($sb['files'] as $f) { if (is_file($storage . '/photos/' . $f)) { $gone = false; } }
afirma($gone && (int) $db->uno("SELECT COUNT(*) FROM $EC WHERE asset_id = :a", [':a' => $sb['asset']]) === 0, 'archivos, hilo y activo, fuera tambien');

// tidy
$db->exec("DELETE FROM $PR WHERE email LIKE 'pv_%'"); $db->exec("DELETE FROM $EV WHERE event = 'test_event'"); $db->exec("DELETE FROM $K WHERE week_start = '2026-01-05'");
foreach (['pv_a', 'pv_b'] as $n) { $db->exec("DELETE FROM $UG WHERE user_id IN (SELECT id FROM $U WHERE username = :u)", [':u' => $n]); $db->exec("DELETE FROM $U WHERE username = :u", [':u' => $n]); }
fin();
