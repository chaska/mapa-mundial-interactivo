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
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * The only door through which a trip enters the database.
 *
 * Order matters: cheap checks first (who, how often, which album), then every piece of text, then every
 * image (the expensive part). Nothing is written until EVERYTHING has been accepted; if any photo fails,
 * no row and no file remain.
 */
final class TripSubmission
{
	public function __construct(
		private DatabaseInterface $db,
		private Settings $settings,
		private ?ImageProcessor $processor = null
	)
	{
		$this->processor ??= new ImageProcessor(
			$settings->maxUploadBytes(),
			$settings->longSide(),
			$settings->quality()
		);
	}

	/**
	 * @param   array<string, mixed>                $post   Raw request fields.
	 * @param   array<int, array<string, mixed>>    $files  Flattened uploads, in the order sent.
	 *
	 * @return  array{id: int, published: int}
	 * @throws  SubmissionException
	 */
	public function handle(User $user, array $post, array $files): array
	{
		$userId = (int) $user->id;

		if ($userId <= 0)
		{
			throw new SubmissionException('login', 403);
		}

		// Every request that reaches this point costs CPU, so every request counts as an attempt BEFORE any work is done.
		$this->reserveAttempt($userId);

		// Honeypot: a human never fills this field.
		if (($post['website'] ?? '') !== '')
		{
			EventLog::add($this->db, $userId, 'honeypot');
			throw new SubmissionException('generic', 400);
		}

		$this->assertRate($userId);

		$album = $this->loadAlbum($user, $this->intField($post['album_id'] ?? null));

		try
		{
			$title = TextSanitizer::clean($this->stringField($post['title'] ?? ''), 80, 0, false);
			$place = TextSanitizer::clean($this->stringField($post['place'] ?? ''), 120, 0, false);
		}
		catch (SanitizeException $e)
		{
			EventLog::add($this->db, $userId, 'reject_text', 'field:' . $e->getReason());
			throw new SubmissionException($e->getReason(), 422, ['field' => 'title']);
		}

		if ($title === '')
		{
			throw new SubmissionException('title_required', 422);
		}

		[$lat, $lon] = $this->coordinates($post['lat'] ?? null, $post['lon'] ?? null);

		if ($place === '')
		{
			$place = sprintf('%.3f, %.3f', $lat, $lon);
		}

		$max = $this->settings->maxPhotos();

		if (\count($files) < 1)
		{
			throw new SubmissionException('photos_required', 422);
		}

		if (\count($files) > $max)
		{
			throw new SubmissionException('photos_too_many', 422, ['max' => $max]);
		}

		$captionsRaw = $post['captions'] ?? [];

		if (!\is_array($captionsRaw) || \count($captionsRaw) > $max)
		{
			throw new SubmissionException('generic', 400);
		}

		$captions = [];

		foreach (array_values($files) as $i => $unused)
		{
			$raw = $captionsRaw[$i] ?? '';

			try
			{
				$captions[$i] = TextSanitizer::clean($this->stringField($raw), 2000, $this->settings->maxWords(), true);
			}
			catch (SanitizeException $e)
			{
				EventLog::add($this->db, $userId, 'reject_text', 'caption:' . $e->getReason());
				throw new SubmissionException($e->getReason(), 422, ['photo' => $i + 1]);
			}
		}

		// Images: everything is converted into private temporary files first.
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

		$temporaries = [];
		$meta        = [];

		try
		{
			foreach (array_values($files) as $i => $file)
			{
				$tmp = $storage->newTemporaryPath();

				try
				{
					$meta[$i] = $this->processor->process($file, $tmp);
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

		// Everything accepted: give the files their final random names, then write the rows in one transaction.
		$names = [];

		try
		{
			foreach ($temporaries as $i => $tmp)
			{
				$names[$i] = $storage->commit($tmp);
			}

			$tripId = $this->store($userId, $album, $title, $place, $lat, $lon, $post, $names, $meta, $captions);
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

			// For the site owner only: class and message, never anything the user sent.
			Log::add('mapaviajes store failed: ' . \get_class($e) . ': ' . $e->getMessage(), Log::ERROR, 'com_mapaviajes');

			throw new SubmissionException('server', 500);
		}

		$published = $this->settings->reviewFirst() ? 0 : 1;
		$this->notifyAdministrators($user, $album['title'], $title, \count($names), $published === 0);

		return ['id' => $tripId, 'published' => $published];
	}

	/** Rows of the given table kind for this user since a moment. */
	private function countSince(string $table, string $userId_col, int $userId, string $since, ?string $event = null): int
	{
		$uid   = $userId;
		$since = $since;
		$query = $this->db->getQuery(true)
			->select('COUNT(*)')
			->from($this->db->quoteName($table))
			->where($this->db->quoteName($userId_col) . ' = :u')
			->where($this->db->quoteName('created') . ' >= :s')
			->bind(':u', $uid, ParameterType::INTEGER)
			->bind(':s', $since);

		if ($event !== null)
		{
			$query->where($this->db->quoteName('event') . ' = :e')->bind(':e', $event);
		}

		return (int) $this->db->setQuery($query)->loadResult();
	}

	private function tripsOverLimit(int $userId): bool
	{
		return $this->countSince('#__mapaviajes_trips', 'user_id', $userId, Factory::getDate('-1 hour')->toSql()) >= $this->settings->perHour()
			|| $this->countSince('#__mapaviajes_trips', 'user_id', $userId, Factory::getDate('-1 day')->toSql()) >= $this->settings->perDay();
	}

	private function assertRate(int $userId): void
	{
		if ($this->tripsOverLimit($userId))
		{
			throw new SubmissionException('rate', 429);
		}
	}

	/**
	 * Failed and abusive requests count too: a few retries after a mistake are fine, a flood is not. Over the limit the
	 * request is refused WITHOUT writing anything, so the refusals cannot fill the table either.
	 */
	private function reserveAttempt(int $userId): void
	{
		$max = max(20, $this->settings->perHour() * 6);

		if ($this->countSince('#__mapaviajes_events', 'user_id', $userId, Factory::getDate('-1 hour')->toSql(), 'attempt') >= $max)
		{
			throw new SubmissionException('rate', 429);
		}

		$now   = Factory::getDate()->toSql();
		$uid   = $userId;
		$kind  = 'attempt';
		$empty = '';
		$this->db->setQuery(
			$this->db->getQuery(true)
				->insert($this->db->quoteName('#__mapaviajes_events'))
				->columns($this->db->quoteName(['user_id', 'event', 'detail', 'created']))
				->values(':u, :e, :d, :c')
				->bind(':u', $uid, ParameterType::INTEGER)
				->bind(':e', $kind)
				->bind(':d', $empty)
				->bind(':c', $now)
		)->execute();

		EventLog::prune($this->db);
	}

	/** @return array{id: int, title: string, allow_comments: int} */
	private function loadAlbum(User $user, int $albumId): array
	{
		if ($albumId <= 0)
		{
			throw new SubmissionException('album', 422);
		}

		$query = $this->db->getQuery(true)
			->select($this->db->quoteName(['id', 'title', 'access', 'allow_comments']))
			->from($this->db->quoteName('#__mapaviajes_albums'))
			->where($this->db->quoteName('id') . ' = :id')
			->where($this->db->quoteName('published') . ' = 1')
			->bind(':id', $albumId, ParameterType::INTEGER);
		$row = $this->db->setQuery($query)->loadAssoc();

		// Same answer whether the album does not exist or the user may not see it.
		if (!$row || !\in_array((int) $row['access'], array_map('intval', $user->getAuthorisedViewLevels()), true))
		{
			throw new SubmissionException('album', 422);
		}

		return ['id' => (int) $row['id'], 'title' => (string) $row['title'], 'allow_comments' => (int) $row['allow_comments']];
	}

	/** @return array{0: float, 1: float} */
	private function coordinates(mixed $rawLat, mixed $rawLon): array
	{
		$parse = static function (mixed $v, float $limit): ?float {
			if (!\is_string($v) || $v === '' || \strlen($v) > 20 || !preg_match('/^-?\d{1,3}(?:\.\d{1,12})?$/', $v))
			{
				return null;
			}

			$f = (float) $v;

			return abs($f) <= $limit ? round($f, 6) : null;
		};

		$lat = $parse($rawLat, 90.0);
		$lon = $parse($rawLon, 180.0);

		if ($lat === null || $lon === null)
		{
			throw new SubmissionException('place', 422);
		}

		return [$lat, $lon];
	}

	/**
	 * @param   array{id: int, title: string, allow_comments: int}  $album
	 * @param   array<string, mixed>                                $post
	 * @param   array<int, string>                                  $names
	 * @param   array<int, array{width: int, height: int, bytes: int}>  $meta
	 * @param   array<int, string>                                  $captions
	 */
	private function store(int $userId, array $album, string $title, string $place, float $lat, float $lon, array $post, array $names, array $meta, array $captions): int
	{
		$db        = $this->db;
		$now       = Factory::getDate()->toSql();
		$published = $this->settings->reviewFirst() ? 0 : 1;
		$allow     = ($album['allow_comments'] === 1 && ($post['allow_comments'] ?? '') === '1') ? 1 : 0;
		$notify    = ($allow === 1 && ($post['notify'] ?? '') === '1') ? 1 : 0;
		$albumId   = $album['id'];
		$count     = \count($names);
		$latS      = sprintf('%.6f', $lat);
		$lonS      = sprintf('%.6f', $lon);

		$db->transactionStart();

		try
		{
			// Serialise this user's submissions: parallel requests wait here one after another, and each one re-counts
			// against the trips the previous ones really committed. Without the lock, N parallel requests all pass.
			$lockId = $userId;
			$db->setQuery(
				$db->getQuery(true)->update($db->quoteName('#__users'))->set($db->quoteName('id') . ' = ' . $db->quoteName('id'))->where($db->quoteName('id') . ' = :lock')->bind(':lock', $lockId, ParameterType::INTEGER)
			)->execute();

			if ($this->tripsOverLimit($userId))
			{
				EventLog::add($db, $userId, 'rate_limit');
				throw new SubmissionException('rate', 429);
			}

			$query = $db->getQuery(true)
				->insert($db->quoteName('#__mapaviajes_trips'))
				->columns($db->quoteName(['album_id', 'user_id', 'title', 'place', 'lat', 'lon', 'allow_comments', 'notify', 'published', 'photo_count', 'created']))
				->values(':album, :user, :title, :place, :lat, :lon, :allow, :notify, :pub, :cnt, :created')
				->bind(':album', $albumId, ParameterType::INTEGER)
				->bind(':user', $userId, ParameterType::INTEGER)
				->bind(':title', $title)
				->bind(':place', $place)
				->bind(':lat', $latS)
				->bind(':lon', $lonS)
				->bind(':allow', $allow, ParameterType::INTEGER)
				->bind(':notify', $notify, ParameterType::INTEGER)
				->bind(':pub', $published, ParameterType::INTEGER)
				->bind(':cnt', $count, ParameterType::INTEGER)
				->bind(':created', $now);
			$db->setQuery($query)->execute();
			$tripId = (int) $db->insertid();

			if ($tripId <= 0)
			{
				throw new \RuntimeException('insert');
			}

			foreach ($names as $i => $name)
			{
				$order = $i + 1;
				$w     = $meta[$i]['width'];
				$h     = $meta[$i]['height'];
				$b     = $meta[$i]['bytes'];
				$cap   = $captions[$i];

				$q = $db->getQuery(true)
					->insert($db->quoteName('#__mapaviajes_photos'))
					->columns($db->quoteName(['trip_id', 'ordering', 'filename', 'width', 'height', 'bytes', 'caption', 'created']))
					->values(':trip, :ord, :file, :w, :h, :b, :cap, :created')
					->bind(':trip', $tripId, ParameterType::INTEGER)
					->bind(':ord', $order, ParameterType::INTEGER)
					->bind(':file', $name)
					->bind(':w', $w, ParameterType::INTEGER)
					->bind(':h', $h, ParameterType::INTEGER)
					->bind(':b', $b, ParameterType::INTEGER)
					->bind(':cap', $cap)
					->bind(':created', $now);
				$db->setQuery($q)->execute();
			}

			$db->transactionCommit();
		}
		catch (\Throwable $e)
		{
			$db->transactionRollback();

			throw $e;
		}

		// Joomla's bind() may have turned the referenced variable into a string.
		return (int) $tripId;
	}

	private function notifyAdministrators(User $author, string $album, string $title, int $photos, bool $needsReview): void
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

		try
		{
			$flat   = static fn (string $s): string => trim(preg_replace('/[\r\n\t]+/', ' ', $s) ?? '');
			$mailer = Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer();
			$mailer->isHtml(false);
			$mailer->CharSet = 'UTF-8';
			$mailer->setSubject('[' . $flat((string) Factory::getApplication()->get('sitename', 'Site')) . '] ' . ($needsReview ? 'Nuevo viaje por revisar' : 'Nuevo viaje publicado'));
			$mailer->setBody(
				"Álbum: " . $flat($album) . "\n"
				. "Título: " . $flat($title) . "\n"
				. "Usuario: " . $flat((string) $author->username) . "\n"
				. "Fotos: " . $photos . "\n\n"
				. "Gestionar: " . Uri::root() . "administrator/index.php?option=com_mapaviajes&view=trips\n"
			);

			foreach ($to as $address)
			{
				$mailer->addRecipient($address);
			}

			$mailer->Send();
		}
		catch (\Throwable $e)
		{
			// A mail problem never rejects a trip that was saved correctly.
		}
	}

	private function intField(mixed $v): int
	{
		return (\is_string($v) && preg_match('/^\d{1,9}$/', $v) === 1) ? (int) $v : 0;
	}

	private function stringField(mixed $v): string
	{
		if (!\is_string($v))
		{
			throw new SubmissionException('generic', 400);
		}

		return $v;
	}
}
