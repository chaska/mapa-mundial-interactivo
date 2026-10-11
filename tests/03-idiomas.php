<?php
declare(strict_types=1);
require __DIR__ . '/aserciones.php';
$src = __DIR__ . '/../src/component';

function claves(string $file): array {
	$out = [];
	foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $l) { if (preg_match('/^([A-Z0-9_]+)="/', $l, $m)) { $out[$m[1]] = true; } }
	return $out;
}
seccion('Los archivos de idioma ES y EN tienen exactamente las mismas claves');
foreach (['frontend', 'backend'] as $side) {
	foreach (['com_mapaviajes.ini', 'com_mapaviajes.sys.ini'] as $f) {
		$es = @claves("$src/$side/language/es-ES/$f"); $en = @claves("$src/$side/language/en-GB/$f");
		if (!$es && !$en) { continue; }
		afirma(array_keys($es) === array_keys($en), "$side/$f: " . count($es) . ' claves iguales en ES y EN' . (array_diff_key($es, $en) || array_diff_key($en, $es) ? ' DIFERENCIAS ' . implode(',', array_keys(array_diff_key($es, $en) + array_diff_key($en, $es))) : ''));
	}
}
seccion('Toda clave usada en el codigo existe en el idioma que la carga');
$site = claves("$src/frontend/language/es-ES/com_mapaviajes.ini");
$admin = claves("$src/backend/language/es-ES/com_mapaviajes.ini") + claves("$src/backend/language/es-ES/com_mapaviajes.sys.ini");
$core = array_flip(['JYES', 'JNO', 'JCANCEL', 'JSTATUS', 'JPUBLISHED', 'JUNPUBLISHED', 'JTRASHED', 'JALL', 'JTOOLBAR_CANCEL', 'JTOOLBAR_CLOSE', 'JTOOLBAR_CHANGE_STATUS', 'JTOOLBAR_DELETE_FROM_TRASH']);
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS));
$used = ['site' => [], 'admin' => []];
foreach ($it as $f) {
	$p = $f->getPathname();
	if (!preg_match('/\.(php|js|xml)$/', $p) || str_contains($p, '/language/')) { continue; }
	$side = str_contains($p, '/frontend/') || str_contains($p, 'media/js') ? 'site' : 'admin';
	preg_match_all('/COM_MAPAVIAJES[A-Z0-9_]*/', (string) file_get_contents($p), $m);
	foreach ($m[0] as $k) { $used[$side][$k] = $p; }
}
$dynamic = ['COM_MAPAVIAJES_ERR_', 'COM_MAPAVIAJES_TRIPS', 'COM_MAPAVIAJES_ALBUMS', 'COM_MAPAVIAJES_ALBUM', 'COM_MAPAVIAJES_JS_', 'COM_MAPAVIAJES_JS_LIKE_'];  // prefixes built at runtime
foreach (['site' => $site, 'admin' => $admin + $site] as $side => $have) {
	foreach ($used[$side] as $k => $p) {
		if (in_array($k, $dynamic, true) || isset($core[$k])) { continue; }
		if (!isset($have[$k])) { afirma(false, "clave sin traducir ($side): $k  <- " . basename($p)); }
	}
}
afirma(true, 'revisadas ' . (count($used['site']) + count($used['admin'])) . ' referencias');
// errors raised by the sanitizer map to ERR keys
foreach (['text_too_long', 'text_too_many_words', 'text_encoding', 'text_code', 'text_link', 'image_too_big', 'image_upload', 'image_unreadable', 'image_type', 'image_polyglot', 'image_dimensions', 'image_server'] as $r) {
	afirma(isset($site['COM_MAPAVIAJES_ERR_' . strtoupper($r)]), "motivo '$r' tiene mensaje");
}
foreach (['login', 'title_required', 'photos_required', 'photos_too_many', 'album', 'place', 'rate', 'generic', 'server'] as $r) {
	afirma(isset($site['COM_MAPAVIAJES_ERR_' . strtoupper($r)]), "motivo '$r' tiene mensaje");
}
fin();
