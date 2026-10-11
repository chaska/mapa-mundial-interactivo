<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\Service;

defined('_JEXEC') || die;

/**
 * Looks places up in our own file (media/data/places.tsv: GeoNames, CC BY 4.0). Nothing is sent to anybody.
 * The file is sorted by normalised name, so a prefix search is a binary search over byte offsets.
 * Columns: normalised name, name, ISO country, latitude, longitude, population.
 */
final class PlaceSearch
{
	public function __construct(private readonly string $file)
	{
	}

	/** Lowercase ASCII letters, digits and single spaces; whatever else is dropped. Same rule as tools/make-places.mjs. */
	public static function normalise(string $text): string
	{
		$text = mb_substr($text, 0, 80, 'UTF-8');

		if (class_exists(\Normalizer::class))
		{
			$text = \Normalizer::normalize($text, \Normalizer::FORM_D) ?: $text;
		}

		$text = preg_replace('/\p{Mn}+/u', '', $text) ?? '';
		$text = strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $text) ?? '');

		return trim($text);
	}

	/** @return list<array{n:string,c:string,la:float,lo:float}> */
	public function search(string $query, int $limit = 8): array
	{
		$key = self::normalise($query);

		if (strlen($key) < 2 || !is_file($this->file) || !($fh = @fopen($this->file, 'rb')))
		{
			return [];
		}

		try
		{
			$size = (int) filesize($this->file);
			$lo   = 0;
			$hi   = $size;

			// First line whose key is >= the query.
			while ($lo < $hi)
			{
				$mid = intdiv($lo + $hi, 2);
				fseek($fh, $mid);

				if ($mid > 0)
				{
					fgets($fh);
				}

				$line = fgets($fh);

				if ($line === false || strcmp((string) strstr($line, "\t", true), $key) >= 0)
				{
					$hi = $mid;
				}
				else
				{
					$lo = $mid + 1;
				}
			}

			fseek($fh, $lo);

			if ($lo > 0)
			{
				fgets($fh);
			}

			$found = [];
			$seen  = 0;

			while (($line = fgets($fh)) !== false && $seen++ < 3000)
			{
				$f = explode("\t", rtrim($line, "\r\n"));

				if (\count($f) !== 6)
				{
					continue;
				}

				if (!str_starts_with($f[0], $key))
				{
					break;
				}

				$found[] = [$f, (int) $f[5]];
			}
		}
		finally
		{
			fclose($fh);
		}

		// Exact name first, then the most populated.
		usort($found, static fn (array $a, array $b): int => [$a[0][0] === $key ? 0 : 1, $b[1]] <=> [$b[0][0] === $key ? 0 : 1, $a[1]]);

		$out = [];

		foreach ($found as [$f])
		{
			$out[] = ['n' => $f[1], 'c' => $f[2], 'la' => (float) $f[3], 'lo' => (float) $f[4]];

			if (\count($out) >= $limit)
			{
				break;
			}
		}

		return $out;
	}

	/** Closest place within $maxKm of a point, or null. */
	public function nearest(float $lat, float $lon, float $maxKm = 120.0): ?array
	{
		if (!is_file($this->file) || !($fh = @fopen($this->file, 'rb')))
		{
			return null;
		}

		$best = null;
		$bd   = $maxKm;
		$cos  = max(0.05, cos(deg2rad($lat)));

		try
		{
			while (($line = fgets($fh)) !== false)
			{
				$f = explode("\t", $line, 6);

				if (\count($f) !== 6)
				{
					continue;
				}

				$dy = abs((float) $f[3] - $lat);

				if ($dy > $bd / 111.0)
				{
					continue;
				}

				$dx = abs((float) $f[4] - $lon);
				$dx = min($dx, 360 - $dx) * $cos;
				$d  = 111.0 * sqrt($dx * $dx + $dy * $dy);

				if ($d < $bd)
				{
					$bd   = $d;
					$best = ['n' => $f[1], 'c' => $f[2], 'la' => (float) $f[3], 'lo' => (float) $f[4]];
				}
			}
		}
		finally
		{
			fclose($fh);
		}

		return $best;
	}
}
