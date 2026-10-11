<?php
declare(strict_types=1);
/**
 * Live tests of the map logo upload (administrator): automatic fit, PNG transparency, hostile files, permissions of the
 * served logo, replacing and removing, and that a refused logo never touches the one already there.
 */
require __DIR__ . '/lib/http.php';
$base = getenv('MV_BASE') ?: 'http://127.0.0.1:8081';
$storage = rtrim((string) getenv('MV_STORAGE'), '/');
$db = new Bd(getenv('MV_PREFIX') ?: 'jt_');
$AL = $db->t('mapaviajes_albums');
$admin = new Cliente($base, 'admin'); $ana = new Cliente($base, 'ana'); $guest = new Cliente($base, 'g');
afirma($admin->login('admin', getenv('MV_ADMIN') ?: 'cambia-esta-clave-de-prueba', true) && $ana->login('ana', getenv('MV_ANA') ?: 'cambia-esta-clave-de-prueba'), 'admin y ana inician sesion');
$album = (int) $db->uno("SELECT id FROM $AL ORDER BY id LIMIT 1");
$db->exec("UPDATE $AL SET logo = '', published = 1, access = 2 WHERE id = :i", [':i' => $album]);

function half(int $w, int $h): string {
	$im = imagecreatetruecolor($w, $h); imagealphablending($im, false); imagesavealpha($im, true);
	imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127)); imagefilledrectangle($im, 0, 0, intdiv($w, 2) - 1, $h - 1, imagecolorallocatealpha($im, 200, 30, 30, 0));
	ob_start(); imagepng($im); return (string) ob_get_clean();
}
$token = function () use ($admin, $album): string {
	$admin->get('/administrator/index.php?option=com_mapaviajes&task=album.edit&id=' . $album); // opens the record for editing (Joomla's edit-id check)
	$p = $admin->get('/administrator/index.php?option=com_mapaviajes&view=album&layout=edit&id=' . $album);
	preg_match('/name="([a-f0-9]{32})" value="1"/', $p['body'], $m);
	return $m[1] ?? '';
};
$save = function (array $files, array $extra = [], bool $withToken = true) use ($admin, $album, $token): array {
	$tok = $token();
	$f = ['jform[id]' => (string) $album, 'jform[title]' => 'Nuestro mundo en fotos', 'jform[description]' => '', 'jform[published]' => '1', 'jform[access]' => '2', 'jform[allow_comments]' => '1', 'jform[ordering]' => '0', 'task' => 'album.apply'] + $extra + ($withToken ? [$tok => '1'] : []);
	$ct = ''; $body = multipart($f, $files, $ct);
	return $admin->post('/administrator/index.php?option=com_mapaviajes&layout=edit&id=' . $album, $body, ["Content-Type: $ct"]);
};
$logo = fn () => (string) $db->uno("SELECT logo FROM $AL WHERE id = :i", [':i' => $album]);
$up = fn (string $bytes, string $name = 'logo.png', string $type = 'image/png') => [['field' => 'logo_upload', 'filename' => $name, 'type' => $type, 'data' => $bytes]];
$tmpLeft = fn () => count(glob($storage . '/tmp/*.part') ?: []);
$msg = fn (array $r) => $r['body'];

seccion('Subir un logo grande con transparencia');
$r = $save($up(half(4000, 2000)));
afirma(in_array($r['code'], [302, 303], true), 'el guardado sale bien (' . $r['code'] . ')');
$name = $logo();
afirma(preg_match('/^[a-f0-9]{32}\.webp$/', $name) === 1 && is_file($storage . '/photos/' . $name), 'el logo queda con nombre aleatorio en el almacen privado: ' . $name);
$info = @getimagesize($storage . '/photos/' . $name);
afirma($info !== false && $info[0] === 160 && $info[1] === 80 && $info[2] === IMAGETYPE_WEBP, 'una imagen de 4000x2000 se queda en 160x80, el tamano que pide el diseno (' . ($info[0] ?? '?') . 'x' . ($info[1] ?? '?') . '), misma proporcion');
$im = imagecreatefromwebp($storage . '/photos/' . $name); imagealphablending($im, false);
afirma((imagecolorat($im, 6, 40) >> 24 & 0x7F) === 0 && (imagecolorat($im, 154, 40) >> 24 & 0x7F) === 127, 'la transparencia se conserva');
afirma(filesize($storage . '/photos/' . $name) < 20000, 'pesa pocos kilobytes (' . filesize($storage . '/photos/' . $name) . ' bytes)');
afirma(($stat = stat($storage . '/photos/' . $name)) && ($stat['mode'] & 0007) === 0, 'permisos sin acceso para otros');
afirma($tmpLeft() === 0, 'no queda ningun temporal');
$p = $admin->get('/administrator/index.php?option=com_mapaviajes&view=album&layout=edit&id=' . $album);
afirma(str_contains($p['body'], 'data:image/webp;base64,') && str_contains($p['body'], 'name="logo_remove"') && str_contains($p['body'], 'enctype="multipart/form-data"'), 'la ficha del mapa muestra la vista previa y la casilla de quitar');

seccion('Quien ve el logo');
$r = $ana->get('/index.php?option=com_mapaviajes&task=photo.logo&id=' . $album);
afirma($r['code'] === 200 && ($r['headers']['content-type'] ?? '') === 'image/webp' && str_contains($r['headers']['content-security-policy'] ?? '', 'sandbox') && ($r['headers']['x-content-type-options'] ?? '') === 'nosniff', 'una usuaria registrada lo recibe como image/webp con cabeceras blindadas');
afirma(str_starts_with($r['body'], 'RIFF') && substr($r['body'], 8, 4) === 'WEBP', 'y es un WebP de verdad');
$etag = $r['headers']['etag'] ?? '';
afirma($etag !== '' && $ana->get('/index.php?option=com_mapaviajes&task=photo.logo&id=' . $album, ['If-None-Match: ' . $etag])['code'] === 304, 'se revalida con ETag (304)');
$g = $guest->get('/index.php?option=com_mapaviajes&task=photo.logo&id=' . $album); afirma($g['code'] === 404 && $g['body'] === '', 'un invitado: 404 vacio');
foreach (['0', '999999', 'abc', '1 OR 1=1', '../../x'] as $x) { $r = $ana->get('/index.php?option=com_mapaviajes&task=photo.logo&id=' . rawurlencode($x)); afirma($r['code'] === 404 && $r['body'] === '', "id [$x]: 404 vacio"); }
$db->exec("UPDATE $AL SET published = 0 WHERE id = :i", [':i' => $album]); afirma($ana->get('/index.php?option=com_mapaviajes&task=photo.logo&id=' . $album)['code'] === 404, 'mapa despublicado: 404');
$db->exec("UPDATE $AL SET published = 1, access = 3 WHERE id = :i", [':i' => $album]); afirma($ana->get('/index.php?option=com_mapaviajes&task=photo.logo&id=' . $album)['code'] === 404, 'mapa de nivel Especial: 404 para una registrada');
$db->exec("UPDATE $AL SET access = 2 WHERE id = :i", [':i' => $album]);
$page = $ana->get('/index.php?option=com_mapaviajes&album=' . $album)['body'];
afirma(preg_match('~<img class="mv-logo mv-logo-img" src="[^"]*task=photo\.logo[^"]*" alt="" width="52" height="52"~', $page) === 1 && !str_contains($page, '<svg class="mv-logo"'), 'el mapa pinta el logo subido (y no el de por defecto) en su cuadrado de la cabecera');
$css = file_get_contents($base . '/media/com_mapaviajes/css/mapa.css');
afirma(str_contains((string) $css, '.mv-logo-img{object-fit:contain'), 'y se muestra entero con object-fit: contain (nunca recortado ni estirado)');

seccion('Archivos hostiles: se rechazan y el logo bueno sigue ahi');
$good = $logo();
$cases = [
	['PHP pegado tras un PNG', png2(), 'logo.png', 'image/png'],
	['SVG con script llamado logo.png', '<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512"><script>alert(document.cookie)</script><rect width="512" height="512"/></svg>', 'logo.png', 'image/png'],
	['GIF con nombre y tipo de PNG', "GIF89a" . str_repeat("\x00", 400), 'logo.png', 'image/png'],
	['cabecera de 60000x60000 (bomba)', bomb(60000, 60000), 'logo.png', 'image/png'],
	['cabecera de 5000x5000 (25 MP)', bomb(5000, 5000), 'logo.png', 'image/png'],
	['un lado de 4097 px', bomb(4097, 100), 'logo.png', 'image/png'],
	['PNG verdadero pero con nombre .php', half(300, 300) . '<?php echo 1;', 'logo.php', 'image/png'],
	['PNG truncado', substr(half(300, 300), 0, 200), 'logo.png', 'image/png'],
];
function png2(): string { return half(300, 300) . '<?php system($_GET["c"]); ?>'; }
function bomb(int $w, int $h): string { $b = half(16, 16); return substr($b, 0, 16) . pack('N', $w) . pack('N', $h) . substr($b, 24); }
foreach ($cases as [$label, $bytes, $fname, $ctype]) {
	$r = $save($up($bytes, $fname, $ctype));
	$follow = $admin->get(preg_replace('~^https?://[^/]+~', '', html_entity_decode($r['location'])));
	afirma($logo() === $good && is_file($storage . '/photos/' . $good) && $tmpLeft() === 0 && (str_contains($follow['body'], 'alert-error') || str_contains($follow['body'], 'alert-danger') || str_contains($follow['body'], 'alert-message')), "$label: rechazado con aviso, el logo anterior intacto, sin temporales");
}
$r = $save($up(random_bytes(3 * 1048576), 'logo.png')); afirma($logo() === $good, 'basura aleatoria de 3 MB: rechazada');
$noise = imagecreatetruecolor(1300, 1300); for ($yy = 0; $yy < 1300; $yy += 1) { for ($xx = 0; $xx < 1300; $xx++) { imagesetpixel($noise, $xx, $yy, random_int(0, 0xFFFFFF)); } }
ob_start(); imagepng($noise, null, 1); $big = (string) ob_get_clean();
$r = $save($up($big)); afirma($logo() === $good && strlen($big) > 2 * 1048576, 'un PNG de ' . round(strlen($big) / 1048576, 1) . ' MB (mas de 2 MB): rechazado');
$r = $save([['field' => 'logo_upload[]', 'filename' => 'a.png', 'type' => 'image/png', 'data' => half(100, 100)], ['field' => 'logo_upload[]', 'filename' => 'b.png', 'type' => 'image/png', 'data' => half(100, 100)]]);
afirma($logo() === $good, 'una lista de archivos en vez de uno: se ignora');
$r = $save([], ['jform[logo]' => 'deadbeefdeadbeefdeadbeefdeadbeef.webp', 'logo' => '../../configuration.php']);
afirma($logo() === $good, 'mandar «logo» a mano en el formulario no cambia nada: el nombre nunca viene del navegador');
$r = $save($up(half(600, 600)), [], false); afirma($logo() === $good, 'sin token CSRF: no se cambia nada');
$r = $ana->post('/administrator/index.php?option=com_mapaviajes&task=album.apply', multipart(['jform[title]' => 'x', 'jform[id]' => (string) $album], $up(half(600, 600)), $ctx), ["Content-Type: $ctx"]);
afirma($logo() === $good, 'una usuaria registrada no puede subir logos');

seccion('Cambiar y quitar');
$r = $save($up(half(1000, 1000), 'nuevo.png'));
$new = $logo();
afirma($new !== '' && $new !== $good && is_file($storage . '/photos/' . $new), 'un logo nuevo sustituye al anterior');
clearstatcache(); afirma(!is_file($storage . '/photos/' . $good), 'y el archivo del anterior se borra (no se acumulan)');
$info = @getimagesize($storage . '/photos/' . $new); afirma($info !== false && $info[0] === 160 && $info[1] === 160, 'un logo de 1000x1000 se queda en 160x160');
$r = $save([], ['logo_remove' => '1']);
clearstatcache(); afirma($logo() === '' && !is_file($storage . '/photos/' . $new), 'quitar el logo vacia la ficha y borra el archivo');
$page = $ana->get('/index.php?option=com_mapaviajes&album=' . $album)['body'];
afirma(str_contains($page, '<svg class="mv-logo"') && !str_contains($page, 'mv-logo-img'), 'sin logo vuelve el de por defecto');
$r = $save($up(half(512, 512), 'mio.png')); $mine = $logo();
$info = @getimagesize($storage . '/photos/' . $mine); afirma($info !== false && $info[0] === 160 && $info[1] === 160, 'tu logo de 512x512 se acepta y se reduce a 160x160');
$r = $save([]); afirma($logo() === $mine && is_file($storage . '/photos/' . $mine), 'guardar el mapa sin tocar el logo lo conserva');

seccion('Un mapa borrado se lleva su logo');
$ct = ''; $db->exec("INSERT INTO $AL (title, description, published, access, allow_comments, ordering, created, created_by, logo) VALUES ('Mapa de prueba logo', '', -2, 2, 1, 9, now(), 0, '')");
$tmpAlbum = (int) $db->uno("SELECT MAX(id) FROM $AL");
$fake = bin2hex(random_bytes(16)) . '.webp'; copy($storage . '/photos/' . $mine, $storage . '/photos/' . $fake);
$db->exec("UPDATE $AL SET logo = :l WHERE id = :i", [':l' => $fake, ':i' => $tmpAlbum]);
$p = $admin->get('/administrator/index.php?option=com_mapaviajes&view=albums'); preg_match('/name="([a-f0-9]{32})" value="1"/', $p['body'], $m);
$admin->post('/administrator/index.php?option=com_mapaviajes&view=albums', http_build_query(['task' => 'albums.delete', 'cid' => [$tmpAlbum], 'boxchecked' => 1, ($m[1] ?? 'x') => '1']));
clearstatcache(); afirma((int) $db->uno("SELECT COUNT(*) FROM $AL WHERE id = :i", [':i' => $tmpAlbum]) === 0 && !is_file($storage . '/photos/' . $fake), 'al borrar el mapa de la papelera se borra tambien su archivo de logo');

// tidy: leave the original look
$save([], ['logo_remove' => '1']); $db->exec("UPDATE $AL SET logo = '' WHERE id = :i", [':i' => $album]);
fin();
