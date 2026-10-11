<?php
declare(strict_types=1);
// Seeds albums that share a place (1, 2, 4 and 7 in four far-apart cities) plus a few people, copying an existing stored photo: php semilla-grupos.php
require __DIR__ . '/http.php';
$db = new Bd(getenv('MV_PREFIX') ?: 'jt_'); $st = rtrim((string) getenv('MV_STORAGE'), '/'); $T = $db->t('mapaviajes_trips'); $P = $db->t('mapaviajes_photos');
$src0 = $db->uno("SELECT filename FROM $P ORDER BY id LIMIT 1"); if (!$src0) { fwrite(STDERR, "hace falta una foto guardada (ejecuta antes 10-envio)\n"); exit(1); }
$src = 'base-grupos.webp'; copy("$st/photos/$src0", "$st/photos/$src");
foreach ($db->filas("SELECT p.filename FROM $P p JOIN $T t ON t.id = p.trip_id WHERE t.title LIKE 'SEED%'") as $r) { @unlink("$st/photos/{$r['filename']}"); }
$db->exec("DELETE FROM $P WHERE trip_id IN (SELECT id FROM $T WHERE title LIKE 'SEED%')"); $db->exec("DELETE FROM $T WHERE title LIKE 'SEED%'");
// Trips left by other suites would merge with these groups; remove them (the other suites recreate what they need).
foreach ($db->filas("SELECT p.filename FROM $P p JOIN $T t ON t.id = p.trip_id WHERE t.title NOT LIKE 'SEED%'") as $r) { @unlink("$st/photos/{$r['filename']}"); }
$db->exec("DELETE FROM $P WHERE trip_id IN (SELECT id FROM $T WHERE title NOT LIKE 'SEED%')"); $db->exec("DELETE FROM $T WHERE title NOT LIKE 'SEED%'");
$users = array_column($db->filas('SELECT id FROM ' . $db->t('users') . ' ORDER BY id LIMIT 4'), 'id');
// far apart on purpose, so that each group is alone on the map
$plan = ['Sidney' => [-33.8688, 151.2093, 2], 'Paris' => [48.8566, 2.3522, 4], 'Tokio' => [35.6762, 139.6503, 7], 'Nueva York' => [40.7128, -74.006, 1]];
$n = 0;
foreach ($plan as $city => [$la, $lo, $k]) { for ($i = 1; $i <= $k; $i++) { $n++; $u = (int) $users[$n % count($users)];
	$db->exec("INSERT INTO $T (album_id,user_id,title,place,lat,lon,allow_comments,notify,published,photo_count,trip_year,created) VALUES (1,$u,'SEED $city $i','$city',$la,$lo,1,0,1,1,2020,NOW())");
	$id = (int) $db->uno("SELECT max(id) FROM $T"); @mkdir("$st/photos/$u", 0750, true); $name = "$u/viaje_2020_{$id}_" . bin2hex(random_bytes(4)) . '.webp';
	copy("$st/photos/$src", "$st/photos/$name"); $db->exec("INSERT INTO $P (trip_id,ordering,filename,width,height,bytes,caption,created) VALUES ($id,1,'$name',1000,667,1000,'',NOW())"); } }
echo "sembrados $n\n";
