<?php
declare(strict_types=1);
// Mini harness: no dependencies, exit code 1 if anything FAILS.
if (!defined('_JEXEC')) { define('_JEXEC', 1); }
$GLOBALS['ok'] = 0; $GLOBALS['fail'] = 0;
function seccion(string $t): void { echo "\n== $t ==\n"; }
function afirma(bool $cond, string $msg): void {
	if ($cond) { $GLOBALS['ok']++; echo "  ok    $msg\n"; } else { $GLOBALS['fail']++; echo "  FALLA $msg\n"; }
}
function fin(): void { echo "\n{$GLOBALS['ok']} correctas, {$GLOBALS['fail']} fallos\n"; exit($GLOBALS['fail'] ? 1 : 0); }
