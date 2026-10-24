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
 * Friends: a request that the other person accepts (or declines), cancelled by either side at any time. Friends see each
 * other's "friends only" photos; nothing else changes (pins and thumbnails are public). Blocking stops requests and hides the
 * private photos. People are named on the wire only by the opaque key of Settings::userKey(), and the key is turned back into
 * a person only among people with something published or people already related to the asker. Nobody ever sees somebody
 * else's list of friends. Every write of one person happens under their row lock.
 */
final class FriendService
{
	public const MAX_FRIENDS = 500;
	public const MAX_PENDING = 20;
	public const MAX_REQUESTS_PER_DAY = 10;
	/** After a request was declined nobody may ask again for this long; a withdrawn request is not mailed again for a week */
	public const DECLINED_DAYS = 30;
	public const RESEND_DAYS = 7;

	public function __construct(private readonly DatabaseInterface $db)
	{
	}

	/** Ids of the accepted friends of a person. @return list<int> */
	public function friendIds(int $userId): array
	{
		if ($userId <= 0)
		{
			return [];
		}

		$a = $userId;
		$b = $userId;
		$q = $this->db->getQuery(true)
			->select('CASE WHEN ' . $this->db->quoteName('requester_id') . ' = :a THEN ' . $this->db->quoteName('addressee_id') . ' ELSE ' . $this->db->quoteName('requester_id') . ' END AS ' . $this->db->quoteName('fid'))
			->from($this->db->quoteName('#__mapaviajes_friends'))
			->where($this->db->quoteName('status') . ' = 1')
			->where('(' . $this->db->quoteName('requester_id') . ' = :a2 OR ' . $this->db->quoteName('addressee_id') . ' = :b)')
			->bind(':a', $a, ParameterType::INTEGER)->bind(':a2', $a, ParameterType::INTEGER)->bind(':b', $b, ParameterType::INTEGER);

		return array_map('intval', $this->db->setQuery($q)->loadColumn() ?: []);
	}

	public function areFriends(int $a, int $b): bool
	{
		if ($a <= 0 || $b <= 0 || $a === $b)
		{
			return false;
		}

		$x = $a;
		$y = $b;
		$x2 = $a;
		$y2 = $b;
		$q = $this->db->getQuery(true)->select('COUNT(*)')->from($this->db->quoteName('#__mapaviajes_friends'))->where($this->db->quoteName('status') . ' = 1')
			->where('((' . $this->db->quoteName('requester_id') . ' = :x AND ' . $this->db->quoteName('addressee_id') . ' = :y) OR (' . $this->db->quoteName('requester_id') . ' = :y2 AND ' . $this->db->quoteName('addressee_id') . ' = :x2))')
			->bind(':x', $x, ParameterType::INTEGER)->bind(':y', $y, ParameterType::INTEGER)->bind(':y2', $y2, ParameterType::INTEGER)->bind(':x2', $x2, ParameterType::INTEGER);

		return (int) $this->db->setQuery($q)->loadResult() > 0;
	}

	/** Somebody who blocked the other (either way): no requests, no private photos. */
	public function blockedBetween(int $a, int $b): bool
	{
		$x = $a;
		$y = $b;
		$x2 = $a;
		$y2 = $b;
		$q = $this->db->getQuery(true)->select('COUNT(*)')->from($this->db->quoteName('#__mapaviajes_blocks'))
			->where('((' . $this->db->quoteName('blocker_id') . ' = :x AND ' . $this->db->quoteName('blocked_id') . ' = :y) OR (' . $this->db->quoteName('blocker_id') . ' = :y2 AND ' . $this->db->quoteName('blocked_id') . ' = :x2))')
			->bind(':x', $x, ParameterType::INTEGER)->bind(':y', $y, ParameterType::INTEGER)->bind(':y2', $y2, ParameterType::INTEGER)->bind(':x2', $x2, ParameterType::INTEGER);

		return (int) $this->db->setQuery($q)->loadResult() > 0;
	}

	/**
	 * What the map needs: keys of friends, of people who asked me (with their name), of people I asked, and of people I blocked.
	 *
	 * @return array{friends: list<array{k: string, n: string}>, incoming: list<array{k: string, n: string}>, outgoing: list<array{k: string, n: string}>, blocked: list<array{k: string, n: string}>}
	 */
	public function state(int $userId, bool $realNames): array
	{
		$out = ['friends' => [], 'incoming' => [], 'outgoing' => [], 'blocked' => []];

		if ($userId <= 0)
		{
			return $out;
		}

		$fids  = $this->friendIds($userId);
		$fname = $this->names($fids, $realNames);

		foreach ($fids as $id)
		{
			$out['friends'][] = ['k' => Settings::userKey($id), 'n' => $fname[$id] ?? '?'];
		}

		$u  = $userId;
		$q  = $this->db->getQuery(true)->select($this->db->quoteName(['requester_id', 'addressee_id']))->from($this->db->quoteName('#__mapaviajes_friends'))->where($this->db->quoteName('status') . ' = 0')
			->where('(' . $this->db->quoteName('requester_id') . ' = :a OR ' . $this->db->quoteName('addressee_id') . ' = :b)')->bind(':a', $u, ParameterType::INTEGER)->bind(':b', $u, ParameterType::INTEGER);
		$in  = [];
		$ou  = [];

		foreach ($this->db->setQuery($q)->loadAssocList() ?: [] as $r)
		{
			if ((int) $r['requester_id'] === $userId)
			{
				$ou[] = (int) $r['addressee_id'];
			}
			else
			{
				$in[] = (int) $r['requester_id'];
			}
		}

		$names = $this->names(array_merge($in, $ou), $realNames);

		foreach ($ou as $id)
		{
			$out['outgoing'][] = ['k' => Settings::userKey($id), 'n' => $names[$id] ?? '?'];
		}

		foreach ($in as $id)
		{
			$out['incoming'][] = ['k' => Settings::userKey($id), 'n' => $names[$id] ?? '?'];
		}

		$bq  = $this->db->getQuery(true)->select($this->db->quoteName('blocked_id'))->from($this->db->quoteName('#__mapaviajes_blocks'))->where($this->db->quoteName('blocker_id') . ' = :a')->bind(':a', $u, ParameterType::INTEGER);
		$bl  = array_map('intval', $this->db->setQuery($bq)->loadColumn() ?: []);
		$bn  = $this->names($bl, $realNames);

		foreach ($bl as $id)
		{
			$out['blocked'][] = ['k' => Settings::userKey($id), 'n' => $bn[$id] ?? '?'];
		}

		return $out;
	}

	/**
	 * Asks for friendship (or accepts at once when the other person had already asked me).
	 *
	 * @return array{status: string} "pending" | "friends"
	 * @throws SubmissionException 403 guest, 404 unknown / yourself / blocked, 429 too many requests or friends
	 */
	public function request(User $user, string $key): array
	{
		$me = (int) $user->id;

		if ($me <= 0)
		{
			throw new SubmissionException('login', 403);
		}

		$target = $this->resolve($key, $me);

		if ($target === null || $target === $me || $this->blockedBetween($me, $target))
		{
			throw new SubmissionException('trip', 404);
		}

		$db     = $this->db;
		$status = 'pending';
		$mail   = false;
		$db->transactionStart();

		try
		{
			UserLock::take($db, $me);
			$mine  = $this->pair($me, $target);   // my request to them
			$their = $this->pair($target, $me);   // their request to me

			if (($mine !== null && (int) $mine['status'] === 1) || ($their !== null && (int) $their['status'] === 1))
			{
				$status = 'friends';
			}
			elseif ($mine !== null && (int) $mine['status'] === 0)
			{
				$status = 'pending'; // already asked: nothing to do
			}
			elseif ($mine !== null && (int) $mine['status'] === 2 && $this->recent($mine, self::DECLINED_DAYS))
			{
				throw new SubmissionException('trip', 404); // declined not long ago: the same answer as "no such person"
			}
			elseif ($their !== null && (int) $their['status'] === 0)
			{
				if ($this->countFriends($me) >= self::MAX_FRIENDS || $this->countFriends($target) >= self::MAX_FRIENDS)
				{
					throw new SubmissionException('rate', 429);
				}

				$this->setStatus((int) $their['id'], 1);
				$status = 'friends';
			}
			else
			{
				if ($this->countPending($me) >= self::MAX_PENDING || $this->requestsSince($me, Factory::getDate('-1 day')->toSql()) >= self::MAX_REQUESTS_PER_DAY)
				{
					throw new SubmissionException('rate', 429);
				}

				$now = Factory::getDate()->toSql();

				if ($mine !== null)
				{
					// an old declined / withdrawn request of mine: the same row asks again; the other person is mailed only if it was a while ago
					$mail = !$this->recent($mine, self::RESEND_DAYS);
					$mid  = (int) $mine['id'];
					$db->setQuery($db->getQuery(true)->update($db->quoteName('#__mapaviajes_friends'))->set($db->quoteName('status') . ' = 0')->set($db->quoteName('created') . ' = :c')->set($db->quoteName('answered') . ' = NULL')->where($db->quoteName('id') . ' = :i')
						->bind(':c', $now)->bind(':i', $mid, ParameterType::INTEGER))->execute();
				}
				else
				{
					$ra = $me;
					$rb = $target;
					$db->setQuery($db->getQuery(true)->insert($db->quoteName('#__mapaviajes_friends'))->columns($db->quoteName(['requester_id', 'addressee_id', 'status', 'created']))->values(':a, :b, 0, :c')
						->bind(':a', $ra, ParameterType::INTEGER)->bind(':b', $rb, ParameterType::INTEGER)->bind(':c', $now))->execute();
					$mail = true;
				}

				EventLog::add($db, $me, 'friend_req');
			}

			$db->transactionCommit();
		}
		catch (\Throwable $e)
		{
			$db->transactionRollback();

			throw $e;
		}

		if ($mail)
		{
			try
			{
				(new Notifier($db, new Settings()))->friendRequest($target, $this->names([$me], (new Settings())->showRealNames())[$me] ?? '');
			}
			catch (\Throwable $e)
			{
				// a mail problem never stops a request
			}
		}

		return ['status' => $status];
	}

	/**
	 * Answers a request somebody sent me.
	 *
	 * @param   string  $action  accept | decline | block
	 * @throws SubmissionException 404 there is no such request, 429 too many friends
	 */
	public function respond(User $user, string $key, string $action): void
	{
		$me = (int) $user->id;

		if ($me <= 0)
		{
			throw new SubmissionException('login', 403);
		}

		$target = $this->resolve($key, $me);

		if ($target === null || $target === $me || !\in_array($action, ['accept', 'decline', 'block'], true))
		{
			throw new SubmissionException('trip', 404);
		}

		$db = $this->db;
		$db->transactionStart();

		try
		{
			UserLock::take($db, $me);
			$row = $this->pair($target, $me);

			if ($row === null || (int) $row['status'] !== 0)
			{
				throw new SubmissionException('trip', 404);
			}

			if ($action === 'accept')
			{
				if ($this->countFriends($me) >= self::MAX_FRIENDS || $this->countFriends($target) >= self::MAX_FRIENDS)
				{
					throw new SubmissionException('rate', 429);
				}

				$this->setStatus((int) $row['id'], 1);
			}
			elseif ($action === 'decline')
			{
				$this->setStatus((int) $row['id'], 2); // kept, so the same person cannot ask again (and mail) at once
			}
			else
			{
				$this->deleteRow((int) $row['id']);
				$this->addBlock($me, $target);
			}

			$db->transactionCommit();
		}
		catch (\Throwable $e)
		{
			$db->transactionRollback();

			throw $e;
		}
	}

	/** Ends a friendship, or withdraws a request I made. Either side of a friendship may do it. */
	public function cancel(User $user, string $key): void
	{
		$me = (int) $user->id;

		if ($me <= 0)
		{
			throw new SubmissionException('login', 403);
		}

		$target = $this->resolve($key, $me);

		if ($target === null || $target === $me)
		{
			throw new SubmissionException('trip', 404);
		}

		$db = $this->db;
		$db->transactionStart();

		try
		{
			UserLock::take($db, $me);
			$mine  = $this->pair($me, $target);
			$their = $this->pair($target, $me);

			if ($mine !== null)
			{
				if ((int) $mine['status'] === 0)
				{
					$this->setStatus((int) $mine['id'], 3); // withdrawn: kept for a week so asking again does not mail again
				}
				elseif ((int) $mine['status'] === 1)
				{
					$this->deleteRow((int) $mine['id']);
				}
			}

			if ($their !== null && (int) $their['status'] === 1)
			{
				$this->deleteRow((int) $their['id']);
			}

			$db->transactionCommit();
		}
		catch (\Throwable $e)
		{
			$db->transactionRollback();

			throw $e;
		}
	}

	public function unblock(User $user, string $key): void
	{
		$me = (int) $user->id;
		$target = $me > 0 ? $this->resolve($key, $me) : null;

		if ($target === null)
		{
			throw new SubmissionException('trip', 404);
		}

		$a = $me;
		$b = $target;
		$this->db->setQuery($this->db->getQuery(true)->delete($this->db->quoteName('#__mapaviajes_blocks'))->where($this->db->quoteName('blocker_id') . ' = :a')->where($this->db->quoteName('blocked_id') . ' = :b')
			->bind(':a', $a, ParameterType::INTEGER)->bind(':b', $b, ParameterType::INTEGER))->execute();
	}

	/** Blocks a person and ends any friendship or request between us. */
	public function block(User $user, string $key): void
	{
		$me = (int) $user->id;
		$target = $me > 0 ? $this->resolve($key, $me) : null;

		if ($target === null || $target === $me)
		{
			throw new SubmissionException('trip', 404);
		}

		$db = $this->db;
		$db->transactionStart();

		try
		{
			UserLock::take($db, $me);

			foreach ([$this->pair($me, $target), $this->pair($target, $me)] as $row)
			{
				if ($row !== null)
				{
					$this->deleteRow((int) $row['id']);
				}
			}

			$this->addBlock($me, $target);
			$db->transactionCommit();
		}
		catch (\Throwable $e)
		{
			$db->transactionRollback();

			throw $e;
		}
	}

	/** Was this row answered / withdrawn less than $days days ago? @param array<string, mixed> $row */
	private function recent(array $row, int $days): bool
	{
		$r = $this->db->setQuery($this->db->getQuery(true)->select($this->db->quoteName('answered'))->from($this->db->quoteName('#__mapaviajes_friends'))->where($this->db->quoteName('id') . ' = ' . (int) $row['id']))->loadResult();

		return $r !== null && (string) $r > Factory::getDate('-' . $days . ' days')->toSql();
	}

	/** @return array<string, mixed>|null */
	private function pair(int $requester, int $addressee): ?array
	{
		$a = $requester;
		$b = $addressee;
		$row = $this->db->setQuery($this->db->getQuery(true)->select($this->db->quoteName(['id', 'status']))->from($this->db->quoteName('#__mapaviajes_friends'))->where($this->db->quoteName('requester_id') . ' = :a')->where($this->db->quoteName('addressee_id') . ' = :b')
			->bind(':a', $a, ParameterType::INTEGER)->bind(':b', $b, ParameterType::INTEGER))->loadAssoc();

		return $row ?: null;
	}

	private function setStatus(int $id, int $status): void
	{
		$i = $id;
		$s = $status;
		$now = Factory::getDate()->toSql();
		$this->db->setQuery($this->db->getQuery(true)->update($this->db->quoteName('#__mapaviajes_friends'))->set($this->db->quoteName('status') . ' = :s')->set($this->db->quoteName('answered') . ' = :n')->where($this->db->quoteName('id') . ' = :i')
			->bind(':s', $s, ParameterType::INTEGER)->bind(':n', $now)->bind(':i', $i, ParameterType::INTEGER))->execute();
	}

	private function deleteRow(int $id): void
	{
		$i = $id;
		$this->db->setQuery($this->db->getQuery(true)->delete($this->db->quoteName('#__mapaviajes_friends'))->where($this->db->quoteName('id') . ' = :i')->bind(':i', $i, ParameterType::INTEGER))->execute();
	}

	private function addBlock(int $blocker, int $blocked): void
	{
		$a = $blocker;
		$b = $blocked;
		$now = Factory::getDate()->toSql();

		try
		{
			$this->db->setQuery($this->db->getQuery(true)->insert($this->db->quoteName('#__mapaviajes_blocks'))->columns($this->db->quoteName(['blocker_id', 'blocked_id', 'created']))->values(':a, :b, :c')
				->bind(':a', $a, ParameterType::INTEGER)->bind(':b', $b, ParameterType::INTEGER)->bind(':c', $now))->execute();
		}
		catch (\Throwable $e)
		{
			// already blocked
		}
	}

	private function countFriends(int $userId): int
	{
		return \count($this->friendIds($userId));
	}

	private function countPending(int $requester): int
	{
		$a = $requester;

		return (int) $this->db->setQuery($this->db->getQuery(true)->select('COUNT(*)')->from($this->db->quoteName('#__mapaviajes_friends'))->where($this->db->quoteName('requester_id') . ' = :a')->where($this->db->quoteName('status') . ' = 0')->bind(':a', $a, ParameterType::INTEGER))->loadResult();
	}

	private function requestsSince(int $userId, string $since): int
	{
		$u = $userId;

		return (int) $this->db->setQuery($this->db->getQuery(true)->select('COUNT(*)')->from($this->db->quoteName('#__mapaviajes_events'))->where($this->db->quoteName('user_id') . ' = :u')->where($this->db->quoteName('event') . ' = ' . $this->db->quote('friend_req'))
			->where($this->db->quoteName('created') . ' >= :s')->bind(':u', $u, ParameterType::INTEGER)->bind(':s', $since))->loadResult();
	}

	/** @param list<int> $ids @return array<int, string> */
	private function names(array $ids, bool $realNames): array
	{
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

		if ($ids === [])
		{
			return [];
		}

		$rows = $this->db->setQuery($this->db->getQuery(true)->select($this->db->quoteName(['id', 'name', 'username']))->from($this->db->quoteName('#__users'))->whereIn($this->db->quoteName('id'), $ids))->loadAssocList() ?: [];
		$out  = [];

		foreach ($rows as $r)
		{
			$out[(int) $r['id']] = $realNames && trim((string) $r['name']) !== '' ? (string) $r['name'] : (string) $r['username'];
		}

		return $out;
	}

	/** The person behind an opaque key: people with something published, and people already related to me (a request, a friendship, a block). */
	private function resolve(string $key, int $me): ?int
	{
		if (preg_match('/^[a-f0-9]{12}$/', $key) !== 1)
		{
			return null;
		}

		$db  = $this->db;
		$q1  = $db->getQuery(true)->select('DISTINCT ' . $db->quoteName('user_id'))->from($db->quoteName('#__mapaviajes_trips'))->where($db->quoteName('published') . ' = 1');
		$ids = array_map('intval', $db->setQuery($q1)->loadColumn() ?: []);
		$m1  = $me;
		$m2  = $me;
		$q2  = $db->getQuery(true)->select($db->quoteName(['requester_id', 'addressee_id']))->from($db->quoteName('#__mapaviajes_friends'))->where('(' . $db->quoteName('requester_id') . ' = :a OR ' . $db->quoteName('addressee_id') . ' = :b)')->bind(':a', $m1, ParameterType::INTEGER)->bind(':b', $m2, ParameterType::INTEGER);

		foreach ($db->setQuery($q2)->loadAssocList() ?: [] as $r)
		{
			$ids[] = (int) $r['requester_id'];
			$ids[] = (int) $r['addressee_id'];
		}

		$m3 = $me;
		$q3 = $db->getQuery(true)->select($db->quoteName('blocked_id'))->from($db->quoteName('#__mapaviajes_blocks'))->where($db->quoteName('blocker_id') . ' = :a')->bind(':a', $m3, ParameterType::INTEGER);
		$ids = array_merge($ids, array_map('intval', $db->setQuery($q3)->loadColumn() ?: []));

		foreach (array_unique($ids) as $id)
		{
			if ($id > 0 && hash_equals(Settings::userKey($id), $key))
			{
				return $id;
			}
		}

		return null;
	}
}
