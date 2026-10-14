<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\Security;

defined('_JEXEC') || die;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Serialises one person's requests: inside a transaction, the first caller takes a row lock on the user and the others wait their turn,
 * so "count what exists, then write" cannot be run by many parallel requests at once (rate limits stay real).
 */
final class UserLock
{
	/** Call inside an open transaction; the lock is released by commit or rollback. */
	public static function take(DatabaseInterface $db, int $userId): void
	{
		$id = $userId;
		$db->setQuery(
			$db->getQuery(true)->update($db->quoteName('#__users'))->set($db->quoteName('id') . ' = ' . $db->quoteName('id'))->where($db->quoteName('id') . ' = :lock')->bind(':lock', $id, ParameterType::INTEGER)
		)->execute();
	}
}
