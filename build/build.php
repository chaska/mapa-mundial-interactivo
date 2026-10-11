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

// 1. php -l on every PHP file, strict_types and the _JEXEC guard everywhere they must be.
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

if ($fail) { fwrite(STDERR, "BUILD REFUSED:\n - " . implode("\n - ", $fail) . "\n"); exit(1); }

// 4. Zip with a stable order and no stray files.
$zipPath = $root . "/dist/com_mapaviajes-$ver.zip";
@unlink($zipPath);
$zip = new ZipArchive();
$zip->open($zipPath, ZipArchive::CREATE) || throw new RuntimeException('zip');
foreach ($files as $path) {
	$rel = substr($path, strlen($src) + 1);
	if (preg_match('~(^|/)\.(git|DS_Store)~', $rel)) { continue; }
	$zip->addFile($path, $rel);
}
$zip->close();
file_put_contents($zipPath . '.sha256', hash_file('sha256', $zipPath) . '  ' . basename($zipPath) . "\n");
echo "OK  $zipPath  (" . count($files) . " files)\n" . hash_file('sha256', $zipPath) . "\n";
