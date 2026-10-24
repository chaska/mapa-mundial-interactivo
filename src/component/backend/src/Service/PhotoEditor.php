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
	 * One save of the edit screen: photos to delete, "friends only" switches of the photos that stay, and new photos. The final
	 * state is checked as a whole (at most the album maximum, at least one photo, at least one live public photo) before anything
	 * is written, so the order in which the person did things on screen never matters.
	 *
	 * @param   list<int>             $delete    ids of the photos to delete
	 * @param   array<int, int>       $priv      existing photo id => 1 friends only | 0 public
	 * @param   array<int, array<string, mixed>>  $files     flattened uploads of the new photos, in the order sent
	 * @param   array<int, mixed>     $captions  one per new file
	 * @param   array<int, mixed>     $newPriv   "1" = friends only, one per new file
	 *
	 * @param   array<string, mixed>  $fields  optional new title, place (+ lat, lon) and trip_year of the trip itself
	 *
	 * @return  array{added: int, removed: int, changed: int, pending: bool, review: bool}
	 * @throws  SubmissionException
	 */
	public function apply(User $user, int $tripId, array $delete, array $priv, array $files, array $captions, array $newPriv, array $fields = []): array
	{
		$userId = (int) $user->id;
		$trip   = $this->ownTrip($userId, $tripId);
		$max    = $this->settings->maxPhotos();
		$have   = $this->photosOf((int) $trip['id']);
		$byId   = [];

		foreach ($have as $p)
		{
			$byId[(int) $p['id']] = $p;
		}

		$delete = array_values(array_unique(array_map('intval', $delete)));

		foreach ($delete as $id)
		{
			if (!isset($byId[$id]))
			{
				throw new SubmissionException('trip', 404);
			}
		}

		$change = [];

		foreach ($priv as $id => $v)
		{
			$id = (int) $id;

			if (!isset($byId[$id]) || ($v !== 0 && $v !== 1) || \in_array($id, $delete, true))
			{
				continue;
			}

			if ((int) $byId[$id]['friends_only'] !== $v)
			{
				$change[$id] = $v;
			}
		}

		$edit = $this->tripFields($userId, $trip, $fields); // validated new values of the trip itself (only what really changed)

		// A new TITLE is free text: when the site reviews first, the whole trip goes back to review (the place only comes from the search list or the map)
		$needsReview = (int) $trip['published'] === 1 && $this->settings->reviewFirst() && !$user->authorise('core.admin');
		$toReview    = $needsReview && isset($edit['title']);
		$n     = \count($files);
		$state = ($needsReview && !$toReview) ? 0 : 1; // live trip: new photos wait one by one; a trip sent back to review is reviewed as a whole

		// the final state, checked as a whole
		$left = 0;
		$livePublic = 0;

		foreach ($byId as $id => $p)
		{
			if (\in_array($id, $delete, true))
			{
				continue;
			}

			$left++;
			$fo = $change[$id] ?? (int) $p['friends_only'];

			if ((int) $p['state'] === 1 && $fo === 0)
			{
				$livePublic++;
			}
		}

		$flags = [];

		foreach (array_values($files) as $i => $unused)
		{
			$flags[$i] = (($newPriv[$i] ?? '0') === '1') ? 1 : 0;

			if ($state === 1 && $flags[$i] === 0)
			{
				$livePublic++;
			}
		}

		if ($left + $n > $max)
		{
			throw new SubmissionException('photos_too_many', 422, ['max' => $max]);
		}

		if ($left + $n < 1)
		{
			throw new SubmissionException('photo_last', 422);
		}

		if ($livePublic < 1)
		{
			throw new SubmissionException('photo_public', 422);
		}

		if ($delete === [] && $change === [] && $n === 0 && $edit === [])
		{
			return ['added' => 0, 'removed' => 0, 'changed' => 0, 'pending' => false, 'review' => false];
		}

		$clean = [];

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
		}

		$storage = $this->settings->storage();
		$names   = [];
		$meta    = [];

		if ($n > 0)
		{
			$this->reserve($userId);

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

				foreach ($temporaries as $i => $tmp)
				{
					$names[$i] = $storage->commit($tmp, $userId, (int) $trip['trip_year'], \count($have) + $i + 1);
				}
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

				throw $e;
			}
		}

		$gone = [];

		try
		{
			$gone = $this->write((int) $trip['id'], $userId, $max, $delete, $change, $names, $meta, $clean, $flags, $state, $edit, $toReview);
		}
		catch (\Throwable $e)
		{
			foreach ($names as $name)
			{
				$storage->delete($name);
			}

			if ($e instanceof SubmissionException)
			{
				throw $e;
			}

			Log::add('mapaviajes photo edit failed: ' . \get_class($e) . ': ' . $e->getMessage(), Log::ERROR, 'com_mapaviajes');

			throw new SubmissionException('server', 500);
		}

		foreach ($gone as $name)
		{
			$storage->delete($name);
		}

		EventLog::add($this->db, $userId, 'photos_edited');

		if ($n > 0 && $state === 0)
		{
			$this->tellModerators((string) $trip['title'], (string) $user->username, $n);
		}

		if ($toReview)
		{
			EventLog::add($this->db, $userId, 'trip_rereview');
			$this->tellNewTrip((string) ($edit['title'] ?? $trip['title']), (string) $user->username, $this->countLive((int) $trip['id']), (int) $trip['album_id']);
		}

		return ['added' => $n, 'removed' => \count($delete), 'changed' => \count($change) + \count($edit), 'pending' => $n > 0 && $state === 0, 'review' => $toReview];
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
		$row = $this->db->setQuery($this->db->getQuery(true)->select($this->db->quoteName(['id', 'user_id', 'album_id', 'title', 'place', 'lat', 'lon', 'published', 'trip_year']))->from($this->db->quoteName('#__mapaviajes_trips'))->where($this->db->quoteName('id') . ' = :t')->bind(':t', $t, ParameterType::INTEGER))->loadAssoc();

		if (!$row || (int) $row['user_id'] !== $userId || !\in_array((int) $row['published'], [0, 1], true))
		{
			throw new SubmissionException('trip', 404);
		}

		return $row;
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
	 * Everything of one save in a single transaction (under the person's row lock). Returns the file names to delete once it is committed.
	 *
	 * @param list<int> $delete @param array<int, int> $change @param array<int, string> $names
	 * @param array<int, array{width: int, height: int, bytes: int}> $meta @param array<int, string> $captions @param array<int, int> $flags
	 * @return list<string>
	 */
	private function write(int $tripId, int $userId, int $max, array $delete, array $change, array $names, array $meta, array $captions, array $flags, int $state, array $edit = [], bool $toReview = false): array
	{
		$db  = $this->db;
		$now = Factory::getDate()->toSql();
		$db->transactionStart();

		try
		{
			UserLock::take($db, $userId);
			$have = $this->photosOf($tripId); // re-read under the lock: parallel requests queue here
			$ids  = array_map(static fn (array $p): int => (int) $p['id'], $have);

			foreach ($delete as $id)
			{
				if (!\in_array($id, $ids, true))
				{
					throw new SubmissionException('trip', 404);
				}
			}

			if (\count($have) - \count($delete) + \count($names) > $max)
			{
				throw new SubmissionException('photos_too_many', 422, ['max' => $max]);
			}

			$gone = [];

			foreach ($have as $p)
			{
				if (\in_array((int) $p['id'], $delete, true))
				{
					$gone[] = (string) $p['filename'];
				}
			}

			foreach ($change as $id => $fo)
			{
				$i = (int) $id;
				$f = (int) $fo;
				$db->setQuery($db->getQuery(true)->update($db->quoteName('#__mapaviajes_photos'))->set($db->quoteName('friends_only') . ' = :f')->where($db->quoteName('id') . ' = :i')->where($db->quoteName('trip_id') . ' = :t')
					->bind(':f', $f, ParameterType::INTEGER)->bind(':i', $i, ParameterType::INTEGER)->bind(':t', $tripId, ParameterType::INTEGER))->execute();
			}

			if ($delete !== [])
			{
				$db->setQuery($db->getQuery(true)->delete($db->quoteName('#__mapaviajes_photos'))->where($db->quoteName('trip_id') . ' = ' . (int) $tripId)->whereIn($db->quoteName('id'), $delete))->execute();
			}

			$ord = 0;

			foreach ($have as $p)
			{
				$ord = max($ord, (int) $p['ordering']);
			}

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

			if ($edit !== [] || $toReview)
			{
				$q = $db->getQuery(true)->update($db->quoteName('#__mapaviajes_trips'))->where($db->quoteName('id') . ' = :tid')->where($db->quoteName('user_id') . ' = :own')->bind(':tid', $tripId, ParameterType::INTEGER)->bind(':own', $userId, ParameterType::INTEGER);
				$map = ['title' => ':v1', 'place' => ':v2', 'lat' => ':v3', 'lon' => ':v4', 'trip_year' => ':v5'];
				$vals = [];

				foreach ($map as $col => $ph)
				{
					if (\array_key_exists($col, $edit))
					{
						$vals[$col] = $edit[$col];
						$q->set($db->quoteName($col) . ' = ' . $ph);
						\is_int($vals[$col]) ? $q->bind($ph, $vals[$col], ParameterType::INTEGER) : $q->bind($ph, $vals[$col]);
					}
				}

				if ($toReview)
				{
					$q->set($db->quoteName('published') . ' = 0');
				}

				$db->setQuery($q)->execute();
			}

			self::recount($db, [$tripId]);
			$db->transactionCommit();
		}
		catch (\Throwable $e)
		{
			$db->transactionRollback();

			throw $e;
		}

		return $gone;
	}

	/**
	 * The new values of the trip itself that really differ from what is stored, validated like a new trip (nothing else can change here).
	 *
	 * @param  array<string, mixed>  $trip
	 * @param  array<string, mixed>  $post
	 * @return array<string, int|string>
	 */
	private function tripFields(int $userId, array $trip, array $post): array
	{
		$out = [];

		if (isset($post['title']) && \is_string($post['title']) && $post['title'] !== '')
		{
			try
			{
				$title = TextSanitizer::clean($post['title'], 80, 0, false);
			}
			catch (SanitizeException $e)
			{
				EventLog::add($this->db, $userId, 'reject_text', 'field:' . $e->getReason());
				throw new SubmissionException($e->getReason(), 422, ['field' => 'title']);
			}

			if ($title === '')
			{
				throw new SubmissionException('title_required', 422, ['field' => 'title']);
			}

			if ($title !== (string) $trip['title'])
			{
				$out['title'] = $title;
			}
		}

		if (isset($post['lat'], $post['lon']))
		{
			[$lat, $lon] = TripSubmission::coordinates($post['lat'], $post['lon']);
			$latS = sprintf('%.6f', $lat);
			$lonS = sprintf('%.6f', $lon);
			$place = (string) $trip['place'];

			if (isset($post['place']) && \is_string($post['place']))
			{
				try
				{
					$place = TextSanitizer::clean($post['place'], 120, 0, false);
				}
				catch (SanitizeException $e)
				{
					EventLog::add($this->db, $userId, 'reject_text', 'field:' . $e->getReason());
					throw new SubmissionException($e->getReason(), 422, ['field' => 'place']);
				}
			}

			if ($place === '')
			{
				$place = sprintf('%.3f, %.3f', $lat, $lon);
			}

			if ($latS !== sprintf('%.6f', (float) $trip['lat']) || $lonS !== sprintf('%.6f', (float) $trip['lon']) || $place !== (string) $trip['place'])
			{
				$out['lat']   = $latS;
				$out['lon']   = $lonS;
				$out['place'] = $place;
			}
		}

		if (isset($post['trip_year']) && $post['trip_year'] !== '')
		{
			$year = TripSubmission::year($post['trip_year']);

			if ($year !== (int) $trip['trip_year'])
			{
				$out['trip_year'] = $year;
			}
		}

		return $out;
	}

	private function countLive(int $tripId): int
	{
		$t = $tripId;

		return (int) $this->db->setQuery($this->db->getQuery(true)->select('COUNT(*)')->from($this->db->quoteName('#__mapaviajes_photos'))->where($this->db->quoteName('trip_id') . ' = :t')->bind(':t', $t, ParameterType::INTEGER))->loadResult();
	}

	private function tellNewTrip(string $title, string $username, int $photos, int $albumId): void
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
			(new Notifier($this->db, $this->settings))->newTrip(array_values($to), $this->albumTitle($albumId), $title, $username, $photos, true);
		}
	}

	private function albumTitle(int $albumId): string
	{
		$a = $albumId;

		return (string) $this->db->setQuery($this->db->getQuery(true)->select($this->db->quoteName('title'))->from($this->db->quoteName('#__mapaviajes_albums'))->where($this->db->quoteName('id') . ' = :a')->bind(':a', $a, ParameterType::INTEGER))->loadResult();
	}

	/** @return list<array<string, mixed>> */
	private function photosOf(int $tripId): array
	{
		$t = $tripId;

		return $this->db->setQuery($this->db->getQuery(true)->select($this->db->quoteName(['id', 'filename', 'friends_only', 'state', 'ordering']))->from($this->db->quoteName('#__mapaviajes_photos'))->where($this->db->quoteName('trip_id') . ' = :t')->order($this->db->quoteName('ordering'))->bind(':t', $t, ParameterType::INTEGER))->loadAssocList() ?: [];
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
