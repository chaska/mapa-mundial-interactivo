<?php
declare(strict_types=1);
/**
 * Builds dist/com_mapaviajes-<version>.zip. It is a lock: if anything below fails there is NO zip.
 * Usage: php build/build.php
 */
$root = dirname(__DIR__);
$src  = $root . '/src/component';
$xml  = simplexml_load_file($src . '/mapaviajes.xml') ?: throw new RuntimeException('manifest');
$ver  = (string) $xml->version;
$fail = [];

/** Lints and inspects every file under $src. Returns the sorted file list. */
function inspect(string $src, array &$fail): array
{
	$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS));
	$files = [];
	foreach ($it as $f) {
		if ($f->isFile()) { $files[] = $f->getPathname(); }
	}
	sort($files);
	foreach ($files as $path) {
		$rel = substr($path, strlen($src) + 1);
		if (str_ends_with($path, '.php')) {
			exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $out, $rc);
			if ($rc !== 0) { $fail[] = "php -l failed: $rel"; }
			$code = (string) file_get_contents($path);
			if (!str_contains($code, 'declare(strict_types=1);')) { $fail[] = "missing strict_types: $rel"; }
			if (!str_contains($code, "defined('_JEXEC')") && !str_contains($code, 'defined("_JEXEC")')) { $fail[] = "missing _JEXEC guard: $rel"; }
			foreach (['eval(', 'unserialize(', 'shell_exec(', 'passthru(', 'proc_open(', 'popen(', 'extract($_', '$_REQUEST', 'mysql_query', 'create_function('] as $bad) {
				if (str_contains($code, $bad) && !in_array($rel, ['backend/src/Security/ImageProcessor.php', 'backend/src/Security/TextSanitizer.php'], true)) { $fail[] = "forbidden construct $bad in $rel"; }
			}
			if (!in_array($rel, ['backend/src/Security/ImageProcessor.php', 'backend/src/Security/TextSanitizer.php'], true) && preg_match('/\b(?:system|exec)\s*\(/', $code) === 1) { $fail[] = "forbidden call in $rel"; }
		}
		if (str_ends_with($path, '.xml') && @simplexml_load_file($path) === false) { $fail[] = "invalid XML: $rel"; }
		if (str_ends_with($path, '.json') && json_decode((string) file_get_contents($path)) === null) { $fail[] = "invalid JSON: $rel"; }
	}

	// 2. Browser code must not use innerHTML / eval / document.write.
	foreach (glob($src . '/media/js/*.js') ?: [] as $js) {
		$code = (string) file_get_contents($js);
		foreach (['innerHTML', 'outerHTML', 'insertAdjacentHTML', 'document.write', 'eval(', 'new Function(', 'setTimeout("', "setTimeout('"] as $bad) {
			if (str_contains($code, $bad)) { $fail[] = "forbidden in " . basename($js) . ": $bad"; }
		}
	}

	// 3. No third-party requests in anything we ship.
	foreach ($files as $path) {
		if (preg_match('/\.(css|js|php)$/', $path) !== 1) { continue; }
		$code = (string) file_get_contents($path);
		if (preg_match_all('~(?:https?:)?//(?!www\.w3\.org|localhost|127\.0\.0\.1)[a-z0-9.-]+\.[a-z]{2,}~i', $code, $m)) {
			foreach (array_unique($m[0]) as $url) {
				if (!str_contains($url, 'example') && !str_contains($url, 'openfontlicense')) { $fail[] = 'external URL in ' . substr($path, strlen($src) + 1) . ': ' . $url; }
			}
		}
	}

	return $files;
}

$files  = inspect($src, $fail);
$plugins = ['content', 'user', 'privacy'];
$pFiles = [];
foreach ($plugins as $g) { $pFiles[$g] = inspect($root . '/src/plugins/' . $g . '/mapaviajes', $fail); }
$kFiles = inspect($root . '/src/package', $fail);

if ($fail) { fwrite(STDERR, "BUILD REFUSED:\n - " . implode("\n - ", $fail) . "\n"); exit(1); }

// 4. Three zips with a stable order and no stray files: the component, the plugin and the package that holds both.
function makeZip(string $zipPath, string $base, array $list, array $extra = []): void
{
	@unlink($zipPath);
	$zip = new ZipArchive();
	$zip->open($zipPath, ZipArchive::CREATE) || throw new RuntimeException('zip');
	foreach ($list as $path) {
		$rel = substr($path, strlen($base) + 1);
		if (preg_match('~(^|/)\.(git|DS_Store)~', $rel)) { continue; }
		$zip->addFile($path, $rel);
	}
	foreach ($extra as $name => $path) { $zip->addFile($path, $name); }
	$zip->close();
}
@mkdir($root . '/dist', 0775, true);
$com = $root . '/dist/com_mapaviajes.zip';
$pkg = $root . "/dist/pkg_mapaviajes-$ver.zip";
makeZip($com, $src, $files);
$inside = ['com_mapaviajes.zip' => $com];
foreach ($plugins as $g) {
	$zipPlg = $root . "/dist/plg_{$g}_mapaviajes.zip";
	makeZip($zipPlg, $root . '/src/plugins/' . $g . '/mapaviajes', $pFiles[$g]);
	$inside["plg_{$g}_mapaviajes.zip"] = $zipPlg;
}
makeZip($pkg, $root . '/src/package', $kFiles, $inside);
file_put_contents($pkg . '.sha256', hash_file('sha256', $pkg) . '  ' . basename($pkg) . "\n");
echo "OK  $pkg  (component " . count($files) . " files, plugins " . array_sum(array_map('count', $pFiles)) . ", package " . count($kFiles) . ")\n" . hash_file('sha256', $pkg) . "\n";
