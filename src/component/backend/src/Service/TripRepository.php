<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\Service;

defined('_JEXEC') || die;

use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/** Read-side queries. Every query is parameterised; visibility rules live here and nowhere else. */
final class TripRepository
{
	public function __construct(private DatabaseInterface $db)
	{
	}

	/** @return list<array<string, mixed>> */
	public function albumsFor(User $user): array
	{
		$levels = array_map('intval', $user->getAuthorisedViewLevels());
		$query  = $this->db->getQuery(true)
			->select($this->db->quoteName(['id', 'title', 'description', 'allow_comments', 'access']))
			->from($this->db->quoteName('#__mapaviajes_albums'))
			->where($this->db->quoteName('published') . ' = 1')
			->order($this->db->quoteName('ordering') . ' ASC, ' . $this->db->quoteName('id') . ' ASC');
		$rows = $this->db->setQuery($query)->loadAssocList() ?: [];

		return array_values(array_filter($rows, static fn (array $r): bool => \in_array((int) $r['access'], $levels, true)));
	}

	/**
	 * Published trips of one album, newest first, plus the user's own pending ones.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function forMap(User $user, int $albumId, int $limit = 3000, bool $realNames = false): array
	{
		$uid   = (int) $user->id;
		$meBind = $uid; // Joomla's bind() takes a reference and may turn it into a string: never reuse a bound variable
		$limit = max(1, min(5000, $limit));

		$query = $this->db->getQuery(true)
			->select([
				$this->db->quoteName('t.id'), $this->db->quoteName('t.title'), $this->db->quoteName('t.place'),
				$this->db->quoteName('t.lat'), $this->db->quoteName('t.lon'), $this->db->quoteName('t.photo_count'),
				$this->db->quoteName('t.allow_comments'), $this->db->quoteName('t.published'), $this->db->quoteName('t.created'),
				$this->db->quoteName('t.user_id'), $this->db->quoteName($realNames ? 'u.name' : 'u.username', 'author'),
			])
			->from($this->db->quoteName('#__mapaviajes_trips', 't'))
			->join('LEFT', $this->db->quoteName('#__users', 'u') . ' ON ' . $this->db->quoteName('u.id') . ' = ' . $this->db->quoteName('t.user_id'))
			->where($this->db->quoteName('t.album_id') . ' = :a')
			->where('(' . $this->db->quoteName('t.published') . ' = 1 OR ' . $this->db->quoteName('t.user_id') . ' = :me)')
			->where($this->db->quoteName('t.published') . ' IN (0, 1)')
			->order($this->db->quoteName('t.created') . ' DESC')
			->bind(':a', $albumId, ParameterType::INTEGER)
			->bind(':me', $meBind, ParameterType::INTEGER)
			->setLimit($limit);
		$trips = $this->db->setQuery($query)->loadAssocList() ?: [];

		if ($trips === [])
		{
			return [];
		}

		$ids = array_map(static fn (array $t): int => (int) $t['id'], $trips);
		$q   = $this->db->getQuery(true)
			->select($this->db->quoteName(['id', 'trip_id', 'ordering', 'width', 'height', 'caption']))
			->from($this->db->quoteName('#__mapaviajes_photos'))
			->whereIn($this->db->quoteName('trip_id'), $ids)
			->order($this->db->quoteName('trip_id') . ', ' . $this->db->quoteName('ordering'));
		$photos = [];

		foreach ($this->db->setQuery($q)->loadAssocList() ?: [] as $p)
		{
			$photos[(int) $p['trip_id']][] = [
				'id' => (int) $p['id'], 'w' => (int) $p['width'], 'h' => (int) $p['height'], 'cap' => (string) ($p['caption'] ?? ''),
			];
		}

		// Reactions: counts for everyone, and which ones are mine (never who else reacted).
		$counts = [];
		$qc = $this->db->getQuery(true)
			->select([$this->db->quoteName('trip_id'), $this->db->quoteName('kind'), 'COUNT(*) AS ' . $this->db->quoteName('c')])
			->from($this->db->quoteName('#__mapaviajes_reactions'))
			->where($this->db->quoteName('active') . ' = 1')
			->whereIn($this->db->quoteName('trip_id'), $ids)
			->group($this->db->quoteName('trip_id') . ', ' . $this->db->quoteName('kind'));

		foreach ($this->db->setQuery($qc)->loadAssocList() ?: [] as $r)
		{
			$counts[(int) $r['trip_id']][(string) $r['kind']] = (int) $r['c'];
		}

		$mine   = [];
		$meBind2 = $uid;
		$qm = $this->db->getQuery(true)
			->select([$this->db->quoteName('trip_id'), $this->db->quoteName('kind')])
			->from($this->db->quoteName('#__mapaviajes_reactions'))
			->where($this->db->quoteName('user_id') . ' = :rme')
			->where($this->db->quoteName('active') . ' = 1')
			->whereIn($this->db->quoteName('trip_id'), $ids)
			->bind(':rme', $meBind2, ParameterType::INTEGER);

		foreach ($this->db->setQuery($qm)->loadAssocList() ?: [] as $r)
		{
			$mine[(int) $r['trip_id']][(string) $r['kind']] = true;
		}

		$out = [];

		foreach ($trips as $t)
		{
			$id    = (int) $t['id'];
			$out[] = [
				'id' => $id, 't' => (string) $t['title'], 'p' => (string) $t['place'],
				'la' => (float) $t['lat'], 'lo' => (float) $t['lon'],
				'by' => (string) ($t['author'] ?? ''), 'u' => Settings::userKey((int) $t['user_id']), 'mine' => (int) $t['user_id'] === $uid,
				'pending' => (int) $t['published'] === 0, 'cm' => (int) $t['allow_comments'] === 1,
				'ts' => strtotime((string) $t['created'] . ' UTC') ?: 0,
				'lk' => $counts[$id]['like'] ?? 0, 'fn' => $counts[$id]['fan'] ?? 0,
				'ml' => !empty($mine[$id]['like']), 'mf' => !empty($mine[$id]['fan']),
				'ph' => $photos[$id] ?? [],
			];
		}

		return $out;
	}

	/**
	 * What a photo request needs to decide: the file name and the state of its trip and album.
	 *
	 * @return array{filename: string, trip_published: int, trip_user: int, album_published: int, album_access: int}|null
	 */
	public function photoGate(int $photoId): ?array
	{
		if ($photoId <= 0)
		{
			return null;
		}

		$query = $this->db->getQuery(true)
			->select([
				$this->db->quoteName('p.filename'), $this->db->quoteName('t.published', 'trip_published'), $this->db->quoteName('t.user_id', 'trip_user'),
				$this->db->quoteName('a.published', 'album_published'), $this->db->quoteName('a.access', 'album_access'),
			])
			->from($this->db->quoteName('#__mapaviajes_photos', 'p'))
			->join('INNER', $this->db->quoteName('#__mapaviajes_trips', 't') . ' ON ' . $this->db->quoteName('t.id') . ' = ' . $this->db->quoteName('p.trip_id'))
			->join('INNER', $this->db->quoteName('#__mapaviajes_albums', 'a') . ' ON ' . $this->db->quoteName('a.id') . ' = ' . $this->db->quoteName('t.album_id'))
			->where($this->db->quoteName('p.id') . ' = :id')
			->bind(':id', $photoId, ParameterType::INTEGER);
		$row = $this->db->setQuery($query)->loadAssoc();

		if (!$row)
		{
			return null;
		}

		return [
			'filename' => (string) $row['filename'], 'trip_published' => (int) $row['trip_published'], 'trip_user' => (int) $row['trip_user'],
			'album_published' => (int) $row['album_published'], 'album_access' => (int) $row['album_access'],
		];
	}

	/**
	 * One trip with what a comment system needs to know about it, whoever is asking. Visibility is decided by the caller
	 * with commentsVisible(): this method never filters by user.
	 *
	 * @return array<string, mixed>|null
	 */
	public function forComments(int $tripId = 0, int $assetId = 0): ?array
	{
		if ($tripId <= 0 && $assetId <= 0)
		{
			return null;
		}

		$t = $tripId;
		$s = $assetId;
		$q = $this->db->getQuery(true)
			->select([
				$this->db->quoteName('t.id'), $this->db->quoteName('t.title'), $this->db->quoteName('t.place'), $this->db->quoteName('t.user_id'),
				$this->db->quoteName('t.published'), $this->db->quoteName('t.allow_comments'), $this->db->quoteName('t.album_id'), $this->db->quoteName('t.created'),
				$this->db->quoteName('t.asset_id'), $this->db->quoteName('a.title', 'album_title'), $this->db->quoteName('a.access', 'album_access'),
				$this->db->quoteName('a.published', 'album_published'), $this->db->quoteName('a.allow_comments', 'album_comments'),
				$this->db->quoteName('u.username'), $this->db->quoteName('u.name'),
			])
			->from($this->db->quoteName('#__mapaviajes_trips', 't'))
			->join('INNER', $this->db->quoteName('#__mapaviajes_albums', 'a') . ' ON ' . $this->db->quoteName('a.id') . ' = ' . $this->db->quoteName('t.album_id'))
			->join('LEFT', $this->db->quoteName('#__users', 'u') . ' ON ' . $this->db->quoteName('u.id') . ' = ' . $this->db->quoteName('t.user_id'));

		if ($tripId > 0)
		{
			$q->where($this->db->quoteName('t.id') . ' = :t')->bind(':t', $t, ParameterType::INTEGER);
		}
		else
		{
			$q->where($this->db->quoteName('t.asset_id') . ' = :s')->bind(':s', $s, ParameterType::INTEGER);
		}

		$row = $this->db->setQuery($q)->loadAssoc();

		return $row ?: null;
	}

	/** Published trip in a published album the user may see (the same rule as the map and the reactions). */
	public function commentsVisible(User $user, array $row): bool
	{
		return (int) $row['published'] === 1 && (int) $row['album_published'] === 1
			&& \in_array((int) $row['album_access'], array_map('intval', $user->getAuthorisedViewLevels()), true);
	}

	/** Comments are possible on this trip: the album and the trip both allow them. */
	public static function commentsAllowed(array $row): bool
	{
		return (int) $row['allow_comments'] === 1 && (int) $row['album_comments'] === 1;
	}
}
