<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\Service;

defined('_JEXEC') || die;

use Joomla\CMS\Factory;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * "Me gusta" (like) and "fan" (star) on a trip. One row per person, trip and kind; a click flips it.
 *
 * Rules, all enforced here and nowhere else: only a registered user, only a PUBLISHED trip in an album that user may
 * see, never your own trip (it would be a free way to climb the ranking), and a daily cap on NEW reactions.
 */
final class ReactionService
{
	public const KINDS = ['like', 'fan'];

	public function __construct(private DatabaseInterface $db, private Settings $settings)
	{
	}

	/**
	 * @return array{active: bool, count: int}
	 * @throws SubmissionException
	 */
	public function toggle(User $user, int $tripId, string $kind): array
	{
		$userId = (int) $user->id;

		if ($userId <= 0)
		{
			throw new SubmissionException('login', 403);
		}

		if (!\in_array($kind, self::KINDS, true))
		{
			throw new SubmissionException('reaction', 422);
		}

		$trip = $this->visibleTrip($user, $tripId);

		if ($trip === null)
		{
			throw new SubmissionException('trip', 404);
		}

		if ((int) $trip['user_id'] === $userId && !$this->settings->canSelfReact($user))
		{
			throw new SubmissionException('own_trip', 403);
		}

		$now = Factory::getDate()->toSql();
		$row = $this->find($tripId, $userId, $kind);

		if ($row === null)
		{
			if ($this->newToday($userId) >= $this->settings->reactionsPerDay())
			{
				EventLog::add($this->db, $userId, 'rate_limit', 'reaction');
				throw new SubmissionException('rate', 429);
			}

			try
			{
				$this->insert($tripId, $userId, $kind, $now);
			}
			catch (\Throwable $e)
			{
				// Two clicks at the same instant: the unique index let one through. The other simply becomes a flip.
				$row = $this->find($tripId, $userId, $kind);

				if ($row === null)
				{
					throw new SubmissionException('server', 500);
				}

				$this->flip((int) $row['id'], $now);
			}
		}
		else
		{
			$this->flip((int) $row['id'], $now);
		}

		$after = $this->find($tripId, $userId, $kind);

		return ['active' => $after !== null && (int) $after['active'] === 1, 'count' => $this->count($tripId, $kind)];
	}

	/** @return array{user_id: int}|null */
	private function visibleTrip(User $user, int $tripId): ?array
	{
		if ($tripId <= 0)
		{
			return null;
		}

		$id     = $tripId;
		$levels = array_map('intval', $user->getAuthorisedViewLevels());
		$query  = $this->db->getQuery(true)
			->select([$this->db->quoteName('t.user_id'), $this->db->quoteName('a.access')])
			->from($this->db->quoteName('#__mapaviajes_trips', 't'))
			->join('INNER', $this->db->quoteName('#__mapaviajes_albums', 'a') . ' ON ' . $this->db->quoteName('a.id') . ' = ' . $this->db->quoteName('t.album_id'))
			->where($this->db->quoteName('t.id') . ' = :t')
			->where($this->db->quoteName('t.published') . ' = 1')
			->where($this->db->quoteName('a.published') . ' = 1')
			->bind(':t', $id, ParameterType::INTEGER);
		$row = $this->db->setQuery($query)->loadAssoc();

		if (!$row || !\in_array((int) $row['access'], $levels, true))
		{
			return null;
		}

		return ['user_id' => (int) $row['user_id']];
	}

	/** @return array{id: int, active: int}|null */
	private function find(int $tripId, int $userId, string $kind): ?array
	{
		$t = $tripId;
		$u = $userId;
		$k = $kind;
		$query = $this->db->getQuery(true)
			->select($this->db->quoteName(['id', 'active']))
			->from($this->db->quoteName('#__mapaviajes_reactions'))
			->where($this->db->quoteName('trip_id') . ' = :t')
			->where($this->db->quoteName('user_id') . ' = :u')
			->where($this->db->quoteName('kind') . ' = :k')
			->bind(':t', $t, ParameterType::INTEGER)
			->bind(':u', $u, ParameterType::INTEGER)
			->bind(':k', $k);
		$row = $this->db->setQuery($query)->loadAssoc();

		return $row ? ['id' => (int) $row['id'], 'active' => (int) $row['active']] : null;
	}

	private function insert(int $tripId, int $userId, string $kind, string $now): void
	{
		$t = $tripId;
		$u = $userId;
		$k = $kind;
		$c = $now;
		$m = $now;
		$this->db->setQuery(
			$this->db->getQuery(true)
				->insert($this->db->quoteName('#__mapaviajes_reactions'))
				->columns($this->db->quoteName(['trip_id', 'user_id', 'kind', 'active', 'created', 'changed']))
				->values(':t, :u, :k, 1, :c, :m')
				->bind(':t', $t, ParameterType::INTEGER)
				->bind(':u', $u, ParameterType::INTEGER)
				->bind(':k', $k)
				->bind(':c', $c)
				->bind(':m', $m)
		)->execute();
	}

	/** One atomic statement: no read-then-write gap for two clicks to fall into. */
	private function flip(int $id, string $now): void
	{
		$i = $id;
		$m = $now;
		$this->db->setQuery(
			$this->db->getQuery(true)
				->update($this->db->quoteName('#__mapaviajes_reactions'))
				->set($this->db->quoteName('active') . ' = 1 - ' . $this->db->quoteName('active'))
				->set($this->db->quoteName('changed') . ' = :m')
				->where($this->db->quoteName('id') . ' = :i')
				->bind(':m', $m)
				->bind(':i', $i, ParameterType::INTEGER)
		)->execute();
	}

	private function count(int $tripId, string $kind): int
	{
		$t = $tripId;
		$k = $kind;

		return (int) $this->db->setQuery(
			$this->db->getQuery(true)
				->select('COUNT(*)')
				->from($this->db->quoteName('#__mapaviajes_reactions'))
				->where($this->db->quoteName('trip_id') . ' = :t')
				->where($this->db->quoteName('kind') . ' = :k')
				->where($this->db->quoteName('active') . ' = 1')
				->bind(':t', $t, ParameterType::INTEGER)
				->bind(':k', $k)
		)->loadResult();
	}

	private function newToday(int $userId): int
	{
		$u     = $userId;
		$since = Factory::getDate('-1 day')->toSql();

		return (int) $this->db->setQuery(
			$this->db->getQuery(true)
				->select('COUNT(*)')
				->from($this->db->quoteName('#__mapaviajes_reactions'))
				->where($this->db->quoteName('user_id') . ' = :u')
				->where($this->db->quoteName('created') . ' >= :s')
				->bind(':u', $u, ParameterType::INTEGER)
				->bind(':s', $since)
		)->loadResult();
	}
}
