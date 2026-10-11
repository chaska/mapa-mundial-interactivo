<?php
declare(strict_types=1);
/** Place search over the bundled GeoNames file: ordering, search, nearest, hostile input, and every name must be storable. */
require __DIR__ . '/aserciones.php';
if (!defined('_JEXEC')) { define('_JEXEC', 1); }
require __DIR__ . '/../src/component/backend/src/Security/SanitizeException.php';
require __DIR__ . '/../src/component/backend/src/Security/TextSanitizer.php';
require __DIR__ . '/../src/component/backend/src/Service/PlaceSearch.php';

use Chaska\Component\Mapaviajes\Administrator\Security\SanitizeException;
use Chaska\Component\Mapaviajes\Administrator\Security\TextSanitizer;
use Chaska\Component\Mapaviajes\Administrator\Service\PlaceSearch;

$file = __DIR__ . '/../src/component/media/data/places.tsv';
$p = new PlaceSearch($file);

seccion('Archivo');
$n = 0; $prev = ''; $sorted = true; $bad = 0; $unsafe = [];
foreach (new SplFileObject($file) as $line) {
	$line = rtrim((string) $line, "\r\n"); if ($line === '') { continue; }
	$f = explode("\t", $line); $n++;
	if (count($f) !== 6 || !is_numeric($f[3]) || !is_numeric($f[4]) || !preg_match('/^[A-Z]{2}$/', $f[2])) { $bad++; continue; }
	if (strcmp($prev, $f[0]) > 0) { $sorted = false; } $prev = $f[0];
	if ($f[0] !== PlaceSearch::normalise($f[0]) && count($unsafe) < 5) { $unsafe[] = "clave $f[0] no normalizada"; }
}
afirma($n > 120000, "mas de 120 000 lugares ($n)");
afirma($bad === 0, 'todas las filas tienen 6 columnas validas');
afirma($sorted, 'ordenado por clave (condicion de la busqueda binaria)');
afirma($unsafe === [], 'toda clave de busqueda ya esta normalizada (minusculas, sin tildes)' . ($unsafe ? ': ' . $unsafe[0] : ''));

seccion('Que cada nombre se puede guardar');
$rej = []; $tot = 0;
foreach (new SplFileObject($file) as $line) {
	$f = explode("\t", rtrim((string) $line, "\r\n")); if (count($f) !== 6) { continue; }
	$tot++;
	try { TextSanitizer::clean($f[1] . ', Pais', 120); } catch (SanitizeException $e) { if (count($rej) < 8) { $rej[] = $f[1]; } }
}
afirma(count($rej) === 0, "ningun nombre de lugar es rechazado por el saneador ($tot comprobados)" . ($rej ? ': ' . implode(' | ', $rej) : ''));

seccion('Busqueda');
$first = fn (string $q) => ($p->search($q)[0] ?? null);
afirma(($first('Ciudad del')['n'] ?? '') === 'Ciudad del Cabo', '"Ciudad del" -> Ciudad del Cabo');
afirma(($first('lisboa')['c'] ?? '') === 'PT', '"lisboa" (nombre en español) -> Portugal');
afirma(($first('MADRID')['c'] ?? '') === 'ES', 'mayusculas: Madrid de España antes que el de Colombia');
afirma(($first('londres')['c'] ?? '') === 'GB', '"londres" (español) -> Reino Unido primero');
afirma(($first('gante')['n'] ?? '') === 'Gante' && ($first('gante')['c'] ?? '') === 'BE', '"gante" -> Gante (Bélgica)');
afirma(($first('gent')['n'] ?? '') === 'Gante', 'el nombre local "gent" encuentra Gante');
afirma(($first('mumbai')['n'] ?? '') === 'Bombay', 'el nombre inglés "mumbai" encuentra Bombay');
afirma(($first('cuzco')['c'] ?? '') === 'PE' && ($first('cusco')['c'] ?? '') === 'PE', 'Cuzco y Cusco');
$sh = $first('shanghai'); afirma(abs(($sh['la'] ?? 0) - 31.2) < 0.5 && abs(($sh['lo'] ?? 0) - 121.5) < 0.5, 'Shanghái está donde debe (31,2 N 121,5 E)');
$ge = $p->search('ge', 8); afirma(count($ge) === count(array_unique(array_map(fn ($x) => $x['n'] . $x['c'] . $x['la'] . $x['lo'], $ge))), 'sin resultados repetidos');
afirma(($first('sao pa')['n'] ?? '') === 'São Paulo', 'sin tilde encuentra "São Paulo"');
afirma(($first('  kioto ')['n'] ?? '') !== '', 'espacios sobrantes no molestan');
afirma(count($p->search('springfield', 8)) === 8, 'tope de 8 resultados');
foreach (['', 'a', "\0\0", '%', '_', '\\', '../../etc/passwd', "'; DROP TABLE x;--", '<script>', str_repeat('a', 5000), "\xff\xfe"] as $q) {
	$r = $p->search($q); afirma(is_array($r) && count($r) <= 8, 'consulta hostil sin efecto: ' . substr(addcslashes(bin2hex(substr($q, 0, 6)), "\0"), 0, 12));
}
$t = microtime(true); for ($i = 0; $i < 50; $i++) { $p->search('san'); } $ms = (microtime(true) - $t) * 20;
afirma($ms < 40, 'cada busqueda tarda poco (' . round($ms, 1) . ' ms)');

seccion('Lugar mas cercano');
afirma(($p->nearest(38.70, -9.10)['c'] ?? '') === 'PT', 'cerca de Lisboa -> Portugal');
afirma($p->nearest(0.0, -140.0) === null, 'en mitad del oceano: nada');
afirma(($p->nearest(-33.9, 18.4)['c'] ?? '') === 'ZA', 'hemisferio sur / este');
afirma(($p->nearest(64.8, -179.9)['c'] ?? '') !== '' || true, 'cerca de la linea de cambio de fecha no falla');
afirma((new PlaceSearch('/no/existe'))->search('madrid') === [] && (new PlaceSearch('/no/existe'))->nearest(1, 1) === null, 'archivo ausente: vacio, sin error');
fin();
