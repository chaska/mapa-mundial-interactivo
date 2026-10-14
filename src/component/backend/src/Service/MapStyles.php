<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\Service;

defined('_JEXEC') || die;

use Joomla\CMS\Language\Text;

/**
 * The map looks. "pergamino" is built into mapa.css; every other look is ONE css file in media/com_mapaviajes/css/maps/<id>.css whose first
 * line says how it is listed:  / * mapa: Nombre; agua: #aabbcc; tierra: #ddeeff * /  (water and land colours are the little round sample).
 * Dropping a new file in that folder adds a map style: nothing else to register. Names and ids are checked, never trusted.
 */
final class MapStyles
{
	public const DEFAULT = 'pergamino';

	private const ID = '/^[a-z][a-z0-9_]{0,23}$/';

	/** @var array<string, array{label: string, water: string, land: string}>|null */
	private static ?array $cache = null;

	/** @return array<string, array{label: string, water: string, land: string}> id => details, the default look first */
	public static function all(): array
	{
		if (self::$cache !== null)
		{
			return self::$cache;
		}

		$list = [self::DEFAULT => ['label' => Text::_('COM_MAPAVIAJES_MAPSTYLE_PERGAMINO'), 'water' => '#d9c59b', 'land' => '#e4b86e']];
		$dir  = self::dir();
		$files = is_dir($dir) ? (glob($dir . '/*.css') ?: []) : [];
		sort($files);

		foreach ($files as $file)
		{
			$id = basename($file, '.css');

			if (preg_match(self::ID, $id) !== 1 || $id === self::DEFAULT || !is_file($file) || is_link($file))
			{
				continue;
			}

			$head = (string) file_get_contents($file, false, null, 0, 300);
			$name = $id;
			$water = '#9fb6c8';
			$land  = '#eeeeee';

			if (preg_match('/^\s*\/\*\s*mapa:\s*([^;*]{1,40})/u', $head, $m) === 1)
			{
				$name = trim(strip_tags($m[1]));
			}

			if (preg_match('/agua:\s*(#[0-9a-fA-F]{6})/', $head, $m) === 1)
			{
				$water = strtolower($m[1]);
			}

			if (preg_match('/tierra:\s*(#[0-9a-fA-F]{6})/', $head, $m) === 1)
			{
				$land = strtolower($m[1]);
			}

			$list[$id] = ['label' => $name !== '' ? $name : $id, 'water' => $water, 'land' => $land];
		}

		return self::$cache = $list;
	}

	public static function valid(string $id): bool
	{
		return preg_match(self::ID, $id) === 1 && isset(self::all()[$id]);
	}

	private static function dir(): string
	{
		return JPATH_ROOT . '/media/com_mapaviajes/css/maps';
	}
}
