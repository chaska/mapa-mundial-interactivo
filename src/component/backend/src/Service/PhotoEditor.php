<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\Service;

defined('_JEXEC') || die;

use Chaska\Component\Mapaviajes\Administrator\Security\ImageProcessor;
use Chaska\Component\Mapaviajes\Administrator\Security\SanitizeException;
use Chaska\Component\Mapaviajes\Administrator\Security\TextSanitizer;
use Chaska\Component\Mapaviajes\Administrator\Security\UserLock;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Editing the photos of a trip that is already on the map: add photos until the album has the maximum, mark a photo "friends
 * only" or public again, delete a photo. Only the AUTHOR may do it, and the trip must always keep at least one photo and at
 * least one live public photo (the thumbnail everybody sees). Photos added to a trip that is already published wait for a
 * moderator when the site reviews first, so editing is never a way around the review; until then only the author and the
 * moderators see them. Captions of photos that are already there cannot be changed (that would skip the review of the text).
 */
final class PhotoEditor
{
	public const ADD_PER_HOUR = 15;

	public function __construct(private readonly DatabaseInterface $db, private readonly Settings $settings, private ?ImageProcessor $processor = null)
	{
		$this->processor ??= new ImageProcessor($settings->maxUploadBytes(), $settings->longSide(), $settings->quality());
	}

	/**
	 * @param   array<int, array<string, mixed>>  $files     flattened uploads, in the order sent
	 * @param   array<int, mixed>                 $captions  one per file
	 * @param   array<int, mixed>                 $priv      "1" = friends only, one per file
	 *
	 * @return  array{added: int, pending: bool}
	 * @throws  SubmissionException
	 */
	public function add(User $user, int $tripId, array $files, array $captions, array $priv): array
	{
		$userId = (int) $user->id;
		$trip   = $this->ownTrip($userId, $tripId);
		$max    = $this->settings->maxPhotos();
		$n      = \count($files);

		if ($n < 1)
		{
			throw new SubmissionException('photos_required', 422);
		}

		$this->reserve($userId);

		if ($this->countPhotos((int) $trip['id']) + $n > $max)
		{
			throw new SubmissionException('photos_too_many', 422, ['max' => $max]);
		}

		$clean = [];
		$flags = [];

		foreach (array_values($files) as $i => $unused)
		{
			try
			{
				$raw = $captions[$i] ?? '';
				$clean[$i] = TextSanitizer::clean(\is_string($raw) ? $raw : '', 2000, $this->settings->maxWords(), true);
			}
			catch (SanitizeException $e)
			{
				EventLog::add($this->db, $userId, 'reject_text', 'caption:' . $e->getReason());
				throw new SubmissionException($e->getReason(), 422, ['photo' => $i + 1]);
			}

			$flags[$i] = (($priv[$i] ?? '0') === '1') ? 1 : 0;
		}

		$storage = $this->settings->storage();

		try
		{
			$storage->prepare();
			$storage->sweep();
		}
		catch (\Throwable $e)
		{
			throw new SubmissionException('server', 500);
		}

		$logo        = $this->albumLogo((int) $trip['album_id']);
		$watermark   = $logo !== '' ? $storage->pathFor($logo) : null;
		$temporaries = [];
		$meta        = [];

		try
		{
			foreach (array_values($files) as $i => $file)
			{
				$tmp = $storage->newTemporaryPath();

				try
				{
					$meta[$i] = $this->processor->process($file, $tmp, $watermark);
				}
				catch (SanitizeException $e)
				{
					EventLog::add($this->db, $userId, 'reject_image', 'photo' . ($i + 1) . ':' . $e->getReason());
					throw new SubmissionException($e->getReason(), 422, ['photo' => $i + 1]);
				}

				$temporaries[$i] = $tmp;
			}
		}
		catch (\Throwable $e)
		{
			foreach ($temporaries as $tmp)
			{
				$storage->discard($tmp);
			}

			throw $e;
		}

		$names = [];
		$year  = (int) $trip['trip_year'];
		$live  = (int) $trip['published'] === 1 && $this->settings->reviewFirst() && !$user->authorise('core.admin');
		$state = $live ? 0 : 1; // already on the map under review: new photos wait; a trip still under review is reviewed as a whole

		try
		{
			$base = $this->countPhotos((int) $trip['id']);

			foreach ($temporaries as $i => $tmp)
			{
				$names[$i] = $storage->commit($tmp, $userId, $year, $base + $i + 1);
			}

			$this->insertRows((int) $trip['id'], $userId, $max, $names, $meta, $clean, $flags, $state);
		}
		catch (\Throwable $e)
		{
			foreach ($names as $name)
			{
				$storage->delete($name);
			}

			foreach ($temporaries as $tmp)
			{
				$storage->discard($tmp);
			}

			if ($e instanceof SubmissionException)
			{
				throw $e;
			}

			Log::add('mapaviajes photo add failed: ' . \get_class($e) . ': ' . $e->getMessage(), Log::ERROR, 'com_mapaviajes');

			throw new SubmissionException('server', 500);
		}

		if ($state === 0)
		{
			$this->tellModerators((string) $trip['title'], (string) $user->username, \count($names));
		}

		return ['added' => \count($names), 'pending' => $state === 0];
	}

	/** @throws SubmissionException 404 not yours, 422 the trip would have no public photo */
	public function setPrivacy(User $user, int $photoId, bool $friendsOnly): void
	{
		$photo = $this->ownPhoto((int) $user->id, $photoId);

		if ($friendsOnly && !$this->otherPublicLive((int) $photo['trip_id'], (int) $photo['id']))
		{
			throw new SubmissionException('photo_public', 422);
		}

		$fo = $friendsOnly ? 1 : 0;
		$id = (int) $photo['id'];
		$this->db->setQuery($this->db->getQuery(true)->update($this->db->quoteName('#__mapaviajes_photos'))->set($this->db->quoteName('friends_only') . ' = :f')->where($this->db->quoteName('id') . ' = :i')
			->bind(':f', $fo, ParameterType::INTEGER)->bind(':i', $id, ParameterType::INTEGER))->execute();
		EventLog::add($this->db, (int) $user->id, 'photo_privacy', $friendsOnly ? 'friends' : 'public');
	}

	/** @throws SubmissionException 404 not yours, 422 it is the only photo or the only public one */
	public function remove(User $user, int $photoId): void
	{
		$photo = $this->ownPhoto((int) $user->id, $photoId);
		$trip  = (int) $photo['trip_id'];

		if ($this->countPhotos($trip) <= 1)
		{
			throw new SubmissionException('photo_last', 422);
		}

		if ((int) $photo['friends_only'] === 0 && (int) $photo['state'] === 1 && !$this->otherPublicLive($trip, (int) $photo['id']))
		{
			throw new SubmissionException('photo_public', 422);
		}

		$id = (int) $photo['id'];
		$this->db->setQuery($this->db->getQuery(true)->delete($this->db->quoteName('#__mapaviajes_photos'))->where($this->db->quoteName('id') . ' = :i')->bind(':i', $id, ParameterType::INTEGER))->execute();
		$this->settings->storage()->delete((string) $photo['filename']);
		self::recount($this->db, [$trip]);
		EventLog::add($this->db, (int) $user->id, 'photo_removed');
	}

	/** Moderators: the photos added later of these trips go live. */
	public static function approve(DatabaseInterface $db, array $tripIds): void
	{
		$ids = array_values(array_filter(array_map('intval', $tripIds)));

		if ($ids === [])
		{
			return;
		}

		$db->setQuery($db->getQuery(true)->update($db->quoteName('#__mapaviajes_photos'))->set($db->quoteName('state') . ' = 1')->where($db->quoteName('state') . ' = 0')->whereIn($db->quoteName('trip_id'), $ids))->execute();
		self::recount($db, $ids);
	}

	/** Moderators: the photos added later of these trips are refused: rows and files go. */
	public static function reject(DatabaseInterface $db, Settings $settings, array $tripIds): void
	{
		$ids = array_values(array_filter(array_map('intval', $tripIds)));

		if ($ids === [])
		{
			return;
		}

		$names = $db->setQuery($db->getQuery(true)->select($db->quoteName('filename'))->from($db->quoteName('#__mapaviajes_photos'))->where($db->quoteName('state') . ' = 0')->whereIn($db->quoteName('trip_id'), $ids))->loadColumn() ?: [];
		$db->setQuery($db->getQuery(true)->delete($db->quoteName('#__mapaviajes_photos'))->where($db->quoteName('state') . ' = 0')->whereIn($db->quoteName('trip_id'), $ids))->execute();

		foreach ($names as $name)
		{
			$settings->storage()->delete((string) $name);
		}

		self::recount($db, $ids);
	}

	/** photo_count of a trip = its live photos. @param list<int> $tripIds */
	public static function recount(DatabaseInterface $db, array $tripIds): void
	{
		foreach ($tripIds as $id)
		{
			$i = (int) $id;
			$j = (int) $id;
			$q = $db->getQuery(true)->update($db->quoteName('#__mapaviajes_trips'))
				->set($db->quoteName('photo_count') . ' = (SELECT COUNT(*) FROM ' . $db->quoteName('#__mapaviajes_photos') . ' WHERE ' . $db->quoteName('trip_id') . ' = :t AND ' . $db->quoteName('state') . ' = 1)')
				->where($db->quoteName('id') . ' = :i')->bind(':t', $j, ParameterType::INTEGER)->bind(':i', $i, ParameterType::INTEGER);
			$db->setQuery($q)->execute();
		}
	}

	/** @return array<string, mixed> */
	private function ownTrip(int $userId, int $tripId): array
	{
		if ($userId <= 0)
		{
			throw new SubmissionException('login', 403);
		}

		$t   = $tripId;
		$row = $this->db->setQuery($this->db->getQuery(true)->select($this->db->quoteName(['id', 'user_id', 'album_id', 'title', 'published', 'trip_year']))->from($this->db->quoteName('#__mapaviajes_trips'))->where($this->db->quoteName('id') . ' = :t')->bind(':t', $t, ParameterType::INTEGER))->loadAssoc();

		if (!$row || (int) $row['user_id'] !== $userId || !\in_array((int) $row['published'], [0, 1], true))
		{
			throw new SubmissionException('trip', 404);
		}

		return $row;
	}

	/** @return array<string, mixed> */
	private function ownPhoto(int $userId, int $photoId): array
	{
		if ($userId <= 0)
		{
			throw new SubmissionException('login', 403);
		}

		$p   = $photoId;
		$row = $this->db->setQuery($this->db->getQuery(true)->select($this->db->quoteName(['p.id', 'p.trip_id', 'p.filename', 'p.friends_only', 'p.state', 't.user_id', 't.published']))->from($this->db->quoteName('#__mapaviajes_photos', 'p'))
			->join('INNER', $this->db->quoteName('#__mapaviajes_trips', 't') . ' ON t.id = p.trip_id')->where($this->db->quoteName('p.id') . ' = :p')->bind(':p', $p, ParameterType::INTEGER))->loadAssoc();

		if (!$row || (int) $row['user_id'] !== $userId || !\in_array((int) $row['published'], [0, 1], true))
		{
			throw new SubmissionException('trip', 404);
		}

		return $row;
	}

	private function countPhotos(int $tripId): int
	{
		$t = $tripId;

		return (int) $this->db->setQuery($this->db->getQuery(true)->select('COUNT(*)')->from($this->db->quoteName('#__mapaviajes_photos'))->where($this->db->quoteName('trip_id') . ' = :t')->bind(':t', $t, ParameterType::INTEGER))->loadResult();
	}

	/** Is there a live public photo in the trip besides this one? */
	private function otherPublicLive(int $tripId, int $exceptId): bool
	{
		$t = $tripId;
		$e = $exceptId;

		return (int) $this->db->setQuery($this->db->getQuery(true)->select('COUNT(*)')->from($this->db->quoteName('#__mapaviajes_photos'))->where($this->db->quoteName('trip_id') . ' = :t')->where($this->db->quoteName('id') . ' <> :e')
			->where($this->db->quoteName('friends_only') . ' = 0')->where($this->db->quoteName('state') . ' = 1')->bind(':t', $t, ParameterType::INTEGER)->bind(':e', $e, ParameterType::INTEGER))->loadResult() > 0;
	}

	private function albumLogo(int $albumId): string
	{
		$a = $albumId;

		return (string) $this->db->setQuery($this->db->getQuery(true)->select($this->db->quoteName('logo'))->from($this->db->quoteName('#__mapaviajes_albums'))->where($this->db->quoteName('id') . ' = :a')->bind(':a', $a, ParameterType::INTEGER))->loadResult();
	}

	/** Every request that reaches the image decoder counts, whatever happens next (the same idea as a new trip). */
	private function reserve(int $userId): void
	{
		$db = $this->db;
		$db->transactionStart();

		try
		{
			UserLock::take($db, $userId);
			$since = Factory::getDate('-1 hour')->toSql();
			$u     = $userId;
			$n     = (int) $db->setQuery($db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__mapaviajes_events'))->where($db->quoteName('user_id') . ' = :u')->where($db->quoteName('event') . ' = ' . $db->quote('photo_add'))
				->where($db->quoteName('created') . ' >= :s')->bind(':u', $u, ParameterType::INTEGER)->bind(':s', $since))->loadResult();

			if ($n >= self::ADD_PER_HOUR)
			{
				throw new SubmissionException('rate', 429);
			}

			EventLog::add($db, $userId, 'photo_add');
			$db->transactionCommit();
		}
		catch (\Throwable $e)
		{
			$db->transactionRollback();

			throw $e;
		}
	}

	/**
	 * @param array<int, string> $names @param array<int, array{width: int, height: int, bytes: int}> $meta @param array<int, string> $captions @param array<int, int> $flags
	 */
	private function insertRows(int $tripId, int $userId, int $max, array $names, array $meta, array $captions, array $flags, int $state): void
	{
		$db  = $this->db;
		$now = Factory::getDate()->toSql();
		$db->transactionStart();

		try
		{
			UserLock::take($db, $userId);
			$have = $this->countPhotos($tripId);

			if ($have + \count($names) > $max)
			{
				throw new SubmissionException('photos_too_many', 422, ['max' => $max]);
			}

			$t   = $tripId;
			$ord = (int) $db->setQuery($db->getQuery(true)->select('COALESCE(MAX(' . $db->quoteName('ordering') . '), 0)')->from($db->quoteName('#__mapaviajes_photos'))->where($db->quoteName('trip_id') . ' = :t')->bind(':t', $t, ParameterType::INTEGER))->loadResult();

			foreach ($names as $i => $name)
			{
				$ord++;
				$w   = $meta[$i]['width'];
				$h   = $meta[$i]['height'];
				$b   = $meta[$i]['bytes'];
				$cap = $captions[$i];
				$fo  = $flags[$i];
				$st  = $state;
				$tr  = $tripId;
				$o   = $ord;
				$db->setQuery($db->getQuery(true)->insert($db->quoteName('#__mapaviajes_photos'))
					->columns($db->quoteName(['trip_id', 'ordering', 'filename', 'width', 'height', 'bytes', 'caption', 'friends_only', 'state', 'created']))->values(':trip, :ord, :file, :w, :h, :b, :cap, :fo, :st, :created')
					->bind(':trip', $tr, ParameterType::INTEGER)->bind(':ord', $o, ParameterType::INTEGER)->bind(':file', $name)->bind(':w', $w, ParameterType::INTEGER)->bind(':h', $h, ParameterType::INTEGER)
					->bind(':b', $b, ParameterType::INTEGER)->bind(':cap', $cap)->bind(':fo', $fo, ParameterType::INTEGER)->bind(':st', $st, ParameterType::INTEGER)->bind(':created', $now))->execute();
			}

			self::recount($db, [$tripId]);
			$db->transactionCommit();
		}
		catch (\Throwable $e)
		{
			$db->transactionRollback();

			throw $e;
		}
	}

	private function tellModerators(string $title, string $username, int $photos): void
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

		if ($to !== [])
		{
			(new Notifier($this->db, $this->settings))->photosPending(array_values($to), $title, $username, $photos);
		}
	}
}
