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
 * "How many countries?" on the server, with the same answer the map gives in its header: each place is projected exactly like the
 * map does and looked up in the outlines of media/data/world.json; a place that falls in no country (the sea) counts as one
 * "country" per 25-unit cell of the drawing, as the map does. Read-only, no database, nothing leaves the server.
 */
final class WorldCountries
{
	/** @var list<array{n: string, box: array{0: float, 1: float, 2: float, 3: float}, rings: list<list<array{0: float, 1: float}>>}>|null */
	private ?array $countries = null;

	/** @var array{scale: float, t: array{0: float, 1: float}}|null */
	private ?array $frame = null;

	public function __construct(private readonly string $file)
	{
	}

	/**
	 * @param   list<array{0: float, 1: float}>  $places  [latitude, longitude] of each album
	 *
	 * @return  int  the number of different countries (or sea cells)
	 */
	public function count(array $places): int
	{
		if (!$this->load())
		{
			return 0;
		}

		$seen = [];

		foreach ($places as [$lat, $lon])
		{
			$seen[$this->keyOf((float) $lat, (float) $lon)] = true;
		}

		return \count($seen);
	}

	private function keyOf(float $lat, float $lon): string
	{
		[$x, $y] = $this->project($lon, $lat);

		foreach ($this->countries ?? [] as $c)
		{
			if ($x < $c['box'][0] || $x > $c['box'][2] || $y < $c['box'][1] || $y > $c['box'][3])
			{
				continue;
			}

			$inside = false;

			foreach ($c['rings'] as $ring)
			{
				$n = \count($ring);

				for ($i = 0, $j = $n - 1; $i < $n; $j = $i++)
				{
					if ((($ring[$i][1] > $y) !== ($ring[$j][1] > $y)) && ($x < ($ring[$j][0] - $ring[$i][0]) * ($y - $ring[$i][1]) / ($ring[$j][1] - $ring[$i][1]) + $ring[$i][0]))
					{
						$inside = !$inside;
					}
				}
			}

			if ($inside)
			{
				return 'c:' . $c['n'];
			}
		}

		return 'q:' . (int) round($x / 25) . ',' . (int) round($y / 25);
	}

	/** Natural Earth 1, the same formula and frame as the map (media/js/mapa.js). @return array{0: float, 1: float} */
	private function project(float $lon, float $lat): array
	{
		$l  = deg2rad($lon);
		$p  = deg2rad($lat);
		$p2 = $p * $p;
		$p4 = $p2 * $p2;
		$x  = $l * (0.8707 - 0.131979 * $p2 + $p4 * (-0.013791 + $p4 * (0.003971 * $p2 - 0.001529 * $p4)));
		$y  = $p * (1.007226 + $p2 * (0.015085 + $p4 * (-0.044475 + 0.028874 * $p2 - 0.005916 * $p4)));

		return [$this->frame['t'][0] + $this->frame['scale'] * $x, $this->frame['t'][1] - $this->frame['scale'] * $y];
	}

	private function load(): bool
	{
		if ($this->countries !== null)
		{
			return true;
		}

		$raw = is_file($this->file) ? @file_get_contents($this->file) : false;
		$w   = \is_string($raw) ? json_decode($raw, true) : null;

		if (!\is_array($w) || !isset($w['countries'], $w['scale'], $w['t'][0], $w['t'][1]))
		{
			return false;
		}

		$this->frame     = ['scale' => (float) $w['scale'], 't' => [(float) $w['t'][0], (float) $w['t'][1]]];
		$this->countries = [];

		foreach ($w['countries'] as $c)
		{
			$rings = [];
			$box   = [INF, INF, -INF, -INF];

			foreach (explode('M', (string) ($c['d'] ?? '')) as $sub)
			{
				if ($sub === '' || preg_match_all('/(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $sub, $m, PREG_SET_ORDER) < 3)
				{
					continue;
				}

				$ring = [];

				foreach ($m as $pt)
				{
					$px     = (float) $pt[1];
					$py     = (float) $pt[2];
					$ring[] = [$px, $py];
					$box    = [min($box[0], $px), min($box[1], $py), max($box[2], $px), max($box[3], $py)];
				}

				$rings[] = $ring;
			}

			if ($rings !== [])
			{
				$this->countries[] = ['n' => (string) ($c['n'] ?? ''), 'box' => $box, 'rings' => $rings];
			}
		}

		return true;
	}
}
