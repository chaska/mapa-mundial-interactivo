<?php
declare(strict_types=1);
require __DIR__ . '/aserciones.php';
$base = __DIR__ . '/../src/component/backend/src/Security/';
require $base . 'SanitizeException.php';
require $base . 'TextSanitizer.php';
use Chaska\Component\Mapaviajes\Administrator\Security\{TextSanitizer, SanitizeException};

function rechaza(string $in, string $razon, int $max = 300, int $words = 0, bool $ml = false): void {
	try { $out = TextSanitizer::clean($in, $max, $words, $ml); afirma(false, "debia rechazar [" . addcslashes($in, "\0..\37") . "] y devolvio [$out]"); }
	catch (SanitizeException $e) { afirma($e->getReason() === $razon, "rechaza [" . substr(addcslashes($in, "\0..\37"), 0, 50) . "] ($razon)" . ($e->getReason() === $razon ? '' : ' pero dio ' . $e->getReason())); }
}
function acepta(string $in, string $esperado, int $max = 300, int $words = 0, bool $ml = false): void {
	try { $out = TextSanitizer::clean($in, $max, $words, $ml); afirma($out === $esperado, "acepta [" . substr($in, 0, 40) . "] => [$out]"); }
	catch (SanitizeException $e) { afirma(false, "debia aceptar [" . substr($in, 0, 40) . "] y rechazo: " . $e->getReason()); }
}

seccion('Texto normal: debe pasar tal cual');
acepta('Fotos en Ciudad del Cabo', 'Fotos en Ciudad del Cabo');
acepta("  Un día   maravilloso\ten Kioto  ", 'Un día maravilloso en Kioto');
acepta('Tom & Jerry, "comillas" y ñandú — ¡qué día! 😀', 'Tom & Jerry, "comillas" y ñandú — ¡qué día! 😀');
acepta('Me encantó. Es una ciudad preciosa.', 'Me encantó. Es una ciudad preciosa.');
acepta('Salimos a las 10.30 y volvimos a las 18.45', 'Salimos a las 10.30 y volvimos a las 18.45');
acepta('Costó 3,5 euros. No me lo creía. Id con cuidado.', 'Costó 3,5 euros. No me lo creía. Id con cuidado.');
acepta('I love the solar system. Great trip!', 'I love the solar system. Great trip!');
acepta('Te quiero <3', 'Te quiero 3');
acepta("Línea uno\n\n\n\nLínea dos", "Línea uno\n\nLínea dos", 300, 0, true);
acepta("Una sola\nlínea", 'Una sola línea');

seccion('Caracteres invisibles y de control: se eliminan');
acepta("Hola\x00mundo", 'Holamundo');
acepta("a\u{200B}b\u{202E}c\u{FEFF}d", 'abcd');
acepta("Hola\u{0007}", 'Hola');

seccion('Codigo y marcado: se rechaza');
foreach ([
	'<script>alert(1)</script>', '<img src=x onerror=alert(1)>', '<b>negrita</b>', '<?php echo 1; ?>', '<!-- comentario -->',
	'&lt;script&gt;alert(1)&lt;/script&gt;', '&#60;script&#62;', '%3Cscript%3Ealert(1)%3C/script%3E', '%253Cscript%253E',
	"\u{FF1C}script\u{FF1E}alert(1)", 'javascript:alert(1)', 'JaVaScRiPt :alert(1)', 'data:text/html;base64,PHNjcmlwdD4=',
	'{{7*7}}', '${jndi:ldap://x}', '{% include "x" %}', '`rm -rf /`', 'onerror=alert(1)', 'onclick = x', 'eval(atob("YQ=="))',
	'document.cookie', '../../etc/passwd', '..\\..\\windows', 'a\\u003cscript\\u003e', 'x\\x3cscript',
] as $c) { rechaza($c, 'text_code'); }

seccion('Enlaces y direcciones: se rechaza');
foreach ([
	'https://malo.com', 'http://x', 'HTTP://EJEMPLO.ES', 'ftp://x.y', 'www.malo.com', 'WWW . malo . com', 'visita malo.com ya',
	'malo.COM', 'malo.net/ruta', 'malo[.]com', 'malo(.)com', 'malo {.} com', 'malo dot com', 'malo punto com', 'malo . com', 'malo. com',
	'hxxp://malo', 'mailto:a@b.com', 'a@b.com', 'a [at] b.com', 'a(at)b', "malo\u{FF0E}com", "\u{FF48}\u{FF54}\u{FF54}\u{FF50}\u{FF1A}//x",
	'bit.ly/abc', 't.me/canal', 'wa.me/34600000000', '192.168.1.1', 'tel:+34600000000', 'whatsapp://send', 'casa.es', 'mi-tienda.xyz', '//malo.com',
	'ejemplo.shop', 'ejemplo.online', 'ejemplo.ru',
] as $c) { rechaza($c, 'text_link'); }

seccion('Limites');
rechaza(str_repeat('a', 81), 'text_too_long', 80);
acepta(str_repeat('a', 80), str_repeat('a', 80), 80);
rechaza(str_repeat('palabra ', 257), 'text_too_many_words', 5000, 256, true);
acepta(trim(str_repeat('palabra ', 256)), trim(str_repeat('palabra ', 256)), 5000, 256, true);
rechaza(str_repeat('x', 100000), 'text_too_long', 80);
rechaza("\xC3\x28 invalido", 'text_encoding');
rechaza("\xF0\x28\x8C\x28", 'text_encoding');

seccion('Rendimiento: entrada hostil grande no cuelga el servidor');
$t = microtime(true);
try { TextSanitizer::clean(str_repeat('a.', 1200) . str_repeat('%25', 800), 2000, 256, true); } catch (SanitizeException $e) {}
try { TextSanitizer::clean(str_repeat('(((', 600), 2000, 256, true); } catch (SanitizeException $e) {}
afirma((microtime(true) - $t) < 1.0, 'entradas patologicas en menos de 1 s (' . round((microtime(true) - $t) * 1000) . ' ms)');

fin();
