<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\Service;

defined('_JEXEC') || die;

use Chaska\Component\Mapaviajes\Administrator\Security\TextSanitizer;
use Chaska\Component\Mapaviajes\Administrator\Security\UserLock;
use Chaska\Component\Mapaviajes\Administrator\Security\SanitizeException;
use Joomla\CMS\Factory;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * "Report this content": any signed-in person can tell the moderators that a trip is illegal or breaks the rules (notice-and-action).
 * One report per person and trip, a small hourly cap per person, and the moderators get an e-mail without links. A report never
 * hides anything by itself: a moderator looks at it and decides (the trips list has a "Reported" filter).
 */
final class ReportService
{
	public const REASONS = ['illegal', 'rights', 'privacy', 'minor', 'other'];

	private const HOURLY_CAP = 10;

	public function __construct(private readonly DatabaseInterface $db, private readonly Settings $settings)
	{
	}

	/** @throws SubmissionException 403 guest, 404 not a visible trip (or your own), 422 bad reason/note, 429 too many */
	public function add(User $user, int $tripId, string $reason, string $note): void
	{
		$userId = (int) $user->id;

		if ($userId <= 0)
		{
			throw new SubmissionException('login', 403);
		}

		if (!\in_array($reason, self::REASONS, true))
		{
			throw new SubmissionException('generic', 422);
		}

		try
		{
			$note = TextSanitizer::clean($note, 500, 0, true);
		}
		catch (SanitizeException $e)
		{
			throw new SubmissionException($e->getReason(), 422);
		}

		if ($reason === 'other' && $note === '')
		{
			throw new SubmissionException('report_note', 422);
		}

		$db   = $this->db;
		$id   = $tripId;
		$trip = $db->setQuery($db->getQuery(true)->select($db->quoteName(['id', 'user_id', 'title', 'published']))->from($db->quoteName('#__mapaviajes_trips'))->where($db->quoteName('id') . ' = :id')->bind(':id', $id, ParameterType::INTEGER))->loadAssoc();

		if (!\is_array($trip) || (int) $trip['user_id'] === $userId || !\in_array((int) $trip['published'], [1, 2], true))
		{
			throw new SubmissionException('trip', 404);
		}

		$now = Factory::getDate()->toSql();
		$db->transactionStart();

		try
		{
			UserLock::take($db, $userId);

			$since = Factory::getDate('-1 hour')->toSql();
			$n     = (int) $db->setQuery($db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__mapaviajes_reports'))->where($db->quoteName('reporter_id') . ' = :u')->where($db->quoteName('created') . ' >= :s')->bind(':u', $userId, ParameterType::INTEGER)->bind(':s', $since))->loadResult();

			if ($n >= self::HOURLY_CAP)
			{
				throw new SubmissionException('rate', 429);
			}

			$again = (int) $db->setQuery($db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__mapaviajes_reports'))->where($db->quoteName('trip_id') . ' = :t')->where($db->quoteName('reporter_id') . ' = :u')->bind(':t', $id, ParameterType::INTEGER)->bind(':u', $userId, ParameterType::INTEGER))->loadResult();

			if ($again === 0)
			{
				$db->setQuery($db->getQuery(true)->insert($db->quoteName('#__mapaviajes_reports'))->columns($db->quoteName(['trip_id', 'reporter_id', 'reason', 'note', 'created']))->values(':t, :u, :r, :n, :c')
					->bind(':t', $id, ParameterType::INTEGER)->bind(':u', $userId, ParameterType::INTEGER)->bind(':r', $reason)->bind(':n', $note)->bind(':c', $now))->execute();
			}

			$db->transactionCommit();
		}
		catch (\Throwable $e)
		{
			$db->transactionRollback();

			throw $e;
		}

		if ($again === 0)
		{
			// bind() may have turned the referenced variables into strings
			EventLog::add($db, (int) $user->id, 'report', $reason);
			$this->tell($trip, $reason);
		}
	}

	/** Moderators: a report is handled (it was fine, or it was removed). */
	public static function dismiss(DatabaseInterface $db, array $tripIds): void
	{
		$ids = array_values(array_filter(array_map('intval', $tripIds)));

		if ($ids !== [])
		{
			$db->setQuery($db->getQuery(true)->delete($db->quoteName('#__mapaviajes_reports'))->whereIn($db->quoteName('trip_id'), $ids))->execute();
		}
	}

	/** @param array<string, mixed> $trip */
	private function tell(array $trip, string $reason): void
	{
		if (!$this->settings->notifyAdmin())
		{
			return;
		}

		$to = $this->settings->notifyEmails();

		if ($to === [])
		{
			$mailfrom = (string) Factory::getApplication()->get('mailfrom', '');
			$to       = filter_var($mailfrom, FILTER_VALIDATE_EMAIL) !== false ? [$mailfrom] : [];
		}

		if ($to === [])
		{
			return;
		}

		(new Notifier($this->db, $this->settings))->reported(array_values($to), (int) $trip['id'], (string) $trip['title'], $reason);
	}
}
