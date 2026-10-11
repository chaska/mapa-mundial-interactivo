<?php
declare(strict_types=1);
/**
 * The weekly podium, against the real tables of the test site (PostgreSQL): scoring, caps, exclusions, ties,
 * lazy closing, idempotence, immutability of closed weeks, all-time table. Uses throw-away users and trips.
 * Environment: MV_SITE (Joomla folder, for its vendor autoloader), MV_PREFIX (default jt_).
 */
require __DIR__ . '/lib/http.php';
if (!defined('_JEXEC')) { define('_JEXEC', 1); }
$site = rtrim((string) getenv('MV_SITE'), '/');
require_once $site . '/libraries/vendor/autoload.php';
foreach (['Settings', 'RankingService'] as $c) { require_once __DIR__ . '/../../src/component/backend/src/Service/' . $c . '.php'; }
use Chaska\Component\Mapaviajes\Administrator\Service\{RankingService, Settings};
use Joomla\Database\DatabaseFactory;
use Joomla\Registry\Registry;

$prefix = getenv('MV_PREFIX') ?: 'jt_';
$jdb = (new DatabaseFactory())->getDriver('pgsql', ['host' => '127.0.0.1:5433', 'user' => 'joomla', 'password' => getenv('MV_DBPASS') ?: 'x', 'database' => 'joomla', 'prefix' => $prefix]);
$db = new Bd($prefix);
$svc = new RankingService($jdb, new Settings(new Registry()));
$T = $db->t('mapaviajes_trips'); $P = $db->t('mapaviajes_photos'); $R = $db->t('mapaviajes_reactions'); $W = $db->t('mapaviajes_weeks'); $K = $db->t('mapaviajes_ranking'); $U = $db->t('users'); $PF = $db->t('mapaviajes_prefs'); $EC = $db->t('engage_comments');
$db->pdo->exec(file_get_contents(__DIR__ . '/engage-pg.sql'));

seccion('Calendario de semanas (lunes a domingo, UTC)');
afirma(RankingService::weekStart(strtotime('2026-10-12 00:00:00 UTC')) === '2026-10-12', 'lunes 00:00 abre su semana');
afirma(RankingService::weekStart(strtotime('2026-10-18 23:59:59 UTC')) === '2026-10-12', 'domingo 23:59:59 sigue en la misma semana');
afirma(RankingService::weekStart(strtotime('2026-10-19 00:00:00 UTC')) === '2026-10-19', 'el lunes siguiente abre otra');
afirma(RankingService::weekEnd('2026-12-28') === '2027-01-04', 'el cambio de año no rompe nada');
afirma(RankingService::pointsFor(1) === 10 && RankingService::pointsFor(2) === 6 && RankingService::pointsFor(3) === 3 && RankingService::pointsFor(4) === 1 && RankingService::pointsFor(10) === 1 && RankingService::pointsFor(11) === 0, 'puntos de podio 10/6/3, 1 hasta el 10, 0 despues');

// clean slate for the tables this test owns, throw-away users
foreach ([$K, $W, $R, $EC] as $t) { $db->exec("DELETE FROM $t"); }
$db->exec("DELETE FROM $P WHERE trip_id IN (SELECT id FROM $T WHERE title LIKE 'RK %')"); $db->exec("DELETE FROM $T WHERE title LIKE 'RK %'");
$db->exec("DELETE FROM $PF"); $db->exec("DELETE FROM $U WHERE username LIKE 'rk_%'");
$mkUser = function (string $n, int $block = 0) use ($db, $U): int {
	$db->exec("INSERT INTO $U (name, username, email, password, block, \"sendEmail\", \"registerDate\", params) VALUES (:n, :u, :e, 'x', :b, 0, now(), '{}')", [':n' => "RK $n", ':u' => "rk_$n", ':e' => "rk_$n@example.test", ':b' => $block]);
	return (int) $db->uno("SELECT id FROM $U WHERE username = :u", [':u' => "rk_$n"]);
};
$album = (int) $db->uno('SELECT id FROM ' . $db->t('mapaviajes_albums') . ' ORDER BY id LIMIT 1');
$week = '2026-03-02'; // a Monday long ago: closed for sure
$at = fn (int $day, int $h = 12) => gmdate('Y-m-d H:i:s', strtotime("$week UTC") + $day * 86400 + $h * 3600);
$trip = function (int $uid, string $created, int $photos, int $published = 1, string $title = 'RK t') use ($db, $T, $P, $album): int {
	$db->exec("INSERT INTO $T (album_id, user_id, title, place, lat, lon, allow_comments, notify, published, photo_count, created) VALUES (:a, :u, :t, 'x', 1, 1, 1, 0, :p, :c, :cr)", [':a' => $album, ':u' => $uid, ':t' => $title, ':p' => $published, ':c' => $photos, ':cr' => $created]);
	$id = (int) $db->uno("SELECT MAX(id) FROM $T WHERE user_id = :u", [':u' => $uid]);
	for ($i = 0; $i < $photos; $i++) { $db->exec("INSERT INTO $P (trip_id, ordering, filename, width, height, bytes, created) VALUES (:t, :o, :f, 10, 10, 1, :c)", [':t' => $id, ':o' => $i, ':f' => 'rk' . bin2hex(random_bytes(12)) . '.webp', ':c' => $created]); }
	return $id;
};
$react = fn (int $uid, int $tripId, string $kind, string $created, int $active = 1) => $db->exec("INSERT INTO $R (trip_id, user_id, kind, active, created, changed) VALUES (:t, :u, :k, :a, :c, :c)", [':t' => $tripId, ':u' => $uid, ':k' => $kind, ':a' => $active, ':c' => $created]);

$a = $mkUser('a'); $b = $mkUser('b'); $c = $mkUser('c'); $d = $mkUser('d'); $e = $mkUser('e'); $f = $mkUser('f'); $g = $mkUser('g'); $h = $mkUser('h', 1); $i2 = $mkUser('i');
// a: 2 trips with 3+2 photos            -> base 20*2 + 3*5 = 55
$ta1 = $trip($a, $at(0), 3); $ta2 = $trip($a, $at(1), 2);
// b: 1 trip with 5 photos + 30 reactions given (cap 20) -> base 20+15 = 35, engagement min(20, 35) -> 55
$tb = $trip($b, $at(2), 5);
// c: no trips, 40 reactions given        -> not ranked at all
$tc_target = $ta1;
// d: 1 trip, 1 photo, lots of engagement  -> base 23, engagement capped at base -> 46
$td = $trip($d, $at(3), 1);
// e: same score as d (tie): trip made LATER -> d before e
$te = $trip($e, $at(4), 1);
// f: unpublished trip -> nothing;   g: trip OUTSIDE the week -> nothing;  h: blocked user;  i: hidden by choice
$trip($f, $at(0), 4, 0); $trip($g, gmdate('Y-m-d H:i:s', strtotime("$week UTC") - 3600), 2); $trip($mkUser('hb', 0), $at(0), 0);
$trip($h, $at(0), 5); $ti = $trip($i2, $at(0), 5);
$db->exec("INSERT INTO $PF (user_id, hide_ranking) VALUES (:u, 1)", [':u' => $i2]);
// reactions: b gives 30 to trips of other people (needs distinct trip/kind pairs: use trips + both kinds on several trips)
$others = [$ta1, $ta2, $td, $te, $ti]; $n = 0;
foreach ($others as $o) { foreach (['like', 'fan'] as $k) { if ($n < 10) { $react($b, $o, $k, $at(2, 13)); $n++; } } }
// to reach 30 reactions we need more trips: make 10 more trips by f/g (published, other week) just as targets
$extra = []; for ($z = 0; $z < 10; $z++) { $extra[] = $trip($g, gmdate('Y-m-d H:i:s', strtotime("$week UTC") - 86400 * (10 + $z)), 1); }
foreach ($extra as $o) { foreach (['like', 'fan'] as $k) { if ($n < 30) { $react($b, $o, $k, $at(2, 14)); $n++; } } }
// c: 40 reactions, no trips of his own
$n = 0; foreach (array_merge($others, $extra) as $o) { foreach (['like', 'fan'] as $k) { if ($n < 40) { $react($c, $o, $k, $at(1, 15)); $n++; } } }
// d: 20 reactions (cap 20) -> would be 20 but base 23 -> counts 20; e: none. To make d tie with e we keep d's engagement = e's: give both 0 and compare
$rows = $svc->compute($week);
$by = []; foreach ($rows as $r) { $by[$r['username']] = $r; }
afirma(isset($by['rk_a'], $by['rk_b'], $by['rk_d'], $by['rk_e']), 'a, b, d y e aparecen');
afirma(!isset($by['rk_c']) && !isset($by['rk_f']) && !isset($by['rk_g']) && !isset($by['rk_h']) && !isset($by['rk_i']), 'sin viajes, con viaje sin publicar, fuera de semana, bloqueado y oculto por eleccion: NO aparecen');
afirma(($by['rk_a']['score'] ?? 0) === 55 && $by['rk_a']['trips'] === 2 && $by['rk_a']['photos'] === 5, 'a: 2 viajes y 5 fotos = 55 puntos (' . ($by['rk_a']['score'] ?? '?') . ')');
afirma(($by['rk_b']['score'] ?? 0) === 55 && $by['rk_b']['likes'] === 20, 'b: 30 reacciones dadas cuentan 20 (tope) y suman 20: 35 + 20 = 55');
afirma(($by['rk_d']['score'] ?? 0) === 23 && ($by['rk_e']['score'] ?? 0) === 23, 'd y e: 23 cada uno, sin reacciones');
afirma(array_column($rows, 'username') === ['rk_a', 'rk_b', 'rk_d', 'rk_e'], 'empate 55-55: gana a (mas viajes); empate 23-23: gana d (llego antes)');
afirma(array_column($rows, 'stars') === [3, 2, 1, 0] && array_column($rows, 'points') === [10, 6, 3, 1], 'estrellas 3/2/1 y puntos 10/6/3/1');
// engagement can at most double the base: d gives 20 reactions
foreach (array_slice($extra, 0, 10) as $o) { foreach (['like', 'fan'] as $k) { $react($d, $o, $k, $at(5)); } }
$by = []; foreach ($svc->compute($week) as $r) { $by[$r['username']] = $r; }
afirma(($by['rk_d']['score'] ?? 0) === 43 && $by['rk_d']['likes'] === 20, 'd: 20 reacciones suman 20 (menos que su base, 23): 43');
$strong = new RankingService($jdb, new Settings(new Registry(['rank_w_like' => 5])));
$by = []; foreach ($strong->compute($week) as $r) { $by[$r['username']] = $r; }
afirma(($by['rk_d']['score'] ?? 0) === 46, 'con reacciones muy valiosas (5 puntos) el compromiso se frena en la base: 23 + 23 = 46, nunca mas');
$none = new RankingService($jdb, new Settings(new Registry(['rank_like_cap' => 0, 'rank_comment_cap' => 0])));
$by = []; foreach ($none->compute($week) as $r) { $by[$r['username']] = $r; }
afirma(($by['rk_d']['score'] ?? 0) === 23, 'tope de reacciones y comentarios a 0: solo cuentan viajes y fotos');
// own trips do not count, inactive (un-liked) reactions do not count
$react($d, $td, 'like', $at(5)); $react($e, $extra[0], 'like', $at(5), 0);
$by = []; foreach ($svc->compute($week) as $r) { $by[$r['username']] = $r; }
afirma(($by['rk_d']['likes'] ?? -1) === 20 && ($by['rk_e']['likes'] ?? -1) === 0, 'reaccionar a tu propio viaje o dejar la reaccion quitada no suma');
// comments (Engage): published comments on other people's trips count; own trip / unpublished do not
$asset = (int) $db->uno("SELECT COALESCE(MAX(id),0) FROM " . $db->t('assets')) + 1000; $db->exec("UPDATE $T SET asset_id = :s WHERE id = :t", [':s' => $asset, ':t' => $ta1]);
$ins = fn (int $by, int $en) => $db->exec("INSERT INTO $EC (asset_id, body, user_agent, enabled, created, created_by) VALUES (:s, 'x', 'x', :e, :c, :b)", [':s' => $asset, ':e' => $en, ':c' => $at(3), ':b' => $by]);
$ins($e, 1); $ins($e, 1); $ins($e, 0); $ins($a, 1);
$by = []; foreach ($svc->compute($week) as $r) { $by[$r['username']] = $r; }
afirma(($by['rk_e']['comments'] ?? -1) === 2 && ($by['rk_e']['score'] ?? 0) === 23 + 4, 'e: 2 comentarios publicados valen 2x2; el no publicado y el de la autora en su propio viaje no cuentan');
afirma(($by['rk_a']['comments'] ?? -1) === 0, 'a comentando en su propio viaje: 0');

seccion('Cierre semanal');
$now = strtotime('2026-03-09 12:00:00 UTC'); // Monday, the week ended 12 h ago: inside the grace period
afirma(!in_array($week, $svc->closePending($now), true) && $svc->closedWeek($week) === null, 'una semana recien terminada (menos de 2 dias) sigue abierta');
$closed = $svc->closePending(strtotime('2026-03-12 00:00:00 UTC'));
afirma(in_array($week, $closed, true) && !in_array('2026-03-09', $closed, true), 'pasados 2 dias se cierra la semana ' . $week . ' (y no la siguiente: ' . json_encode($closed) . ')');
$res = $svc->closedWeek($week);
afirma(is_array($res) && count($res) === 4 && $res[0]['username'] === 'rk_a' && (int) $res[0]['stars'] === 3 && (int) $res[0]['points'] === 10, 'guardada: 4 filas, la primera es a con 3 estrellas');
afirma($svc->closePending(strtotime('2026-03-12 00:00:00 UTC')) === [], 'cerrar otra vez no hace nada (idempotente)');
$db->exec("DELETE FROM $T WHERE user_id = :u AND title = 'RK t'", [':u' => $a]);
$res2 = $svc->closedWeek($week);
afirma($res2 === $res, 'una semana cerrada no cambia aunque despues se borren los datos');
$hold = $db->t('mapaviajes_weeks');
$db->exec("DELETE FROM $K"); $db->exec("DELETE FROM $W");
$db->exec("INSERT INTO $W (week_start, closed) VALUES (:w, now())", [':w' => $week]);
afirma(!in_array($week, $svc->closePending(strtotime('2026-03-12 00:00:00 UTC')), true) && (int) $db->uno("SELECT COUNT(*) FROM $K WHERE week_start = :w", [':w' => $week]) === 0, 'si otra peticion ya reservo la semana, esta no escribe nada');

seccion('Tabla general');
$db->exec("DELETE FROM $K"); $db->exec("DELETE FROM $W");
$ins2 = fn (string $w, int $uid, int $pos) => $db->exec("INSERT INTO $K (week_start, user_id, position, score, stars, points) VALUES (:w, :u, :p, 10, :s, :pt)", [':w' => $w, ':u' => $uid, ':p' => $pos, ':s' => RankingService::STARS[$pos] ?? 0, ':pt' => RankingService::pointsFor($pos)]);
$ins2('2026-03-02', $a, 1); $ins2('2026-03-09', $a, 2); $ins2('2026-03-02', $b, 2); $ins2('2026-03-09', $b, 1); $ins2('2026-03-16', $b, 4); $ins2('2026-03-02', $h, 1); $ins2('2026-03-02', $i2, 3);
$all = $svc->allTime();
afirma(array_column($all, 'username') === ['rk_b', 'rk_a'] || array_column($all, 'username') === ['rk_a', 'rk_b'], 'bloqueado y oculto no salen en la tabla general');
$allB = array_values(array_filter($all, fn ($r) => $r['username'] === 'rk_b'))[0] ?? [];
afirma((int) ($allB['points'] ?? 0) === 10 + 6 + 1 && (int) ($allB['podiums'] ?? 0) === 2 && (int) ($allB['best'] ?? 9) === 1, 'b: 6 + 10 + 1 = 17 puntos, 2 podios, mejor puesto 1 (' . json_encode($allB) . ')');
afirma(array_column($all, 'username')[0] === 'rk_b', 'orden por puntos: b (17) antes que a (16)');
$svc->setHidden($a, true); afirma($svc->isHidden($a) && array_column($svc->allTime(), 'username') === ['rk_b'], 'pedir no aparecer te saca del ranking');
$svc->setHidden($a, false); afirma(!$svc->isHidden($a), 'y se puede volver a aparecer');
$svc->setHidden($a, true); $svc->setHidden($a, true); afirma((int) $db->uno("SELECT COUNT(*) FROM $PF WHERE user_id = :u", [':u' => $a]) === 1, 'preferencia guardada una sola vez');

// tidy
foreach ([$K, $W, $R, $EC, $PF] as $t) { $db->exec("DELETE FROM $t"); }
$db->exec("DELETE FROM $P WHERE trip_id IN (SELECT id FROM $T WHERE user_id IN (SELECT id FROM $U WHERE username LIKE 'rk_%'))");
$db->exec("DELETE FROM $T WHERE user_id IN (SELECT id FROM $U WHERE username LIKE 'rk_%')"); $db->exec("DELETE FROM $U WHERE username LIKE 'rk_%'");
fin();
