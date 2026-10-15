<?php
declare(strict_types=1);
// Leaves one published trip by "ana" in the test site, for the browser tests. Prints its id.
require __DIR__ . '/http.php';
$base = getenv('MV_BASE') ?: 'http://127.0.0.1:8081';
$db = new Bd(getenv('MV_PREFIX') ?: 'jt_');
$db->setParams(['trips_per_hour' => 1000, 'trips_per_day' => 1000, 'review_first' => 0, 'notify_admin' => 0]);
if (getenv('MV_COMMENTS')) { // comments bridge on, and Registered users allowed to comment in Engage (test setup)
	$db->pdo->exec(file_get_contents(__DIR__ . '/../engage-pg.sql'));
	$db->setParams(['comments_provider' => (string) getenv('MV_COMMENTS')]);
	$db->exec('UPDATE ' . $db->t('assets') . " SET rules = '{\"core.create\":{\"2\":1}}' WHERE name = 'com_engage'");
}
$ana = new Cliente($base, 'ana');
if (!$ana->login('ana', getenv('MV_ANA') ?: 'cambia-esta-clave-de-prueba')) { fwrite(STDERR, "login\n"); exit(1); }
$db->exec('DELETE FROM ' . $db->t('mapaviajes_reactions'));
$db->exec('UPDATE ' . $db->t('mapaviajes_trips') . " SET published = 0 WHERE title LIKE 'Semilla%'");
$o = $ana->opciones();
$f = ['trip_year' => '2024', 'album_id' => (string) $o['albumId'], 'title' => 'Semilla para reacciones', 'place' => 'Lisboa', 'lat' => '38.72', 'lon' => '-9.14', 'allow_comments' => '1', 'notify' => '0', 'website' => '', 'rights' => '1', $o['token'] => '1'];
$ct = ''; $body = multipart($f, [['field' => 'photos[]', 'filename' => 'a.jpg', 'type' => 'image/jpeg', 'data' => jpegBytes(900, 600)]], $ct);
$r = $ana->post('/index.php?option=com_mapaviajes&task=trip.submit&format=json', $body, ['X-Requested-With: XMLHttpRequest', "Content-Type: $ct"]);
$id = (int) (json_decode($r['body'], true)['id'] ?? 0);
$db->exec('UPDATE ' . $db->t('mapaviajes_trips') . ' SET published = 1 WHERE id = :i', [':i' => $id]);
$db->exec('DELETE FROM ' . $db->t('mapaviajes_reactions') . ' WHERE trip_id = :i', [':i' => $id]);
$db->setParams(['review_first' => 1]);
echo $id;
