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

/**
 * The author of a trip may open or close the comments of THAT trip at any time (they chose at the start, now they changed their mind);
 * administrators with the "edit state" permission may too. Closing keeps the comments already written (they come back if it is opened again).
 */
final class TripCommentsSwitch
{
	public function __construct(private readonly DatabaseInterface $db)
	{
	}

	/** @throws SubmissionException 404 when the trip does not exist or is not yours, 403 for guests */
	public function set(User $user, int $tripId, bool $on): void
	{
		$me = (int) $user->id;

		if ($me <= 0)
		{
			throw new SubmissionException('login', 403);
		}

		$db = $this->db;
		$id = $tripId;
		$owner = $db->setQuery($db->getQuery(true)->select($db->quoteName('user_id'))->from($db->quoteName('#__mapaviajes_trips'))->where($db->quoteName('id') . ' = :id')->bind(':id', $id, ParameterType::INTEGER))->loadResult();

		if ($owner === null || ((int) $owner !== $me && !$user->authorise('core.edit.state', 'com_mapaviajes')))
		{
			throw new SubmissionException('trip', 404);
		}

		$value = $on ? 1 : 0;
		$id2   = $tripId;
		$db->setQuery($db->getQuery(true)->update($db->quoteName('#__mapaviajes_trips'))->set($db->quoteName('allow_comments') . ' = :v')->where($db->quoteName('id') . ' = :id')->bind(':v', $value, ParameterType::INTEGER)->bind(':id', $id2, ParameterType::INTEGER))->execute();
		EventLog::add($db, $me, $on ? 'comments_on' : 'comments_off', (int) $owner === $me ? 'own' : 'by_admin');
	}
}
