<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\Service;

defined('_JEXEC') || die;

use Chaska\Component\Mapaviajes\Administrator\Security\PrivateStorage;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\Registry\Registry;

/** The component options, always clamped to safe ranges whatever the database holds. */
final class Settings
{
	private Registry $params;

	public function __construct(?Registry $params = null)
	{
		$this->params = $params ?? ComponentHelper::getParams('com_mapaviajes');
	}

	private function int(string $key, int $default, int $min, int $max): int
	{
		return max($min, min($max, (int) $this->params->get($key, $default)));
	}

	public function maxPhotos(): int      { return $this->int('max_photos', 5, 1, 10); }
	public function longSide(): int       { $v = $this->int('long_side', 1000, 800, 1600); return \in_array($v, [800, 1000, 1600], true) ? $v : 1000; }
	public function quality(): int        { return $this->int('webp_quality', 80, 50, 95); }
	public function maxUploadBytes(): int { return $this->int('upload_max_mb', 10, 1, 20) * 1048576; }
	public function maxWords(): int       { return $this->int('max_words', 256, 20, 500); }
	public function perHour(): int        { return $this->int('trips_per_hour', 3, 1, 50); }
	public function perDay(): int         { return $this->int('trips_per_day', 10, 1, 200); }
	public function reviewFirst(): bool   { return (int) $this->params->get('review_first', 1) === 1; }
	public function commentsDefault(): bool { return (int) $this->params->get('comments_default', 1) === 1; }
	/** Which comment system the trip viewer uses: none, engage (Akeeba Engage / Engage) or events (any extension that listens to Joomla's content events). */
	public function commentsProvider(): string
	{
		$v = (string) $this->params->get('comments_provider', 'none');

		return \in_array($v, ['engage', 'events'], true) ? $v : 'none';
	}

	public function reactionsPerDay(): int { return $this->int('reactions_per_day', 300, 1, 5000); }
	public function showRealNames(): bool { return (int) $this->params->get('show_real_names', 0) === 1; }
	public function notifyAdmin(): bool   { return (int) $this->params->get('notify_admin', 1) === 1; }

	/** @return string[] valid e-mail addresses only */
	public function notifyEmails(): array
	{
		$out = [];

		foreach (preg_split('/[\s,;]+/', (string) $this->params->get('notify_emails', ''), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $mail)
		{
			if (filter_var($mail, FILTER_VALIDATE_EMAIL) !== false && \strlen($mail) <= 190 && !preg_match('/[\r\n]/', $mail))
			{
				$out[] = $mail;
			}
		}

		return \array_slice(array_values(array_unique($out)), 0, 5);
	}

	public function storage(): PrivateStorage
	{
		$custom = trim((string) $this->params->get('storage_path', ''));

		// A custom path must be absolute and free of traversal; otherwise the default is used.
		if ($custom !== '' && ($custom[0] === '/' || preg_match('#^[A-Za-z]:[\\\\/]#', $custom) === 1) && !str_contains($custom, '..') && !str_contains($custom, "\0"))
		{
			return new PrivateStorage($custom);
		}

		return new PrivateStorage(JPATH_ROOT . '/media/com_mapaviajes_private');
	}
}
