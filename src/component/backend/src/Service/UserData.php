<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\Service;

defined('_JEXEC') || die;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * What the component keeps about one person, and how it goes away. Used by Joomla's Privacy tools (access / erasure
 * requests) and when an account is deleted from the Users manager. Erasure removes the person's trips (with photo files
 * and their comment thread), their reactions, their preference and their ranking rows, and detaches them from the
 * security log (which only holds codes).
 */
final class UserData
{
	public function __construct(private DatabaseInterface $db)
	{
	}

	/**
	 * @return array<string, list<array<string, mixed>>>  table name => rows, nothing about other people
	 */
	public function export(int $userId): array
	{
		if ($userId <= 0)
		{
			return [];
		}

		return [
			'trips'     => $this->rows('SELECT id, album_id, title, place, lat, lon, allow_comments, published, photo_count, created FROM #__mapaviajes_trips WHERE user_id = :u ORDER BY id', $userId),
			'photos'    => $this->rows('SELECT p.id, p.trip_id, p.ordering, p.width, p.height, p.bytes, p.caption, p.created FROM #__mapaviajes_photos p JOIN #__mapaviajes_trips t ON t.id = p.trip_id WHERE t.user_id = :u ORDER BY p.id', $userId),
			'reactions' => $this->rows('SELECT id, trip_id, kind, active, created FROM #__mapaviajes_reactions WHERE user_id = :u ORDER BY id', $userId),
			'ranking'   => $this->rows('SELECT week_start, position, score, trips, photos, likes, comments, stars, points FROM #__mapaviajes_ranking WHERE user_id = :u ORDER BY week_start', $userId),
			'settings'  => $this->rows('SELECT hide_ranking, digest, follows_seen FROM #__mapaviajes_prefs WHERE user_id = :u', $userId),
			'following' => $this->rows('SELECT followed_id, created FROM #__mapaviajes_follows WHERE follower_id = :u ORDER BY id', $userId),
			'events'    => $this->rows('SELECT event, detail, created FROM #__mapaviajes_events WHERE user_id = :u ORDER BY id', $userId),
		];
	}

	/** Deletes everything above that belongs to the person (and the photo files). Safe to run twice. */
	public function erase(int $userId): void
	{
		if ($userId <= 0)
		{
			return;
		}

		$storage = (new Settings())->storage();
		$bridge  = new CommentsBridge($this->db, new Settings());
		$trips   = $this->rows('SELECT id, asset_id FROM #__mapaviajes_trips WHERE user_id = :u', $userId);

		foreach ($trips as $t)
		{
			$id    = (int) $t['id'];
			$files = $this->db->setQuery($this->db->getQuery(true)->select($this->db->quoteName('filename'))->from($this->db->quoteName('#__mapaviajes_photos'))->where($this->db->quoteName('trip_id') . ' = :i')->bind(':i', $id, ParameterType::INTEGER))->loadColumn() ?: [];

			foreach (['#__mapaviajes_photos', '#__mapaviajes_reactions'] as $table)
			{
				$tid = $id;
				$this->db->setQuery($this->db->getQuery(true)->delete($this->db->quoteName($table))->where($this->db->quoteName('trip_id') . ' = :t')->bind(':t', $tid, ParameterType::INTEGER))->execute();
			}

			$tid2 = $id;
			$this->db->setQuery($this->db->getQuery(true)->delete($this->db->quoteName('#__mapaviajes_trips'))->where($this->db->quoteName('id') . ' = :t')->bind(':t', $tid2, ParameterType::INTEGER))->execute();

			foreach ($files as $name)
			{
				$storage->delete((string) $name);
			}

			$bridge->forget([(int) $t['asset_id']]);
		}

		// who they follow and who follows them
		foreach (['follower_id', 'followed_id'] as $col)
		{
			$fu = $userId;
			$this->db->setQuery($this->db->getQuery(true)->delete($this->db->quoteName('#__mapaviajes_follows'))->where($this->db->quoteName($col) . ' = :f')->bind(':f', $fu, ParameterType::INTEGER))->execute();
		}

		foreach (['#__mapaviajes_reactions', '#__mapaviajes_ranking', '#__mapaviajes_prefs'] as $table)
		{
			$u = $userId;
			$this->db->setQuery($this->db->getQuery(true)->delete($this->db->quoteName($table))->where($this->db->quoteName('user_id') . ' = :u')->bind(':u', $u, ParameterType::INTEGER))->execute();
		}

		$u2 = $userId;
		$zero = 0;
		$this->db->setQuery($this->db->getQuery(true)->update($this->db->quoteName('#__mapaviajes_events'))->set($this->db->quoteName('user_id') . ' = :z')->where($this->db->quoteName('user_id') . ' = :u')->bind(':z', $zero, ParameterType::INTEGER)->bind(':u', $u2, ParameterType::INTEGER))->execute();
	}

	/** @return list<array<string, mixed>> */
	private function rows(string $sql, int $userId): array
	{
		$sql = str_replace(':u', (string) (int) $userId, $sql); // an integer cast right here: nothing else can reach this string

		return $this->db->setQuery($sql)->loadAssocList() ?: [];
	}
}
