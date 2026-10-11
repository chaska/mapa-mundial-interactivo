<?php
declare(strict_types=1);
// Uploads a test logo through the administrator form: php logo.php <width> <height>   (or "quitar" to remove it). Prints the stored size.
require __DIR__ . '/http.php';
$base = getenv('MV_BASE') ?: 'http://127.0.0.1:8081';
$a = new Cliente($base, 'logo'); $a->login('admin', getenv('MV_ADMIN') ?: 'cambia-esta-clave-de-prueba', true);
$a->get('/administrator/index.php?option=com_mapaviajes&task=album.edit&id=1');
$p = $a->get('/administrator/index.php?option=com_mapaviajes&view=album&layout=edit&id=1'); preg_match('/name="([a-f0-9]{32})" value="1"/', $p['body'], $m);
$f = ['jform[id]' => '1', 'jform[title]' => 'Nuestro mundo en fotos', 'jform[description]' => '', 'jform[published]' => '1', 'jform[access]' => '2', 'jform[allow_comments]' => '1', 'jform[ordering]' => '0', 'task' => 'album.apply', ($m[1] ?? 'x') => '1'];
$files = [];
if (($argv[1] ?? '') === 'quitar') { $f['logo_remove'] = '1'; }
else {
	$w = (int) $argv[1]; $h = (int) $argv[2];
	$im = imagecreatetruecolor($w, $h); imagealphablending($im, false); imagesavealpha($im, true); imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127)); imagealphablending($im, true);
	imagefilledellipse($im, intdiv($w, 2), intdiv($h, 2), min($w, $h), min($w, $h), imagecolorallocate($im, 31, 111, 120));
	imagefilledrectangle($im, intdiv($w, 2) - intdiv($w, 8), intdiv($h, 2) - intdiv($h, 8), intdiv($w, 2) + intdiv($w, 8), intdiv($h, 2) + intdiv($h, 8), imagecolorallocate($im, 240, 180, 41));
	ob_start(); imagepng($im); $files = [['field' => 'logo_upload', 'filename' => 'logo.png', 'type' => 'image/png', 'data' => (string) ob_get_clean()]];
}
$ct = ''; $r = $a->post('/administrator/index.php?option=com_mapaviajes&layout=edit&id=1', multipart($f, $files, $ct), ["Content-Type: $ct"]);
$db = new Bd(getenv('MV_PREFIX') ?: 'jt_'); $n = (string) $db->uno('SELECT logo FROM ' . $db->t('mapaviajes_albums') . ' WHERE id = 1');
$i = $n !== '' ? getimagesize(rtrim((string) getenv('MV_STORAGE'), '/') . '/photos/' . $n) : false;
echo $r['code'], ' ', $i ? $i[0] . 'x' . $i[1] : 'sin logo', "\n";
