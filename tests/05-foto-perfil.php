<?php
declare(strict_types=1);
// ProfilePhoto: which addresses are accepted and how the answer of MiFoto is filtered. No Joomla needed (a stub User stands in).
namespace Joomla\CMS\User { class User { public int $id = 0; public bool $guest = true; } }
namespace Joomla\CMS\Log { class Log { public const WARNING = 4; public static array $lines = []; public static function add(string $m, int $p, string $c): void { self::$lines[] = $m; } } }
namespace {
define('_JEXEC', 1);
require __DIR__ . '/aserciones.php';
require __DIR__ . '/../src/component/backend/src/Service/ProfilePhoto.php';
use Chaska\Component\Mapaviajes\Administrator\Service\ProfilePhoto;
use Joomla\CMS\User\User;

seccion('Direcciones: solo rutas del propio sitio');
$ok = '/index.php?option=com_mifoto&task=photo.show&u=0123456789abcdef&s=64&v=89abcdef';
afirma(ProfilePhoto::safe($ok) === $ok, 'la ruta de MiFoto (sin extension y con parametros) se acepta tal cual');
afirma(ProfilePhoto::safe('/sub/index.php?option=com_mifoto&u=ab') !== null, 'con subcarpeta se acepta');
foreach (['', 'index.php?x=1', '//evil.example/a.png', 'https://evil.example/a.png', 'javascript:alert(1)', 'data:image/png;base64,AAAA', '/a b', "/a\nb", '/a"onerror="x', "/a'b", '/a<b', '/a>b', '/a`b', '/a\\b', '/' . str_repeat('a', 300)] as $bad) {
	afirma(ProfilePhoto::safe($bad) === null, 'se rechaza [' . substr(addcslashes($bad, "\0..\37"), 0, 30) . ']');
}

seccion('Lo que devuelve MiFoto: texto, lista u objeto');
afirma(ProfilePhoto::addressOf($ok) === $ok, 'texto');
afirma(ProfilePhoto::addressOf(['url' => $ok]) === $ok, 'lista con url');
afirma(ProfilePhoto::addressOf((object) ['url' => $ok]) === $ok, 'objeto con propiedad url');
$o = new class($ok) { public function __construct(private string $u) {} public function getUrl(): string { return $this->u; } };
afirma(ProfilePhoto::addressOf($o) === $ok, 'objeto con getUrl()');
afirma(ProfilePhoto::addressOf(null) === null && ProfilePhoto::addressOf(['url' => 5]) === null && ProfilePhoto::addressOf(new stdClass()) === null, 'null, tipos raros y objetos sin direccion: nada');
afirma(ProfilePhoto::addressOf(['url' => 'https://evil.example/x']) === null, 'una direccion externa que viniera de MiFoto tampoco se usa');

seccion('urls(): el servicio completo con una fuente de prueba');
$viewer = new User(); $viewer->id = 7; $viewer->guest = false;
$guest = new User();
$fuente = static fn (array $ids, int $size, User $v): array => [42 => ['url' => '/f/42?s=' . $size], 43 => 'https://evil.example/x', 99 => ['url' => '/f/99']];
$svc = new ProfilePhoto($fuente, true);
$r = $svc->urls([42, 43, 42, 0, -1], $viewer, 128);
afirma($r === [42 => '/f/42?s=128'], 'solo la persona pedida con direccion segura (42); la externa (43) y la no pedida (99) fuera: ' . json_encode($r));
afirma($svc->urls([], $viewer) === [], 'sin personas: nada, sin llamar a MiFoto');
afirma($svc->urls([42], $guest) === [], 'quien mira es invitado: nada');
afirma((new ProfilePhoto($fuente, false))->urls([42], $viewer) === [], 'con el interruptor del administrador apagado: nada');
afirma((new ProfilePhoto(static function (): array { throw new RuntimeException('MiFoto roto'); }, true))->urls([42], $viewer) === [], 'si MiFoto falla, nada (el mapa sigue con las iniciales)');
afirma(count(\Joomla\CMS\Log\Log::$lines) === 1 && str_contains(\Joomla\CMS\Log\Log::$lines[0], 'RuntimeException: MiFoto roto'), 'y el fallo queda anotado una sola vez en el registro: ' . json_encode(\Joomla\CMS\Log\Log::$lines));
afirma((new ProfilePhoto(static fn (): array => [], true))->urls([42], $viewer) === [], 'MiFoto no conoce a nadie: nada');
fin();
}
