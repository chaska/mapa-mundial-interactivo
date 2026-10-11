<?php
declare(strict_types=1);
/** Live tests of the place search endpoints (own GeoNames file). */
require __DIR__ . '/lib/http.php';
$base = getenv('MV_BASE') ?: 'http://127.0.0.1:8081';
$guest = new Cliente($base, 'g'); $ana = new Cliente($base, 'ana');
afirma($ana->login('ana', getenv('MV_ANA') ?: 'cambia-esta-clave-de-prueba'), 'ana inicia sesion');
$s = '/index.php?option=com_mapaviajes&task=places.search&format=json&q=';
$n = '/index.php?option=com_mapaviajes&task=places.near&format=json';
$guest->get('/index.php?option=com_users&view=login');

seccion('Acceso');
afirma($guest->get($s . 'madrid')['code'] === 403, 'invitado: busqueda 403');
afirma($guest->get($n . '&lat=40&lon=-3')['code'] === 403, 'invitado: cercano 403');
afirma($ana->post($s . 'madrid', 'x=1')['code'] === 405, 'POST: 405');

seccion('Resultados');
$r = $ana->get($s . rawurlencode('Ciudad del')); $d = json_decode($r['body'], true);
afirma($r['code'] === 200 && ($d['places'][0]['n'] ?? '') === 'Ciudad del Cabo' && ($d['places'][0]['c'] ?? '') === 'ZA', 'Ciudad del Cabo (ZA)');
afirma(str_contains($r['headers']['cache-control'] ?? '', 'no-store') && ($r['headers']['x-content-type-options'] ?? '') === 'nosniff', 'cabeceras: no-store y nosniff');
$d = json_decode($ana->get($s . rawurlencode('S' . "\u{e3}" . 'o Pa'))['body'], true);
afirma(($d['places'][0]['n'] ?? '') === "S\u{e3}o Paulo", 'con tilde y mayusculas');
afirma(json_decode($ana->get($s . 'zzzzzqq')['body'], true)['places'] === [], 'nada: lista vacia');
foreach (['', 'a', '%00', '%27%3B%20DROP%20TABLE%20x%3B--', '%3Cscript%3E', '..%2F..%2Fetc%2Fpasswd', str_repeat('a', 3000), '%FF%FE'] as $q) {
	$r = $ana->get($s . $q); afirma($r['code'] === 200 && is_array(json_decode($r['body'], true)['places'] ?? null), 'consulta hostil [' . substr($q, 0, 14) . '] -> 200 sin resultados raros o error controlado');
}
$r = $ana->get('/index.php?option=com_mapaviajes&task=places.search&format=json&q[]=madrid'); afirma($r['code'] === 200 && json_decode($r['body'], true)['places'] === [], 'q como lista: vacio');

seccion('Cercano');
$d = json_decode($ana->get($n . '&lat=38.70&lon=-9.10')['body'], true);
afirma(($d['place']['c'] ?? '') === 'PT', 'cerca de Lisboa -> PT');
$d = json_decode($ana->get($n . '&lat=0&lon=-140')['body'], true);
afirma($d['ok'] === true && $d['place'] === null, 'en el oceano: null');
foreach (['&lat=abc&lon=1', '&lat=91&lon=0', '&lat=0&lon=181', '&lat=1e2&lon=0', '&lat=&lon=', '', '&lat[]=1&lon=2', '&lat=1;DROP&lon=2'] as $q) {
	afirma($ana->get($n . $q)['code'] === 400, 'coordenadas invalidas [' . $q . '] -> 400');
}
fin();
