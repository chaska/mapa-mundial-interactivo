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

/**
 * Who took part: the people with at least $min published trips WITH photos in a period (blocked accounts never count).
 * One line per person, however many trips. The draw uses the operating system's random source and is a pure function of
 * the list it is given. Personal data (name, e-mail) is for the moderators who run the draw and is never logged.
 */
final class RaffleService
{
	public function __construct(private DatabaseInterface $db)
	{
	}

	/** @return array{from: string, to: string, min: int, album: int} the filters, cleaned */
	public static function filters(array $in): array
	{
		$date = static function ($v): string {
			$v = \is_string($v) ? trim($v) : '';

			return (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1 && strtotime($v . ' UTC') !== false) ? $v : '';
		};
		$int = static fn ($v): int => (\is_string($v) && preg_match('/^\d{1,9}$/', $v) === 1) ? (int) $v : 0;

		return ['from' => $date($in['from'] ?? ''), 'to' => $date($in['to'] ?? ''), 'min' => max(1, min(1000, $int($in['min'] ?? '1') ?: 1)), 'album' => $int($in['album'] ?? '')];
	}

	/**
	 * @param  array{from: string, to: string, min: int, album: int}  $f
	 * @return list<array<string, mixed>>
	 */
	public function participants(array $f): array
	{
		$q = $this->db->getQuery(true)
			->select([
				$this->db->quoteName('u.id'), $this->db->quoteName('u.username'), $this->db->quoteName('u.name'), $this->db->quoteName('u.email'),
				'COUNT(DISTINCT t.id) AS trips', 'SUM(t.photo_count) AS photos', 'MIN(t.created) AS first_trip', 'MAX(t.created) AS last_trip',
			])
			->from($this->db->quoteName('#__mapaviajes_trips', 't'))
			->join('INNER', $this->db->quoteName('#__users', 'u') . ' ON u.id = t.user_id')
			->where($this->db->quoteName('t.published') . ' = 1')
			->where($this->db->quoteName('t.photo_count') . ' > 0')
			->where($this->db->quoteName('u.block') . ' = 0')
			->group([$this->db->quoteName('u.id'), $this->db->quoteName('u.username'), $this->db->quoteName('u.name'), $this->db->quoteName('u.email')]);

		if ($f['from'] !== '')
		{
			$q->where($this->db->quoteName('t.created') . ' >= ' . $this->db->quote($f['from'] . ' 00:00:00'));
		}

		if ($f['to'] !== '')
		{
			$q->where($this->db->quoteName('t.created') . ' < ' . $this->db->quote(gmdate('Y-m-d', strtotime($f['to'] . ' UTC') + 86400) . ' 00:00:00'));
		}

		if ($f['album'] > 0)
		{
			$q->where($this->db->quoteName('t.album_id') . ' = ' . (int) $f['album']);
		}

		$q->having('COUNT(DISTINCT t.id) >= ' . (int) $f['min'])->order('COUNT(DISTINCT t.id) DESC, MIN(t.created) ASC, u.id ASC');
		$rows = $this->db->setQuery($q)->loadAssocList() ?: [];
		$pts  = $this->podiumPoints(array_map(static fn (array $r): int => (int) $r['id'], $rows));

		foreach ($rows as &$r)
		{
			$r['points'] = $pts[(int) $r['id']] ?? 0;
		}

		unset($r);

		return $rows;
	}

	/** @param list<int> $ids  @return array<int, int> */
	private function podiumPoints(array $ids): array
	{
		if ($ids === [])
		{
			return [];
		}

		$q = $this->db->getQuery(true)->select([$this->db->quoteName('user_id'), 'SUM(points) AS p'])->from($this->db->quoteName('#__mapaviajes_ranking'))->whereIn($this->db->quoteName('user_id'), array_map('intval', $ids))->group($this->db->quoteName('user_id'));
		$out = [];

		foreach ($this->db->setQuery($q)->loadAssocList() ?: [] as $r)
		{
			$out[(int) $r['user_id']] = (int) $r['p'];
		}

		return $out;
	}

	/**
	 * @param  list<array<string, mixed>>  $people
	 * @return list<array<string, mixed>>  $n different people, drawn uniformly (partial Fisher-Yates with random_int)
	 */
	public static function draw(array $people, int $n): array
	{
		$n = max(0, min($n, \count($people)));

		for ($i = 0; $i < $n; $i++)
		{
			$j = random_int($i, \count($people) - 1);
			[$people[$i], $people[$j]] = [$people[$j], $people[$i]];
		}

		return \array_slice($people, 0, $n);
	}

	/** A spreadsheet cell that can never be read as a formula (CSV injection), quoted for CSV. */
	public static function cell(string $value): string
	{
		$value = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);

		if ($value !== '' && strpbrk($value[0], "=+-@\t\r") !== false)
		{
			$value = "'" . $value;
		}

		return '"' . str_replace('"', '""', $value) . '"';
	}

	/** @param list<array<string, mixed>> $people */
	public static function csv(array $people): string
	{
		$out = "\xEF\xBB\xBF" . implode(',', array_map([self::class, 'cell'], ['id', 'username', 'name', 'email', 'trips', 'photos', 'first_trip', 'last_trip', 'podium_points'])) . "\r\n";

		foreach ($people as $p)
		{
			$out .= implode(',', array_map([self::class, 'cell'], [(string) $p['id'], (string) $p['username'], (string) $p['name'], (string) $p['email'], (string) $p['trips'], (string) $p['photos'], (string) $p['first_trip'], (string) $p['last_trip'], (string) $p['points']])) . "\r\n";
		}

		return $out;
	}
}
