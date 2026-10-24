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
 * The weekly podium.
 *
 * A week is Monday 00:00 to Sunday 24:00 UTC. Per person and week:
 *   base       = w_trip * published trips with photos + w_photo * public photos on those trips + w_photo_private * friends-only photos (worth less)
 *   engagement = w_like * min(reactions given to OTHER people's published trips, like cap)
 *              + w_comment * min(published comments left on OTHER people's trips, comment cap)
 *   score      = base + min(engagement, base)
 * so nobody climbs by clicking or typing alone: without published trips there are no points, and engagement can at
 * most double what trips and photos earned. Blocked users and people who asked to stay out never appear.
 * Ties are broken by trips, then photos, then who got there first, then id: the same data always gives the same podium.
 *
 * A week is closed lazily, once it ended at least two days ago (moderation catches up first): its top ten are written
 * once and never change. Position 1-3 earn 3/2/1 stars; podium points for the all-time table are 10/6/3 and 1 for 4th-10th.
 */
final class RankingService
{
	public const STARS  = [1 => 3, 2 => 2, 3 => 1];
	public const POINTS = [1 => 10, 2 => 6, 3 => 3];
	public const TOP    = 10;
	public const GRACE_DAYS = 2;
	public const MAX_BACK_WEEKS = 26;

	public function __construct(private DatabaseInterface $db, private Settings $settings)
	{
	}

	/** Monday of the week containing the timestamp, as Y-m-d (UTC). */
	public static function weekStart(int $timestamp): string
	{
		$day = (int) gmdate('N', $timestamp); // 1 = Monday

		return gmdate('Y-m-d', $timestamp - ($day - 1) * 86400);
	}

	public static function weekEnd(string $weekStart): string
	{
		return gmdate('Y-m-d', strtotime($weekStart . ' UTC') + 7 * 86400);
	}

	public static function pointsFor(int $position): int
	{
		return self::POINTS[$position] ?? ($position <= self::TOP ? 1 : 0);
	}

	/**
	 * The ranking of one week computed from the live data (used for the current week and for closing past ones).
	 *
	 * @return list<array<string, int|string>>  best first, at most $limit rows
	 */
	public function compute(string $weekStart, int $limit = self::TOP): array
	{
		$from = $weekStart . ' 00:00:00';
		$to   = self::weekEnd($weekStart) . ' 00:00:00';
		$w    = $this->settings->rankWeights();

		$trips = $this->perUser(
			'SELECT t.user_id AS uid, COUNT(*) AS n, MIN(t.created) AS first FROM ' . $this->t('trips') . ' t'
			. ' WHERE t.published = 1 AND t.photo_count > 0 AND t.created >= :f AND t.created < :t GROUP BY t.user_id',
			$from,
			$to
		);
		$photos = $this->perUser(
			'SELECT t.user_id AS uid, SUM(CASE WHEN p.friends_only = 0 THEN 1 ELSE 0 END) AS n, SUM(CASE WHEN p.friends_only = 1 THEN 1 ELSE 0 END) AS np FROM ' . $this->t('trips') . ' t JOIN ' . $this->t('photos') . ' p ON p.trip_id = t.id'
			. ' WHERE t.published = 1 AND p.state = 1 AND t.created >= :f AND t.created < :t GROUP BY t.user_id',
			$from,
			$to
		);
		$likes = $this->perUser(
			'SELECT r.user_id AS uid, COUNT(*) AS n FROM ' . $this->t('reactions') . ' r JOIN ' . $this->t('trips') . ' t ON t.id = r.trip_id'
			. ' WHERE r.active = 1 AND t.published = 1 AND t.user_id <> r.user_id AND r.created >= :f AND r.created < :t GROUP BY r.user_id',
			$from,
			$to
		);
		$comments = $this->tableExists('engage_comments') ? $this->perUser(
			'SELECT c.created_by AS uid, COUNT(*) AS n FROM ' . $this->t('engage_comments') . ' c JOIN ' . $this->t('trips') . ' t ON t.asset_id = c.asset_id'
			. ' WHERE c.enabled = 1 AND t.published = 1 AND t.asset_id > 0 AND c.created_by IS NOT NULL AND c.created_by <> t.user_id AND c.created >= :f AND c.created < :t GROUP BY c.created_by',
			$from,
			$to
		) : [];

		$ids = array_keys($trips);

		if ($ids === [])
		{
			return [];
		}

		$people = $this->people($ids);
		$rows   = [];

		foreach ($ids as $uid)
		{
			if (!isset($people[$uid]))
			{
				continue; // blocked or deleted
			}

			$nTrips    = (int) $trips[$uid]['n'];
			$nPhotos   = (int) ($photos[$uid]['n'] ?? 0);
			$nLikes    = min((int) ($likes[$uid]['n'] ?? 0), $this->settings->rankLikeCap());
			$nComments = min((int) ($comments[$uid]['n'] ?? 0), $this->settings->rankCommentCap());
			$nPrivate  = (int) ($photos[$uid]['np'] ?? 0);
			$base      = $w['trip'] * $nTrips + $w['photo'] * $nPhotos + $w['photo_private'] * $nPrivate;
			$engage    = $w['like'] * $nLikes + $w['comment'] * $nComments;
			$score     = $base + min($engage, $base);

			if ($score <= 0)
			{
				continue;
			}

			$rows[] = [
				'user_id'  => $uid, 'username' => $people[$uid]['username'], 'name' => $people[$uid]['name'],
				'score'    => $score, 'trips' => $nTrips, 'photos' => $nPhotos + $nPrivate, 'likes' => $nLikes, 'comments' => $nComments,
				'first'    => (string) $trips[$uid]['first'],
			];
		}

		usort($rows, static fn (array $a, array $b): int => [$b['score'], $b['trips'], $b['photos'], $a['first'], $a['user_id']] <=> [$a['score'], $a['trips'], $a['photos'], $b['first'], $b['user_id']]);
		$rows = \array_slice($rows, 0, max(1, $limit));

		foreach ($rows as $i => &$r)
		{
			$r['position'] = $i + 1;
			$r['stars']    = self::STARS[$i + 1] ?? 0;
			$r['points']   = self::pointsFor($i + 1);
		}

		unset($r);

		return $rows;
	}

	/**
	 * Closes every finished week that is not closed yet (at most MAX_BACK_WEEKS back, never before the first trip).
	 * Safe to call on every request and from several requests at once: the week row is the lock.
	 *
	 * @return list<string>  the weeks closed now
	 */
	public function closePending(?int $now = null): array
	{
		if (!$this->settings->rankEnabled())
		{
			return [];
		}

		$now      = $now ?? time();
		$firstRaw = (string) $this->db->setQuery($this->db->getQuery(true)->select('MIN(' . $this->db->quoteName('created') . ')')->from($this->db->quoteName('#__mapaviajes_trips'))->where($this->db->quoteName('published') . ' = 1'))->loadResult();

		if ($firstRaw === '')
		{
			return [];
		}

		$week   = self::weekStart(strtotime($firstRaw . ' UTC') ?: $now);
		$oldest = self::weekStart($now - self::MAX_BACK_WEEKS * 7 * 86400);
		$week   = max($week, $oldest);
		$closed = [];

		while (strtotime(self::weekEnd($week) . ' UTC') + self::GRACE_DAYS * 86400 <= $now)
		{
			if ($this->closeWeek($week, $now))
			{
				$closed[] = $week;
			}

			$week = self::weekEnd($week);
		}

		return $closed;
	}

	private function closeWeek(string $week, int $now): bool
	{
		$w = $week;

		if ((int) $this->db->setQuery($this->db->getQuery(true)->select('COUNT(*)')->from($this->db->quoteName('#__mapaviajes_weeks'))->where($this->db->quoteName('week_start') . ' = :w')->bind(':w', $w))->loadResult() > 0)
		{
			return false;
		}

		$rows = $this->compute($week);

		try
		{
			$this->db->transactionStart();
			$at = gmdate('Y-m-d H:i:s', $now);
			$wk = $week;
			$this->db->setQuery($this->db->getQuery(true)->insert($this->db->quoteName('#__mapaviajes_weeks'))->columns($this->db->quoteName(['week_start', 'closed']))->values(':w, :c')->bind(':w', $wk)->bind(':c', $at))->execute();

			foreach ($rows as $r)
			{
				$vals = [$week, (int) $r['user_id'], (int) $r['position'], (int) $r['score'], (int) $r['trips'], (int) $r['photos'], (int) $r['likes'], (int) $r['comments'], (int) $r['stars'], (int) $r['points']];
				$q    = $this->db->getQuery(true)->insert($this->db->quoteName('#__mapaviajes_ranking'))
					->columns($this->db->quoteName(['week_start', 'user_id', 'position', 'score', 'trips', 'photos', 'likes', 'comments', 'stars', 'points']))
					->values(implode(',', array_map(fn ($i) => ':v' . $i, array_keys($vals))));

				foreach ($vals as $i => $v)
				{
					$q->bind(':v' . $i, $vals[$i], \is_int($v) ? ParameterType::INTEGER : ParameterType::STRING);
				}

				$this->db->setQuery($q)->execute();
			}

			$this->db->transactionCommit();
		}
		catch (\Throwable $e)
		{
			// Somebody else closed it at the same instant (unique key), or the database refused: nothing half-written stays.
			try
			{
				$this->db->transactionRollback();
			}
			catch (\Throwable $e2)
			{
				// nothing to roll back
			}

			return false;
		}

		return true;
	}

	/** A closed week: its stored top ten, or null while the week is open. */
	public function closedWeek(string $weekStart): ?array
	{
		$w = $weekStart;

		if ((int) $this->db->setQuery($this->db->getQuery(true)->select('COUNT(*)')->from($this->db->quoteName('#__mapaviajes_weeks'))->where($this->db->quoteName('week_start') . ' = :w')->bind(':w', $w))->loadResult() === 0)
		{
			return null;
		}

		$w2 = $weekStart;
		$q  = $this->db->getQuery(true)
			->select(['r.*', $this->db->quoteName('u.username'), $this->db->quoteName('u.name')])
			->from($this->db->quoteName('#__mapaviajes_ranking', 'r'))
			->join('LEFT', $this->db->quoteName('#__users', 'u') . ' ON u.id = r.user_id')
			->where($this->db->quoteName('r.week_start') . ' = :w')->bind(':w', $w2)
			->order($this->db->quoteName('r.position') . ' ASC');

		return $this->db->setQuery($q)->loadAssocList() ?: [];
	}

	/** The most recent closed week that has at least one person, or null. */
	public function lastClosedWeek(): ?string
	{
		$q = $this->db->getQuery(true)->select($this->db->quoteName('w.week_start'))->from($this->db->quoteName('#__mapaviajes_weeks', 'w'))
			->where('EXISTS (SELECT 1 FROM ' . $this->db->quoteName('#__mapaviajes_ranking') . ' r WHERE r.week_start = w.week_start)')
			->order($this->db->quoteName('w.week_start') . ' DESC');
		$v = $this->db->setQuery($q, 0, 1)->loadResult();

		return $v ? (string) $v : null;
	}

	/**
	 * The all-time table: podium points summed over the closed weeks.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function allTime(int $limit = self::TOP): array
	{
		$q = $this->db->getQuery(true)
			->select([$this->db->quoteName('r.user_id'), 'SUM(r.points) AS points', 'SUM(r.stars) AS stars', 'COUNT(*) AS weeks', 'SUM(CASE WHEN r.position <= 3 THEN 1 ELSE 0 END) AS podiums', 'MIN(r.position) AS best', $this->db->quoteName('u.username'), $this->db->quoteName('u.name')])
			->from($this->db->quoteName('#__mapaviajes_ranking', 'r'))
			->join('INNER', $this->db->quoteName('#__users', 'u') . ' ON u.id = r.user_id AND u.block = 0')
			->group([$this->db->quoteName('r.user_id'), $this->db->quoteName('u.username'), $this->db->quoteName('u.name')])
			->order('SUM(r.points) DESC, SUM(r.stars) DESC, MIN(r.position) ASC, r.user_id ASC');

		return $this->db->setQuery($q, 0, max(1, $limit))->loadAssocList() ?: [];
	}

	/** @param list<int> $ids  @return array<int, array{username: string, name: string}> everyone who is not blocked */
	private function people(array $ids): array
	{
		$ids = array_values(array_unique(array_map('intval', $ids)));
		$q   = $this->db->getQuery(true)
			->select([$this->db->quoteName('u.id'), $this->db->quoteName('u.username'), $this->db->quoteName('u.name')])
			->from($this->db->quoteName('#__users', 'u'))
			->where($this->db->quoteName('u.block') . ' = 0')
			->whereIn($this->db->quoteName('u.id'), $ids);
		$out = [];

		foreach ($this->db->setQuery($q)->loadAssocList() ?: [] as $r)
		{
			$out[(int) $r['id']] = ['username' => (string) $r['username'], 'name' => (string) $r['name']];
		}

		return $out;
	}

	/** @return array<int, array<string, mixed>> keyed by the uid column. The two dates are built by weekStart(): the quoting is belt and braces. */
	private function perUser(string $sql, string $from, string $to): array
	{
		$sql = str_replace([':f', ':t'], [$this->db->quote($from), $this->db->quote($to)], $sql);
		$out = [];

		foreach ($this->db->setQuery($sql)->loadAssocList() ?: [] as $r)
		{
			$out[(int) $r['uid']] = $r;
		}

		return $out;
	}

	/** A table name with Joomla's #__ prefix placeholder (the engage table belongs to Engage, not to us). */
	private function t(string $name): string
	{
		return $name === 'engage_comments' ? '#__engage_comments' : '#__mapaviajes_' . $name;
	}

	private function tableExists(string $name): bool
	{
		return \in_array($this->db->getPrefix() . $name, $this->db->getTableList(), true);
	}
}
