<?php
declare(strict_types=1);
// The map page and the comments page must not be cacheable: php lib/cabeceras.php
require __DIR__ . '/http.php';
$ana = new Cliente(getenv('MV_BASE') ?: 'http://127.0.0.1:8081', 'cab'); $ana->login('ana', getenv('MV_ANA') ?: 'cambia-esta-clave-de-prueba');
$bad = 0;
foreach (['/index.php?option=com_mapaviajes', '/index.php?option=com_mapaviajes&view=tripcomments&tmpl=component&id=1'] as $u) {
	$r = $ana->get($u); $cc = strtolower($r['headers']['cache-control'] ?? ''); $ls = strtolower($r['headers']['x-litespeed-cache-control'] ?? '');
	$ok = str_contains($cc, 'no-store') && $ls === 'no-cache'; if (!$ok) { $bad++; }
	echo ($ok ? '  ok    ' : '  FALLA ') . "$u: cache-control=[$cc] litespeed=[$ls]\n";
}
exit($bad ? 1 : 0);
