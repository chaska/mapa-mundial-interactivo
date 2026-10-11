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
	public function forMap(User $user, int $albumId, int $limit = 3000): array
	{
		$uid   = (int) $user->id;
		$meBind = $uid; // Joomla's bind() takes a reference and may turn it into a string: never reuse a bound variable
		$limit = max(1, min(5000, $limit));

		$query = $this->db->getQuery(true)
			->select([
				$this->db->quoteName('t.id'), $this->db->quoteName('t.title'), $this->db->quoteName('t.place'),
				$this->db->quoteName('t.lat'), $this->db->quoteName('t.lon'), $this->db->quoteName('t.photo_count'),
				$this->db->quoteName('t.allow_comments'), $this->db->quoteName('t.published'), $this->db->quoteName('t.created'),
				$this->db->quoteName('t.user_id'), $this->db->quoteName('u.name', 'author'),
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

		$out = [];

		foreach ($trips as $t)
		{
			$id    = (int) $t['id'];
			$out[] = [
				'id' => $id, 't' => (string) $t['title'], 'p' => (string) $t['place'],
				'la' => (float) $t['lat'], 'lo' => (float) $t['lon'],
				'by' => (string) ($t['author'] ?? ''), 'mine' => (int) $t['user_id'] === $uid,
				'pending' => (int) $t['published'] === 0, 'cm' => (int) $t['allow_comments'] === 1,
				'ts' => strtotime((string) $t['created'] . ' UTC') ?: 0,
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
}
