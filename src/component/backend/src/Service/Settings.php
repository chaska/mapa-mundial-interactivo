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

	/** Weekly ranking: points per published trip, per photo on it, per reaction given and per comment left (engagement is capped, see RankingService). */
	public function rankWeights(): array
	{
		return [
			'trip'    => $this->int('rank_w_trip', 20, 0, 1000),
			'photo'   => $this->int('rank_w_photo', 3, 0, 1000),
			'like'    => $this->int('rank_w_like', 1, 0, 1000),
			'comment' => $this->int('rank_w_comment', 2, 0, 1000),
		];
	}

	public function rankLikeCap(): int    { return $this->int('rank_like_cap', 20, 0, 500); }
	public function rankCommentCap(): int { return $this->int('rank_comment_cap', 20, 0, 500); }
	public function rankEnabled(): bool   { return (int) $this->params->get('rank_enabled', 1) === 1; }
	/** E-mail the author when a moderator publishes or rejects their trip. */
	public function notifyAuthor(): bool  { return (int) $this->params->get('notify_author', 1) === 1; }
	/** E-mail the podium of the week when it closes (off by default: it is not a transactional message). */
	public function notifyPodium(): bool  { return (int) $this->params->get('notify_podium', 0) === 1; }

	/** An opaque, stable key for a person (so the map can filter by author without publishing user ids). Same person, same key, on this site only. */
	public static function userKey(int $userId): string
	{
		$secret = (string) \Joomla\CMS\Factory::getApplication()->get('secret', '');

		return substr(hash_hmac('sha256', 'mapaviajes-user-' . $userId, $secret !== '' ? $secret : JPATH_ROOT), 0, 12);
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
