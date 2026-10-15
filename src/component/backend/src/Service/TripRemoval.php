<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\Service;

defined('_JEXEC') || die;

use Chaska\Component\Mapaviajes\Administrator\Security\UserLock;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Deleting a trip for good, from the map: its author may delete THEIR OWN trip (a wrong pin they cannot otherwise get rid of), and people with
 * the "delete" permission of the component (administrators) may delete any. Rows go first (one transaction), then the photo files,
 * then the comment thread, so nothing is left orphaned and a failure never leaves a half-deleted trip.
 */
final class TripRemoval
{
	public function __construct(private readonly DatabaseInterface $db, private readonly Settings $settings)
	{
	}

	/** @throws SubmissionException 404 when it does not exist or is not yours (same answer), 403 for guests */
	public function remove(User $user, int $tripId): void
	{
		$userId = (int) $user->id;

		if ($userId <= 0)
		{
			throw new SubmissionException('login', 403);
		}

		$db = $this->db;
		$id = $tripId;
		$trip = $db->setQuery($db->getQuery(true)->select($db->quoteName(['id', 'user_id', 'asset_id']))->from($db->quoteName('#__mapaviajes_trips'))->where($db->quoteName('id') . ' = :id')->bind(':id', $id, ParameterType::INTEGER))->loadAssoc();

		if (!\is_array($trip) || ((int) $trip['user_id'] !== $userId && !$user->authorise('core.delete', 'com_mapaviajes')))
		{
			throw new SubmissionException('trip', 404);
		}

		$names = $db->setQuery($db->getQuery(true)->select($db->quoteName('filename'))->from($db->quoteName('#__mapaviajes_photos'))->where($db->quoteName('trip_id') . ' = :t')->bind(':t', $id, ParameterType::INTEGER))->loadColumn() ?: [];

		$db->transactionStart();

		try
		{
			UserLock::take($db, $userId);

			foreach (['#__mapaviajes_photos' => 'trip_id', '#__mapaviajes_reactions' => 'trip_id', '#__mapaviajes_reports' => 'trip_id', '#__mapaviajes_trips' => 'id'] as $table => $column)
			{
				$db->setQuery($db->getQuery(true)->delete($db->quoteName($table))->where($db->quoteName($column) . ' = :x')->bind(':x', $id, ParameterType::INTEGER))->execute();
			}

			$db->transactionCommit();
		}
		catch (\Throwable $e)
		{
			$db->transactionRollback();

			throw $e;
		}

		EventLog::add($db, $userId, 'trip_removed', (int) $trip['user_id'] === $userId ? 'own' : 'by_admin');
		$storage = $this->settings->storage();

		foreach ($names as $name)
		{
			$storage->delete((string) $name);
		}

		(new CommentsBridge($db, $this->settings))->forget([(int) $trip['asset_id']]);
	}
}
