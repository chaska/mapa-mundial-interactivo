<?php
declare(strict_types=1);
require __DIR__ . '/aserciones.php';
$base = __DIR__ . '/../src/component/backend/src/Security/';
require $base . 'SanitizeException.php';
require $base . 'ImageProcessor.php';
require $base . 'PrivateStorage.php';
use Chaska\Component\Mapaviajes\Administrator\Security\{ImageProcessor, PrivateStorage, SanitizeException};

$work = sys_get_temp_dir() . '/mv-img-' . bin2hex(random_bytes(4));
mkdir($work, 0700);
register_shutdown_function(static function () use ($work) { foreach (glob($work . '/*') ?: [] as $f) { @unlink($f); } @rmdir($work); });

afirma(ImageProcessor::isSupported(), 'GD con soporte WebP/JPEG/PNG disponible');

function fichero(string $contenido, string $ext = 'bin'): string { global $work; $p = $work . '/' . bin2hex(random_bytes(6)) . '.' . $ext; file_put_contents($p, $contenido); return $p; }
function jpeg(int $w, int $h, int $q = 85): string {
	$im = imagecreatetruecolor($w, $h);
	for ($i = 0; $i < 60; $i++) { imagefilledellipse($im, random_int(0, $w), random_int(0, $h), random_int(1, max(1, $w)), random_int(1, max(1, $h)), imagecolorallocate($im, random_int(0, 255), random_int(0, 255), random_int(0, 255))); }
	ob_start(); imagejpeg($im, null, $q); return (string) ob_get_clean();
}
function png(int $w, int $h, bool $alpha = false): string {
	$im = imagecreatetruecolor($w, $h); imagealphablending($im, false); imagesavealpha($im, true);
	imagefill($im, 0, 0, imagecolorallocatealpha($im, 200, 30, 30, $alpha ? 64 : 0));
	ob_start(); imagepng($im); return (string) ob_get_clean();
}
function withApp1(string $jpeg, string $payload): string { $seg = "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload; return substr($jpeg, 0, 2) . $seg . substr($jpeg, 2); }
function exif(int $orientation, bool $gps = false): string {
	// minimal big-endian TIFF with an Orientation tag (and optionally a GPS-looking tag + text)
	$tiff = 'MM' . pack('n', 42) . pack('N', 8) . pack('n', 1) . pack('n', 0x0112) . pack('n', 3) . pack('N', 1) . pack('n', $orientation) . "\0\0" . pack('N', 0);
	return "Exif\0\0" . $tiff . ($gps ? 'GPSLatitude=40.4168;GPSLongitude=-3.7038;Make=SECRETCAMERA' : '');
}
function subir(string $path, array $extra = []): array { return array_merge(['name' => 'foto.jpg', 'type' => 'image/jpeg', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => (int) filesize($path)], $extra); }
function intenta(ImageProcessor $p, array $file): array {
	global $work; $dest = $work . '/out-' . bin2hex(random_bytes(4)) . '.part';
	try { $r = $p->process($file, $dest); return ['ok' => true, 'r' => $r, 'dest' => $dest]; }
	catch (SanitizeException $e) { return ['ok' => false, 'reason' => $e->getReason(), 'left' => is_file($dest)]; }
}
function rechazada(ImageProcessor $p, array $file, string $reason, string $msg): void {
	$x = intenta($p, $file);
	afirma(!$x['ok'] && $x['reason'] === $reason && !$x['left'], "$msg -> " . ($x['ok'] ? 'ACEPTADA' : $x['reason'] . ($x['left'] ? ' (dejo fichero!)' : '')));
}
$p = new ImageProcessor(10 * 1048576, 1000, 80, false, 40000000, 12000, static fn (string $f): bool => true);

seccion('Conversion correcta: WebP, lado largo 1000, misma proporcion');
$x = intenta($p, subir(fichero(jpeg(3000, 2000), 'jpg')));
afirma($x['ok'] && $x['r']['width'] === 1000 && $x['r']['height'] === 667, 'horizontal 3000x2000 -> 1000x667 (' . ($x['r']['width'] ?? '?') . 'x' . ($x['r']['height'] ?? '?') . ')');
afirma($x['ok'] && (new finfo(FILEINFO_MIME_TYPE))->file($x['dest']) === 'image/webp' && str_starts_with(file_get_contents($x['dest'], false, null, 0, 12), 'RIFF'), 'el resultado es WebP de verdad');
afirma($x['ok'] && (fileperms($x['dest']) & 0007) === 0, 'permisos sin acceso para "otros"');
$x = intenta($p, subir(fichero(jpeg(2000, 3000), 'jpg')));
afirma($x['ok'] && $x['r']['width'] === 667 && $x['r']['height'] === 1000, 'vertical 2000x3000 -> 667x1000');
$x = intenta($p, subir(fichero(jpeg(2400, 2400), 'jpg')));
afirma($x['ok'] && $x['r']['width'] === 1000 && $x['r']['height'] === 1000, 'cuadrada 2400x2400 -> 1000x1000');
$x = intenta($p, subir(fichero(jpeg(400, 300), 'jpg')));
afirma($x['ok'] && $x['r']['width'] === 400 && $x['r']['height'] === 300, 'pequena 400x300 no se amplia');
$x = intenta($p, subir(fichero(png(1500, 900, true), 'png')));
afirma($x['ok'] && $x['r']['width'] === 1000 && $x['r']['height'] === 600, 'PNG con transparencia -> 1000x600');
$x = intenta($p, subir(fichero(jpeg(1200, 800), 'webp')));
afirma($x['ok'], 'JPEG con extension equivocada se acepta por su contenido real y se reconvierte');

seccion('Orientacion EXIF y borrado de metadatos');
$x = intenta($p, subir(fichero(withApp1(jpeg(1600, 1000), exif(6)), 'jpg')));
afirma($x['ok'] && $x['r']['width'] === 625 && $x['r']['height'] === 1000, 'orientacion 6 (movil en vertical): 1600x1000 -> 625x1000');
$x = intenta($p, subir(fichero(withApp1(jpeg(1600, 1000), exif(8)), 'jpg')));
afirma($x['ok'] && $x['r']['height'] === 1000, 'orientacion 8 tambien gira');
$x = intenta($p, subir(fichero(withApp1(jpeg(1600, 1000), exif(1, true)), 'jpg')));
$out = $x['ok'] ? file_get_contents($x['dest']) : '';
afirma($x['ok'] && !str_contains($out, 'GPS') && !str_contains($out, 'SECRETCAMERA') && !str_contains($out, 'Exif'), 'GPS, marca de camara y EXIF NO sobreviven en la salida');
foreach ([0, 9, 255, 65535] as $o) { $x = intenta($p, subir(fichero(withApp1(jpeg(800, 600), exif($o)), 'jpg'))); afirma($x['ok'] && $x['r']['width'] === 800, "orientacion invalida $o se ignora sin romper"); }
afirma(ImageProcessor::jpegOrientation("\xFF\xD8\xFF\xE1\xFF\xFF") === 1, 'APP1 con longitud que se sale del archivo: 1, sin errores');
afirma(ImageProcessor::jpegOrientation(str_repeat("\xFF\xE1\x00\x02", 200)) === 1, 'cadena infinita de segmentos: termina');

seccion('Archivos disfrazados y cabeceras cambiadas');
rechazada($p, subir(fichero("GIF89a" . str_repeat("\x00", 200) . '<?php system($_GET["c"]); ?>', 'jpg'), ['type' => 'image/jpeg', 'name' => 'x.jpg']), 'image_type', 'GIF/PHP que dice ser JPEG');
rechazada($p, subir(fichero('<?php system($_GET["c"]); ?>' . str_repeat('A', 300), 'php'), ['type' => 'image/jpeg', 'name' => 'x.php.jpg']), 'image_type', 'PHP puro con Content-Type de imagen');
rechazada($p, subir(fichero('<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)">' . str_repeat(' ', 200) . '</svg>', 'png'), ['type' => 'image/png']), 'image_type', 'SVG con script renombrado a .png');
rechazada($p, subir(fichero('<html><script>alert(1)</script></html>' . str_repeat(' ', 200), 'jpg'), ['type' => 'image/jpeg']), 'image_type', 'HTML renombrado a .jpg');
rechazada($p, subir(fichero("%PDF-1.4\n" . str_repeat('x', 300), 'jpg')), 'image_type', 'PDF renombrado');
rechazada($p, subir(fichero("\x00\x00\x00\x18ftypheic" . str_repeat("\x00", 300), 'jpg')), 'image_type', 'HEIC/AVIF renombrado');
rechazada($p, subir(fichero("BM" . str_repeat("\x00", 300), 'jpg')), 'image_type', 'BMP renombrado');
rechazada($p, subir(fichero("PK\x03\x04" . str_repeat("\x00", 300), 'jpg')), 'image_type', 'ZIP renombrado');
rechazada($p, subir(fichero("\x7FELF" . str_repeat("\x00", 300), 'jpg')), 'image_type', 'ejecutable ELF renombrado');
rechazada($p, subir(fichero("\xFF\xD8\xFF" . str_repeat("\x00", 300), 'jpg')), 'image_type', 'solo la firma JPEG y basura detras');

seccion('Poliglotas: imagen valida con codigo escondido');
rechazada($p, subir(fichero(jpeg(800, 600) . '<?php system($_GET["c"]); ?>', 'jpg')), 'image_polyglot', 'JPEG valido + PHP al final');
rechazada($p, subir(fichero(withApp1(jpeg(800, 600), "Exif\0\0<?php eval(\$_POST[1]);?>"), 'jpg')), 'image_polyglot', 'PHP dentro del EXIF');
rechazada($p, subir(fichero(png(800, 600) . '<script>alert(1)</script>', 'png')), 'image_polyglot', 'PNG valido + script al final');
rechazada($p, subir(fichero(jpeg(800, 600) . "\n<html><body onload=alert(1)>", 'jpg')), 'image_polyglot', 'JPEG + HTML');
rechazada($p, subir(fichero(jpeg(800, 600) . "\n<SVG onload=alert(1)>", 'jpg')), 'image_polyglot', 'JPEG + SVG en mayusculas');

seccion('Archivos rotos, vacios o enormes');
rechazada($p, subir(fichero('', 'jpg')), 'image_unreadable', 'archivo vacio');
rechazada($p, subir(fichero(substr(jpeg(1200, 800), 0, 4000), 'jpg')), 'image_unreadable', 'JPEG truncado');
$pngBomb = png(9000, 9000); // ~81 Mpx, pesa poco
rechazada($p, subir(fichero($pngBomb, 'png')), 'image_dimensions', 'bomba de descompresion PNG 9000x9000 (' . round(strlen($pngBomb) / 1024) . ' KB)');
$hdr = "\x89PNG\r\n\x1A\n" . pack('N', 13) . 'IHDR' . pack('N', 60000) . pack('N', 60000) . "\x08\x02\x00\x00\x00" . "\0\0\0\0" . str_repeat("\0", 200);
rechazada($p, subir(fichero($hdr, 'png')), 'image_dimensions', 'cabecera PNG que declara 60000x60000 sin datos validos');
$big = jpeg(3000, 3000, 100);
$small = new ImageProcessor(strlen($big) - 1, 1000, 80, false, 40000000, 12000, static fn (string $f): bool => true);
rechazada($small, subir(fichero($big, 'jpg')), 'image_too_big', 'archivo mayor que el limite');
rechazada($p, subir(fichero(jpeg(8, 8), 'jpg')), 'image_dimensions', 'imagen minuscula 8x8');

seccion('Peticion manipulada: errores de subida y rutas ajenas');
rechazada($p, ['name' => 'a.jpg', 'type' => 'image/jpeg', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0], 'image_upload', 'sin archivo');
rechazada($p, ['name' => 'a.jpg', 'type' => 'image/jpeg', 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0], 'image_too_big', 'supera upload_max_filesize');
rechazada($p, ['name' => 'a.jpg', 'type' => 'image/jpeg', 'tmp_name' => '', 'error' => UPLOAD_ERR_PARTIAL, 'size' => 0], 'image_upload', 'subida parcial');
rechazada($p, ['name' => 'a.jpg', 'type' => 'image/jpeg', 'tmp_name' => '/etc/passwd', 'error' => UPLOAD_ERR_OK, 'size' => 100], 'image_type', '/etc/passwd como tmp_name (aunque PHP lo dejara pasar)');
$strict = new ImageProcessor();   // is_uploaded_file() REAL: un fichero cualquiera NO cuenta como subido
rechazada($strict, subir(fichero(jpeg(800, 600), 'jpg')), 'image_upload', 'fichero local que PHP no ha recibido por HTTP');
rechazada($strict, ['name' => 'a.jpg', 'tmp_name' => '/etc/passwd', 'error' => 0, 'size' => 1], 'image_upload', 'tmp_name=/etc/passwd con comprobacion real');
rechazada($p, ['name' => 'a.jpg', 'tmp_name' => "/tmp/x\0.jpg", 'error' => 0, 'size' => 1], 'image_upload', 'byte nulo en la ruta');
rechazada($p, ['name' => 'a.jpg', 'tmp_name' => $work, 'error' => 0, 'size' => 1], 'image_upload', 'tmp_name es un directorio');
$real = fichero(jpeg(800, 600), 'jpg'); $ln = $work . '/enlace.jpg'; symlink($real, $ln);
rechazada($p, subir($ln), 'image_upload', 'enlace simbolico');
rechazada($p, ['name' => 'a.jpg'], 'image_upload', 'entrada sin campos');
$x = intenta($p, subir(fichero(jpeg(900, 600), 'jpg'), ['name' => '../../../../etc/cron.d/x.php', 'type' => 'application/x-php', 'size' => 1]));
afirma($x['ok'] && dirname($x['dest']) === $work, 'nombre, tipo y tamano declarados por el cliente se ignoran por completo');

seccion('Almacen privado');
$st = new PrivateStorage($work . '/store');
$st->prepare();
afirma(is_file($work . '/store/.htaccess') && is_file($work . '/store/photos/.htaccess') && is_file($work . '/store/web.config') && is_file($work . '/store/photos/index.html'), 'se crean .htaccess, web.config e index.html (raiz y fotos)');
afirma(str_contains((string) file_get_contents($work . '/store/.htaccess'), 'Require all denied'), '.htaccess deniega todo');
$tmp = $st->newTemporaryPath();
$r = $p->process(subir(fichero(jpeg(1200, 800), 'jpg')), $tmp);
$name = $st->commit($tmp);
afirma(preg_match(PrivateStorage::NAME_PATTERN, $name) === 1 && !is_file($tmp), 'nombre final aleatorio de 128 bits y temporal movido');
afirma($st->pathFor($name) !== null, 'se encuentra el archivo guardado');
foreach (['../x.webp', '..%2f..%2fetc%2fpasswd', $name . '/../../x', strtoupper($name), substr($name, 0, 31), $name . "\0", '', '.htaccess', 'index.html', 'a.php', str_repeat('a', 32) . '.php'] as $bad) {
	afirma($st->pathFor($bad) === null, 'pathFor rechaza [' . addcslashes($bad, "\0..\37") . ']');
}
$st->delete($name); afirma($st->pathFor($name) === null, 'delete borra el archivo');
$tmp = $st->newTemporaryPath(); $p->process(subir(fichero(jpeg(1200, 800), 'jpg')), $tmp);
$un = $st->commit($tmp, 12, 2019, 3);
afirma(preg_match('~^12/viaje_2019_3_[a-f0-9]{8}\.webp$~', $un) === 1 && is_dir($work . '/store/photos/12') && $st->pathFor($un) !== null, 'foto con usuario: photos/12/viaje_2019_3_<aleatorio>.webp (' . $un . ')');
foreach (['12/../13/viaje_2019_1_aaaaaaaa.webp', '12/../../x.webp', '12/13/a.webp', '/12/a.webp', 'a/viaje.webp', '12/.htaccess', '12/viaje 2019.webp', '12/Viaje_2019.webp', '123456789012/a.webp', "12/a.webp\0"] as $bad) {
	afirma($st->pathFor($bad) === null, 'pathFor rechaza [' . addcslashes($bad, "\0..\37") . ']');
}
$st->delete($un); afirma($st->pathFor($un) === null && !is_dir($work . '/store/photos/12'), 'al borrar la ultima foto de la persona se quita su carpeta vacia');
$st->delete('../../etc/passwd'); afirma(is_file('/etc/passwd'), 'delete con ruta hostil no toca nada');
file_put_contents($work . '/store/tmp/viejo.part', 'x'); touch($work . '/store/tmp/viejo.part', time() - 7200);
$st->sweep(3600); afirma(!is_file($work . '/store/tmp/viejo.part'), 'sweep borra temporales abandonados');
@unlink($work . '/store/tmp/../../../outside.part');

seccion('Logo del mapa: PNG con transparencia, ajuste automatico, mismas defensas');
$lp = new ImageProcessor(5 * 1048576, 512, 100, false, 16000000, 4096, static fn (string $f): bool => true, true);
function pngHalf(int $w, int $h): string { // left half opaque red, right half completely transparent
	$im = imagecreatetruecolor($w, $h); imagealphablending($im, false); imagesavealpha($im, true);
	imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
	imagefilledrectangle($im, 0, 0, intdiv($w, 2) - 1, $h - 1, imagecolorallocatealpha($im, 200, 30, 30, 0));
	ob_start(); imagepng($im); return (string) ob_get_clean();
}
$x = intenta($lp, subir(fichero(pngHalf(4000, 2000), 'png'), ['name' => 'logo.png', 'type' => 'image/png']));
afirma($x['ok'] && $x['r']['width'] === 512 && $x['r']['height'] === 256, 'logo 4000x2000 -> 512x256 (el tamano que pide el diseno): se ajusta solo y conserva la proporcion (' . ($x['r']['width'] ?? '?') . 'x' . ($x['r']['height'] ?? '?') . ')');
if ($x['ok']) {
	$im = imagecreatefromwebp($x['dest']); imagealphablending($im, false);
	$left = imagecolorat($im, 6, 40) >> 24 & 0x7F; $right = imagecolorat($im, 506, 40) >> 24 & 0x7F;
	afirma($left === 0 && $right === 127, 'la transparencia sobrevive: mitad izquierda opaca, derecha transparente (alfa ' . $left . ' / ' . $right . ')');
	afirma(substr((string) file_get_contents($x['dest'], false, null, 12, 4), 0, 4) === 'VP8L', 'se guarda sin perdida (WebP VP8L): los bordes duros del logo no se emborronan');
}
$x = intenta($lp, subir(fichero(pngHalf(512, 512), 'png')));
afirma($x['ok'] && $x['r']['width'] === 512 && $x['r']['height'] === 512, 'tu logo de 512x512 se queda en 512x512 (el diseno lo pinta a 208 px: mas de 2x para retina)');
$x = intenta($lp, subir(fichero(pngHalf(2000, 4000), 'png')));
afirma($x['ok'] && $x['r']['width'] === 256 && $x['r']['height'] === 512, 'vertical 2000x4000 -> 256x512');
$x = intenta($lp, subir(fichero(pngHalf(128, 128), 'png')));
afirma($x['ok'] && $x['r']['width'] === 128 && $x['r']['height'] === 128, 'un logo pequeno no se amplia (128x128)');
$x = intenta($lp, subir(fichero(jpeg(1500, 700), 'jpg')));
afirma($x['ok'] && $x['r']['width'] === 512 && $x['r']['height'] === 239, 'JPG tambien vale (1500x700 -> 512x239)');
function conChunk(string $png, string $type, string $data): string { // an ancillary chunk just before IEND, with a valid CRC
	$c = pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
	return substr($png, 0, -12) . $c . substr($png, -12);
}
$meta = conChunk(pngHalf(600, 600), 'caBX', 'jumb c2pa <?php system($_GET[1]); ?> <svg onload=1> eval(x)');
$x = intenta($lp, subir(fichero($meta, 'png'), ['name' => 'logo.png', 'type' => 'image/png']));
afirma($x['ok'], 'PNG con metadatos descriptivos (C2PA/XMP de herramientas de IA) que mencionan codigo: se acepta, porque el re-codificado los tira');
if ($x['ok']) { $o = (string) file_get_contents($x['dest']); afirma(!str_contains($o, '<?php') && !str_contains($o, 'c2pa') && !str_contains($o, 'caBX'), 'y el archivo guardado no conserva nada de ellos'); }
rechazada($lp, subir(fichero(pngHalf(600, 600) . 'x<?php system($_GET[1]); ?>', 'png')), 'image_polyglot', 'codigo pegado DESPUES de IEND sigue rechazado');
rechazada($lp, subir(fichero(conChunk(pngHalf(600, 600), 'IDAT', '<?php system($_GET[1]); ?>'), 'png')), 'image_polyglot', 'codigo dentro de un chunk de pixeles sigue rechazado');
$bigHeader = function (int $w, int $h): string { $b = png(16, 16); $b = substr($b, 0, 16) . pack('N', $w) . pack('N', $h) . substr($b, 24); return $b; };
rechazada($lp, subir(fichero($bigHeader(5000, 5000), 'png')), 'image_dimensions', 'cabecera PNG que dice 5000x5000 (25 MP) se rechaza SIN decodificar');
rechazada($lp, subir(fichero($bigHeader(4097, 100), 'png')), 'image_dimensions', 'un lado de 4097 px');
rechazada($lp, subir(fichero($bigHeader(60000, 60000), 'png')), 'image_dimensions', 'bomba de descompresion 60000x60000');
rechazada($lp, subir(fichero($bigHeader(8, 8), 'png')), 'image_dimensions', 'demasiado pequeno (8x8)');
$noise = imagecreatetruecolor(1500, 1500); for ($yy = 0; $yy < 1500; $yy++) { for ($xx = 0; $xx < 1500; $xx++) { imagesetpixel($noise, $xx, $yy, random_int(0, 0xFFFFFF)); } }
ob_start(); imagepng($noise, null, 1); $noisePng = (string) ob_get_clean();
rechazada($lp, subir(fichero($noisePng, 'png')), 'image_too_big', 'un PNG de ' . round(strlen($noisePng) / 1048576, 1) . ' MB supera el limite de 5 MB');
rechazada($lp, subir(fichero(png(300, 300) . '<?php system($_GET["c"]); ?>', 'png')), 'image_polyglot', 'PNG con PHP pegado detras (poliglota)');
rechazada($lp, subir(fichero('<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512"><script>alert(1)</script></svg>', 'png'), ['type' => 'image/png', 'name' => 'logo.png']), 'image_type', 'un SVG con script llamado logo.png (los SVG no se aceptan nunca)');
rechazada($lp, subir(fichero("GIF89a" . str_repeat("\0", 200), 'png')), 'image_type', 'GIF disfrazado de PNG');
rechazada($lp, subir(fichero("BM" . str_repeat("\0", 200), 'png')), 'image_type', 'BMP disfrazado de PNG');
rechazada($lp, subir(fichero(substr(png(300, 300), 0, 200), 'png')), 'image_unreadable', 'PNG truncado');
rechazada($lp, subir(fichero(png(300, 300), 'png'), ['error' => UPLOAD_ERR_PARTIAL]), 'image_upload', 'subida incompleta');
$hostile = new ImageProcessor(5 * 1048576, 512, 100, false, 16000000, 4096, null, true);
rechazada($hostile, subir('/etc/passwd', ['name' => 'logo.png']), 'image_upload', 'tmp_name inventado apuntando a /etc/passwd (no lo subio PHP)');

fin();
