<?php
declare(strict_types=1);
// Tiny HTTP client for the live tests: cookie jar per user, redirects never followed, full control over multipart.
require_once __DIR__ . '/../../aserciones.php';

final class Cliente
{
	public string $jar;
	public function __construct(public string $base, string $name) { $this->jar = sys_get_temp_dir() . '/mv-jar-' . $name . '-' . bin2hex(random_bytes(3)); @unlink($this->jar); }

	/** @return array{code:int, headers:array<string,string>, body:string, location:string} */
	public function pide(string $method, string $path, $body = null, array $headers = []): array
	{
		$ch = curl_init($this->base . $path);
		$hdrs = [];
		curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 120]);
		if ($body !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, $body); }
		$raw = (string) curl_exec($ch);
		$code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		$hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		curl_close($ch);
		foreach (explode("\r\n", substr($raw, 0, $hs)) as $l) { if (str_contains($l, ':')) { [$k, $v] = explode(':', $l, 2); $hdrs[strtolower(trim($k))] = trim($v); } }
		return ['code' => $code, 'headers' => $hdrs, 'body' => substr($raw, $hs), 'location' => $hdrs['location'] ?? ''];
	}
	public function get(string $path, array $headers = []): array { return $this->pide('GET', $path, null, $headers); }
	public function post(string $path, $body, array $headers = []): array { return $this->pide('POST', $path, $body, $headers); }

	public function login(string $user, string $pass, bool $admin = false): bool
	{
		$page = $this->get($admin ? '/administrator/index.php' : '/index.php?option=com_users&view=login');
		if (!preg_match('/name="([a-f0-9]{32})" value="1"/', $page['body'], $m)) { return false; }
		$fields = ['username' => $user, (($admin) ? 'passwd' : 'password') => $pass, $m[1] => '1'];
		if ($admin) { $fields += ['option' => 'com_login', 'task' => 'login', 'return' => 'aW5kZXgucGhw']; }
		else { $fields += ['option' => 'com_users', 'task' => 'user.login', 'return' => base64_encode('index.php?option=com_mapaviajes')]; }
		$r = $this->post($admin ? '/administrator/index.php' : '/index.php', $fields);
		return in_array($r['code'], [302, 303], true) && !str_contains($r['location'], 'view=login');
	}

	/** Reads the session token and component options Joomla embeds in the map page. */
	public function opciones(): ?array
	{
		$p = $this->get('/index.php?option=com_mapaviajes');
		if ($p['code'] !== 200 || !preg_match('~<script type="application/json" class="joomla-script-options new">(.*?)</script>~s', $p['body'], $m)) { return null; }
		$j = json_decode($m[1], true);
		return $j['com_mapaviajes'] ?? null;
	}
}

/** Builds a multipart body by hand so a test can forge every header of every part. */
function multipart(array $fields, array $files, ?string &$contentType = null): string
{
	$b = '----mvtest' . bin2hex(random_bytes(6)); $out = '';
	foreach ($fields as $name => $val) { foreach ((array) $val as $k => $v) { $n = is_array($val) ? $name . '[' . (is_int($k) ? '' : $k) . ']' : $name; $out .= "--$b\r\nContent-Disposition: form-data; name=\"$n\"\r\n\r\n$v\r\n"; } }
	foreach ($files as $f) { // ['field'=>'photos[]','filename'=>'a.jpg','type'=>'image/jpeg','data'=>bytes]
		$out .= "--$b\r\nContent-Disposition: form-data; name=\"{$f['field']}\"; filename=\"{$f['filename']}\"\r\nContent-Type: {$f['type']}\r\n\r\n{$f['data']}\r\n";
	}
	$contentType = 'multipart/form-data; boundary=' . $b;
	return $out . "--$b--\r\n";
}

function jpegBytes(int $w, int $h, int $q = 85): string {
	$im = imagecreatetruecolor($w, $h);
	for ($i = 0; $i < 40; $i++) { imagefilledellipse($im, random_int(0, $w), random_int(0, $h), random_int(10, max(11, $w)), random_int(10, max(11, $h)), imagecolorallocate($im, random_int(0, 255), random_int(0, 255), random_int(0, 255))); }
	ob_start(); imagejpeg($im, null, $q); return (string) ob_get_clean();
}
function pngBytes(int $w, int $h): string { $im = imagecreatetruecolor($w, $h); imagefill($im, 0, 0, 0xCC2222); ob_start(); imagepng($im); return (string) ob_get_clean(); }
function withExif(string $jpeg, string $payload): string { return substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload . substr($jpeg, 2); }

final class Bd
{
	public PDO $pdo;
	public function __construct(public string $prefix) { $this->pdo = new PDO(getenv('MV_DSN') ?: 'pgsql:host=127.0.0.1;port=5433;dbname=joomla', getenv('MV_DBUSER') ?: 'joomla', getenv('MV_DBPASS') ?: 'x', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); }
	public function t(string $n): string { return $this->prefix . $n; }
	public function uno(string $sql, array $p = []) { $s = $this->pdo->prepare($sql); $s->execute($p); return $s->fetchColumn(); }
	public function filas(string $sql, array $p = []): array { $s = $this->pdo->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC); }
	public function setParams(array $kv): void {
		$row = (string) $this->uno('SELECT params FROM ' . $this->t('extensions') . " WHERE element = 'com_mapaviajes' AND type = 'component'");
		$p = array_merge(json_decode($row ?: '{}', true) ?: [], $kv);
		$this->exec('UPDATE ' . $this->t('extensions') . " SET params = :p WHERE element = 'com_mapaviajes' AND type = 'component'", [':p' => json_encode($p)]);
	}
	public function exec(string $sql, array $p = []): void { $s = $this->pdo->prepare($sql); $s->execute($p); }
}
