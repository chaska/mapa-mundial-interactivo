<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\Service;

defined('_JEXEC') || die;

use Chaska\Component\Mapaviajes\Administrator\Security\UserLock;
use Joomla\CMS\Factory;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * "Follow" a traveller: you are told when they publish new trips. People are named on the wire only by the opaque key of
 * Settings::userKey() (never a user id); the key is turned back into a person only among people with published trips, and
 * nobody can follow themselves or a person with nothing published. All writes of one person happen under their row lock.
 */
final class FollowService
{
	public const MAX_FOLLOWS = 300;

	public function __construct(private readonly DatabaseInterface $db)
	{
	}

	/**
	 * Follows or stops following the person behind an opaque key.
	 *
	 * @return array{following: bool}
	 * @throws SubmissionException 404 unknown / yourself, 429 too many follows
	 */
	public function toggle(User $user, string $key): array
	{
		$me = (int) $user->id;

		if ($me <= 0)
		{
			throw new SubmissionException('login', 403);
		}

		$target = $this->resolve($key);

		if ($target === null || $target === $me)
		{
			throw new SubmissionException('trip', 404);
		}

		$db = $this->db;
		$db->transactionStart();

		try
		{
			UserLock::take($db, $me);
			$row = $this->row($me, $target);

			if ($row !== null)
			{
				$id = (int) $row;
				$db->setQuery($db->getQuery(true)->delete($db->quoteName('#__mapaviajes_follows'))->where($db->quoteName('id') . ' = :i')->bind(':i', $id, ParameterType::INTEGER))->execute();
				$following = false;
			}
			else
			{
				if ($this->count($me) >= self::MAX_FOLLOWS)
				{
					throw new SubmissionException('rate', 429);
				}

				$now = Factory::getDate()->toSql();
				$db->setQuery(
					$db->getQuery(true)->insert($db->quoteName('#__mapaviajes_follows'))->columns($db->quoteName(['follower_id', 'followed_id', 'created']))->values(':a, :b, :c')
						->bind(':a', $me, ParameterType::INTEGER)->bind(':b', $target, ParameterType::INTEGER)->bind(':c', $now)
				)->execute();
				$following = true;
			}

			$db->transactionCommit();
		}
		catch (\Throwable $e)
		{
			$db->transactionRollback();

			throw $e;
		}

		return ['following' => $following];
	}

	/** What the map needs about this person's follows: the keys of who they follow, when they last looked, and the weekly-mail switch. */
	public function state(int $userId): array
	{
		$keys = [];

		foreach ($this->followedIds($userId) as $id)
		{
			$keys[] = Settings::userKey($id);
		}

		$prefs = $this->prefs($userId);

		return ['keys' => $keys, 'seen' => $prefs['seen'], 'digest' => $prefs['digest']];
	}

	/** The person has looked at the news: nothing before now is new any more. */
	public function markSeen(int $userId): void
	{
		$this->upsert($userId, 'follows_seen', Factory::getDate()->toSql());
	}

	public function setDigest(int $userId, bool $on): void
	{
		$this->upsert($userId, 'digest', $on ? 1 : 0);
	}

	/** @return list<int> */
	public function followedIds(int $userId): array
	{
		$id = $userId;

		return array_map('intval', $this->db->setQuery($this->db->getQuery(true)->select($this->db->quoteName('followed_id'))->from($this->db->quoteName('#__mapaviajes_follows'))->where($this->db->quoteName('follower_id') . ' = :u')->bind(':u', $id, ParameterType::INTEGER))->loadColumn() ?: []);
	}

	/**
	 * Only one request every few hours may run the weekly summaries: whoever updates the site-wide marker (the row of user 0) wins.
	 */
	public function claimRun(int $hours): bool
	{
		$db  = $this->db;
		$now = Factory::getDate()->toSql();
		$cut = Factory::getDate('-' . max(1, $hours) . ' hours')->toSql();
		$q   = $db->getQuery(true)->update($db->quoteName('#__mapaviajes_prefs'))->set($db->quoteName('digest_sent') . ' = :n')->where($db->quoteName('user_id') . ' = 0')
			->where('(' . $db->quoteName('digest_sent') . ' IS NULL OR ' . $db->quoteName('digest_sent') . ' < :c)')->bind(':n', $now)->bind(':c', $cut);
		$db->setQuery($q)->execute();

		if ($db->getAffectedRows() > 0)
		{
			return true;
		}

		try
		{
			$db->setQuery($db->getQuery(true)->insert($db->quoteName('#__mapaviajes_prefs'))->columns($db->quoteName(['user_id', 'digest_sent']))->values('0, :n')->bind(':n', $now))->execute();

			return true; // the marker did not exist: this request is the first
		}
		catch (\Throwable $e)
		{
			return false; // it exists and is recent (or another request just created it)
		}
	}

	/**
	 * Who gets a weekly summary now and about what: people who switched it on, at most one per 6 days, with the people they
	 * follow that published since the last summary (at most a week back). Nothing to say = no job.
	 *
	 * @return list<array{user_id: int, authors: list<array{name: string, n: int}>}>
	 */
	public function digestJobs(bool $realNames): array
	{
		$db   = $this->db;
		$jobs = [];
		$rows = $db->setQuery($db->getQuery(true)->select($db->quoteName(['p.user_id', 'p.digest_sent']))->from($db->quoteName('#__mapaviajes_prefs', 'p'))
			->join('INNER', $db->quoteName('#__users', 'u') . ' ON u.id = p.user_id AND u.block = 0')->where($db->quoteName('p.digest') . ' = 1')->where($db->quoteName('p.user_id') . ' > 0'))->loadAssocList() ?: [];
		$weekAgo = Factory::getDate('-7 days')->toSql();
		$sixDays = Factory::getDate('-6 days')->toSql();

		foreach ($rows as $r)
		{
			$uid = (int) $r['user_id'];

			if ($r['digest_sent'] !== null && (string) $r['digest_sent'] > $sixDays)
			{
				continue;
			}

			$followed = $this->followedIds($uid);

			if ($followed === [])
			{
				continue;
			}

			$since = $r['digest_sent'] !== null && (string) $r['digest_sent'] > $weekAgo ? (string) $r['digest_sent'] : $weekAgo;
			$q = $db->getQuery(true)->select([$db->quoteName('t.user_id'), 'COUNT(*) AS n', $db->quoteName('u.name', 'name'), $db->quoteName('u.username', 'username')])->from($db->quoteName('#__mapaviajes_trips', 't'))
				->join('INNER', $db->quoteName('#__users', 'u') . ' ON u.id = t.user_id')->where($db->quoteName('t.published') . ' = 1')->where($db->quoteName('t.photo_count') . ' > 0')
				->where($db->quoteName('t.created') . ' > :since')->whereIn($db->quoteName('t.user_id'), $followed)->group($db->quoteName(['t.user_id', 'u.name', 'u.username']))->bind(':since', $since);
			$authors = [];

			foreach ($db->setQuery($q)->loadAssocList() ?: [] as $a)
			{
				$name = $realNames && trim((string) $a['name']) !== '' ? (string) $a['name'] : (string) $a['username'];
				$authors[] = ['name' => $name, 'n' => (int) $a['n']];
			}

			if ($authors !== [])
			{
				$jobs[] = ['user_id' => $uid, 'authors' => $authors];
			}
		}

		return $jobs;
	}

	public function markDigestSent(int $userId): void
	{
		$this->upsert($userId, 'digest_sent', Factory::getDate()->toSql());
	}

	/** @return array{seen: int, digest: int} seen = epoch of the last look (0 = never) */
	private function prefs(int $userId): array
	{
		$id  = $userId;
		$row = $this->db->setQuery($this->db->getQuery(true)->select($this->db->quoteName(['follows_seen', 'digest']))->from($this->db->quoteName('#__mapaviajes_prefs'))->where($this->db->quoteName('user_id') . ' = :u')->bind(':u', $id, ParameterType::INTEGER))->loadAssoc();

		return ['seen' => $row && $row['follows_seen'] ? (strtotime((string) $row['follows_seen'] . ' UTC') ?: 0) : 0, 'digest' => $row ? (int) $row['digest'] : 0];
	}

	private function upsert(int $userId, string $column, $value): void
	{
		if (!\in_array($column, ['follows_seen', 'digest', 'digest_sent'], true) || $userId <= 0)
		{
			return;
		}

		$db = $this->db;

		for ($try = 0; $try < 2; $try++)
		{
			$u = $userId;
			$v = $value;
			$q = $db->getQuery(true)->update($db->quoteName('#__mapaviajes_prefs'))->set($db->quoteName($column) . ' = :v')->where($db->quoteName('user_id') . ' = :u')->bind(':u', $u, ParameterType::INTEGER);
			$q->bind(':v', $v, \is_int($value) ? ParameterType::INTEGER : ParameterType::STRING);
			$db->setQuery($q)->execute();

			if ($db->getAffectedRows() > 0)
			{
				return;
			}

			try
			{
				$u2 = $userId;
				$v2 = $value;
				$ins = $db->getQuery(true)->insert($db->quoteName('#__mapaviajes_prefs'))->columns($db->quoteName(['user_id', $column]))->values(':u, :v')->bind(':u', $u2, ParameterType::INTEGER);
				$ins->bind(':v', $v2, \is_int($value) ? ParameterType::INTEGER : ParameterType::STRING);
				$db->setQuery($ins)->execute();

				return;
			}
			catch (\Throwable $e)
			{
				// somebody else created the row at the same instant: update it on the next turn
			}
		}
	}

	private function row(int $follower, int $followed): ?int
	{
		$a = $follower;
		$b = $followed;
		$id = $this->db->setQuery($this->db->getQuery(true)->select($this->db->quoteName('id'))->from($this->db->quoteName('#__mapaviajes_follows'))->where($this->db->quoteName('follower_id') . ' = :a')->where($this->db->quoteName('followed_id') . ' = :b')->bind(':a', $a, ParameterType::INTEGER)->bind(':b', $b, ParameterType::INTEGER))->loadResult();

		return $id === null || $id === false ? null : (int) $id;
	}

	private function count(int $follower): int
	{
		$a = $follower;

		return (int) $this->db->setQuery($this->db->getQuery(true)->select('COUNT(*)')->from($this->db->quoteName('#__mapaviajes_follows'))->where($this->db->quoteName('follower_id') . ' = :a')->bind(':a', $a, ParameterType::INTEGER))->loadResult();
	}

	/** The person behind an opaque key, only among people who have something published. */
	private function resolve(string $key): ?int
	{
		if (preg_match('/^[a-f0-9]{12}$/', $key) !== 1)
		{
			return null;
		}

		$ids = $this->db->setQuery($this->db->getQuery(true)->select('DISTINCT ' . $this->db->quoteName('user_id'))->from($this->db->quoteName('#__mapaviajes_trips'))->where($this->db->quoteName('published') . ' = 1'))->loadColumn() ?: [];

		foreach ($ids as $id)
		{
			if (hash_equals(Settings::userKey((int) $id), $key))
			{
				return (int) $id;
			}
		}

		return null;
	}
}
