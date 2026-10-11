<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\Security;

defined('_JEXEC') || die;

/**
 * Turns whatever a user typed into plain, harmless text, or refuses it.
 *
 * Policy: only plain text is stored. No markup, no code, no template syntax, no links, no e-mail addresses.
 * Anything that looks like those is REFUSED (never silently "fixed"), so the author learns what to change
 * and nothing half-cleaned is ever saved. Output must still be escaped by the caller: this class is a
 * second wall, not the only one.
 */
final class TextSanitizer
{
	/** Top-level domains that are never ordinary words: a dot followed by one of these is a link. */
	private const TLD_STRONG = 'com|net|org|info|biz|edu|gov|mil|int|eu|uk|fr|pt|nl|ch|ru|cn|jp|br|ar|mx|cl|pe|ca|au|nz|ie|se|fi|dk|cz|sk|ro|bg|gr|tr|il|ae|sa|za|ng|ke|dz|tn|eg|id|sg|th|vn|ph|kr|tw|hk|io|co|tv|ly|gl|cc|xyz|top|club|online|site|shop|store|app|dev|link|click|live|tech|cloud|page|blog|icu|vip|fun|buzz|website|space|pro|name|mobi|ws|tk|ml|ga|cf|gq|su|ua|pl|lt|lv|ee|asia|today|world|life|news|agency|digital|email|network|group|media|wiki|zip|mov|bet|casino|porn|sex|xxx|adult|ru|onion';

	/** Short TLDs that are also ordinary words (es, no, me, it, in, is, at, to, de, us, be...). Only matched in lowercase, glued to a label. */
	private const TLD_SHORT = 'es|no|me|it|in|is|at|to|de|us|be|do|so|my|ma|la|be|by|im|as|am|ad|ag|ai|al|ao|be|bo|cr|cu|do|ec|gt|hn|ni|pa|pr|py|sv|uy|ve';

	/**
	 * @param   string  $raw        What came from the request.
	 * @param   int     $maxChars   Hard limit in characters (the real column width).
	 * @param   int     $maxWords   Hard limit in words (0 = no limit).
	 * @param   bool    $multiline  Keep line breaks (captions) or flatten to a single line (titles, places).
	 *
	 * @return  string  Clean plain text, possibly empty.
	 * @throws  SanitizeException
	 */
	public static function clean(string $raw, int $maxChars, int $maxWords = 0, bool $multiline = false): string
	{
		// 1. A cheap size guard first, so the regular expressions below never see a huge string.
		if (\strlen($raw) > $maxChars * 8 + 64)
		{
			throw new SanitizeException('text_too_long');
		}

		if (!mb_check_encoding($raw, 'UTF-8'))
		{
			throw new SanitizeException('text_encoding');
		}

		// 2. Canonical form (also folds full-width and compatibility characters for the checks).
		$text = class_exists(\Normalizer::class) ? (\Normalizer::normalize($raw, \Normalizer::FORM_C) ?: $raw) : $raw;

		// 3. Line endings, then drop every control, format (zero-width, bidi), private-use and unassigned character.
		$text = str_replace("\t", ' ', $text);
		$text = preg_replace('/\R/u', "\n", $text) ?? '';
		$text = preg_replace('/[^\P{C}\n]/u', '', $text) ?? '';

		if ($text === '')
		{
			return '';
		}

		// 4. Detection runs on a hardened copy: NFKC (＜ becomes <, ＠ becomes @, ． becomes .), entities and
		//    URL-escapes decoded a few times, so "&lt;script&gt;" and "%3Cscript%3E" cannot hide.
		$probe = self::probeForm($text);

		if (self::looksLikeCode($probe))
		{
			throw new SanitizeException('text_code');
		}

		if (self::looksLikeLink($probe))
		{
			throw new SanitizeException('text_link');
		}

		// 5. Stored form: no angle brackets at all, single spaces, trimmed.
		$text = str_replace(['<', '>', "\u{FF1C}", "\u{FF1E}"], '', $text);

		if ($multiline)
		{
			$text = preg_replace('/[^\S\n]+/u', ' ', $text) ?? '';
			$text = preg_replace('/ *\n */u', "\n", $text) ?? '';
			$text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? '';
		}
		else
		{
			$text = preg_replace('/\s+/u', ' ', $text) ?? '';
		}

		$text = trim($text);

		if (mb_strlen($text, 'UTF-8') > $maxChars)
		{
			throw new SanitizeException('text_too_long');
		}

		if ($maxWords > 0 && self::countWords($text) > $maxWords)
		{
			throw new SanitizeException('text_too_many_words');
		}

		return $text;
	}

	public static function countWords(string $text): int
	{
		return \count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: []);
	}

	private static function probeForm(string $text): string
	{
		$probe = $text;

		for ($i = 0; $i < 3; $i++)
		{
			$before = $probe;
			$probe  = html_entity_decode($probe, ENT_QUOTES | ENT_HTML5, 'UTF-8');
			$probe  = rawurldecode($probe);
			$probe  = preg_replace('/\\\\u00([0-9a-f]{2})/i', '%$1', $probe) ?? $probe;
			$probe  = preg_replace_callback('/\\\\x([0-9a-f]{2})/i', static fn (array $m): string => \chr((int) hexdec($m[1])), $probe) ?? $probe;

			if (class_exists(\Normalizer::class))
			{
				$probe = \Normalizer::normalize($probe, \Normalizer::FORM_KC) ?: $probe;
			}

			if ($probe === $before)
			{
				break;
			}
		}

		// Remove anything invisible that decoding may have revealed.
		return preg_replace('/[^\P{C}\n]/u', '', $probe) ?? $probe;
	}

	private static function looksLikeCode(string $probe): bool
	{
		$patterns = [
			'/<\s*[!\/?a-z]/i',                       // any tag, comment, doctype, processing instruction
			'/<\?|\?>|<%|%>/',                        // PHP / ASP delimiters
			'/\{\{|\}\}|\$\{|\{%|%\}|<\$|\[\[|\]\]/', // template and expression syntax
			'/`/',                                    // backticks
			'/\b(?:javascript|vbscript|livescript|mocha|data|blob|file|jar)\s*:/i',
			'/\bon[a-z]{3,}\s*=/i',                   // event handlers
			'/\b(?:eval|exec|system|passthru|shell_exec|base64_decode)\s*\(/i',
			'/\b(?:document\.(?:write|cookie)|window\.location|innerhtml)\b/i',
			'/\\\\0|%00|\\\\u0000/i',
			'/\.\.[\/\\\\]/',                         // path traversal
		];

		foreach ($patterns as $pattern)
		{
			if (preg_match($pattern, $probe) === 1)
			{
				return true;
			}
		}

		return false;
	}

	private static function looksLikeLink(string $probe): bool
	{
		$dot = '(?:\.|\s*(?:\[\.\]|\(\.\)|\{\.\})\s*|\s+(?:dot|punto)\s+|\x{2024})';

		$patterns = [
			// any scheme that does something
			'/\b(?:[a-z][a-z0-9+.\-]{1,15})\s*:\s*\/\//iu',
			'/\b(?:https?|hxxps?|ftps?|sftp|wss?|mailto|tel|sms|skype|whatsapp|tg|telegram|intent|market|view-source|magnet)\s*:/iu',
			'/(?<![a-z0-9])(?:www|ftp|web)\s*' . $dot . '/iu',
			// label.tld (strong TLDs, any case)
			'/[a-z0-9\p{L}][a-z0-9\p{L}\-]*' . $dot . '(?:' . self::TLD_STRONG . ')(?![a-z0-9\p{L}])/iu',
			// "example . com" with spaces around the dot, for the TLDs nobody writes by accident
			'/[a-z0-9]\s+\.\s*(?:com|net|org|info|biz|xyz|top|club|online|site|shop|store|app|link|click)(?![a-z0-9])/iu',
			'/[a-z0-9]\s*\.\s+(?:com|net|org|info|biz|xyz|top|club|online|site|shop|store|app|link|click)(?![a-z0-9])/iu',
			// label.tld (short, ambiguous TLDs): only lowercase and glued, e.g. "casa.es"
			'/(?<![A-Za-z0-9\p{L}])[a-z0-9\-]{3,}\.(?:' . self::TLD_SHORT . ')(?![A-Za-z0-9\p{L}])/u',
			// e-mail addresses, including [at] / (at) tricks
			'/[a-z0-9._%+\-]+\s*(?:@|\[at\]|\(at\)|\{at\})\s*[a-z0-9\-]+/iu',
			// IPv4 / IPv6 literals
			'/\b\d{1,3}(?:\.\d{1,3}){3}\b/u',
			'/\b[0-9a-f]{0,4}(?::[0-9a-f]{0,4}){3,7}\b/iu',
			// shorteners and messaging handles that are links in disguise
			'/\b(?:t\.me|wa\.me|bit\.ly|goo\.gl|tinyurl|cutt\.ly|discord\.gg)\b/iu',
			// protocol-relative or bare slashes after a domain-like label
			'/(?:^|\s)\/\/[a-z0-9]/iu',
		];

		foreach ($patterns as $pattern)
		{
			if (preg_match($pattern, $probe) === 1)
			{
				return true;
			}
		}

		return false;
	}
}
