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
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Security journal: WHO (user id) and WHY (a short code). Never the content that was refused, never an IP.
 */
final class EventLog
{
	public static function add(DatabaseInterface $db, int $userId, string $event, string $detail = ''): void
	{
		try
		{
			$event  = substr(preg_replace('/[^a-z0-9_.:-]/i', '', $event) ?? '', 0, 40);
			$detail = substr(preg_replace('/[^a-z0-9_.:-]/i', '', $detail) ?? '', 0, 190);
			$now    = Factory::getDate()->toSql();

			$query = $db->getQuery(true)
				->insert($db->quoteName('#__mapaviajes_events'))
				->columns($db->quoteName(['user_id', 'event', 'detail', 'created']))
				->values(':u, :e, :d, :c')
				->bind(':u', $userId, ParameterType::INTEGER)
				->bind(':e', $event)
				->bind(':d', $detail)
				->bind(':c', $now);
			$db->setQuery($query)->execute();

			self::prune($db);
		}
		catch (\Throwable $e)
		{
			// The journal must never break the request.
		}
	}

	/** Housekeeping: keep ninety days, checked now and then. */
	public static function prune(DatabaseInterface $db): void
	{
		try
		{
			if (random_int(1, 40) === 1)
			{
				$limit = Factory::getDate('-90 days')->toSql();
				$db->setQuery($db->getQuery(true)->delete($db->quoteName('#__mapaviajes_events'))->where($db->quoteName('created') . ' < :l')->bind(':l', $limit))->execute();
			}
		}
		catch (\Throwable $e)
		{
			// never breaks the request
		}
	}
}
