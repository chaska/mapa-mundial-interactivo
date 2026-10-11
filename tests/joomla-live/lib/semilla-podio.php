<?php
declare(strict_types=1);
// Seeds the podium for the browser test: two weeks ago (closed by the first request) five people compete; this week ana and beto have
// published trips with photos. Prints nothing. Throw-away users are named pd_*.
require __DIR__ . '/http.php';
$prefix = getenv('MV_PREFIX') ?: 'jt_'; $db = new Bd($prefix);
$T = $db->t('mapaviajes_trips'); $P = $db->t('mapaviajes_photos'); $U = $db->t('users');
$db->setParams(['rank_enabled' => 1, 'review_first' => 1]);
foreach (['mapaviajes_ranking', 'mapaviajes_weeks', 'mapaviajes_reactions', 'mapaviajes_prefs'] as $t) { $db->exec('DELETE FROM ' . $db->t($t)); }
$db->exec("DELETE FROM $P WHERE trip_id IN (SELECT id FROM $T WHERE title LIKE 'PD %')"); $db->exec("DELETE FROM $T WHERE title LIKE 'PD %'"); $db->exec("DELETE FROM $U WHERE username LIKE 'pd_%'");
$album = (int) $db->uno('SELECT id FROM ' . $db->t('mapaviajes_albums') . ' ORDER BY id LIMIT 1');
$uid = fn (string $n) => (int) $db->uno("SELECT id FROM $U WHERE username = :u", [':u' => $n]);
foreach (['alba', 'bruno', 'carla'] as $n) { $db->exec("INSERT INTO $U (name, username, email, password, block, \"sendEmail\", \"registerDate\", params) VALUES (:n, :u, :e, 'x', 0, 0, now(), '{}')", [':n' => ucfirst($n) . ' Podio', ':u' => "pd_$n", ':e' => "pd_$n@example.test"]); }
$trip = function (int $u, string $created, int $photos) use ($db, $T, $P, $album) {
	$db->exec("INSERT INTO $T (album_id, user_id, title, place, lat, lon, allow_comments, notify, published, photo_count, created) VALUES (:a, :u, 'PD t', 'Lisboa', 38.72, -9.14, 1, 0, 1, :c, :cr)", [':a' => $album, ':u' => $u, ':c' => $photos, ':cr' => $created]);
	$id = (int) $db->uno("SELECT MAX(id) FROM $T WHERE user_id = :u", [':u' => $u]);
	for ($i = 0; $i < $photos; $i++) { $db->exec("INSERT INTO $P (trip_id, ordering, filename, width, height, bytes, created) VALUES (:t, :o, :f, 10, 10, 1, :c)", [':t' => $id, ':o' => $i, ':f' => 'pd' . bin2hex(random_bytes(12)) . '.webp', ':c' => $created]); }
};
$monday = strtotime(gmdate('Y-m-d', time() - ((int) gmdate('N') - 1) * 86400) . ' UTC');
$old = fn (int $day) => gmdate('Y-m-d H:i:s', $monday - 14 * 86400 + $day * 86400 + 43200);
$trip($uid('pd_alba'), $old(0), 5); $trip($uid('pd_alba'), $old(1), 5); $trip($uid('pd_bruno'), $old(1), 5); $trip($uid('pd_carla'), $old(2), 2);
$trip($uid('ana'), $old(3), 3); $trip($uid('beto'), $old(4), 1);
foreach (['ana', 'beto'] as $n) { $trip($uid($n), gmdate('Y-m-d H:i:s'), 2); }
