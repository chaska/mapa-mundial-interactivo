<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\Service;

defined('_JEXEC') || die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;

/** The handful of options the control panel switches on and off by itself. Only these names, only these values. */
final class AdminSettings
{
	/** name => list of accepted values (strings) */
	public const FIELDS = [
		'review_first'      => ['0', '1'],
		'comments_default'  => ['0', '1'],
		'show_real_names'   => ['0', '1'],
		'profile_photos'    => ['0', '1'],
		'notify_admin'      => ['0', '1'],
		'notify_author'     => ['0', '1'],
		'rank_enabled'      => ['0', '1'],
		'notify_podium'     => ['0', '1'],
		'comments_provider' => ['none', 'engage', 'events'],
		'map_user_choice'   => ['0', '1'],
		'map_style'         => [], // any look that exists in media/com_mapaviajes/css/maps (see MapStyles)
	];

	public static function accepts(string $name, string $value): bool
	{
		if ($name === 'map_style')
		{
			return MapStyles::valid($value);
		}

		return isset(self::FIELDS[$name]) && \in_array($value, self::FIELDS[$name], true);
	}

	/** Writes one option into the component parameters (the same place the Options page writes to). */
	public static function save(string $name, string $value): bool
	{
		if (!self::accepts($name, $value))
		{
			return false;
		}

		$params = clone ComponentHelper::getParams('com_mapaviajes');
		$params->set($name, ctype_digit($value) ? (int) $value : $value);
		$json = (string) $params;
		$db   = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
		$q    = $db->getQuery(true)->update($db->quoteName('#__extensions'))->set($db->quoteName('params') . ' = :p')
			->where($db->quoteName('element') . ' = ' . $db->quote('com_mapaviajes'))->where($db->quoteName('type') . ' = ' . $db->quote('component'))->bind(':p', $json);
		$db->setQuery($q)->execute();

		// Joomla keeps the component parameters in its cache
		try
		{
			Factory::getContainer()->get(\Joomla\CMS\Cache\CacheControllerFactoryInterface::class)->createCacheController('callback', ['defaultgroup' => '_system'])->clean();
		}
		catch (\Throwable $e)
		{
			// the next request reads the database anyway when the cache is off
		}

		return true;
	}
}
