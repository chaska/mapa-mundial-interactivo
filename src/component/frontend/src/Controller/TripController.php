<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Site\Controller;

defined('_JEXEC') || die;

use Chaska\Component\Mapaviajes\Administrator\Security\ImageProcessor;
use Chaska\Component\Mapaviajes\Administrator\Service\EventLog;
use Chaska\Component\Mapaviajes\Administrator\Service\PhotoEditor;
use Chaska\Component\Mapaviajes\Administrator\Service\ReportService;
use Chaska\Component\Mapaviajes\Administrator\Service\Settings;
use Chaska\Component\Mapaviajes\Administrator\Service\SubmissionException;
use Chaska\Component\Mapaviajes\Administrator\Service\TripCommentsSwitch;
use Chaska\Component\Mapaviajes\Administrator\Service\TripRemoval;
use Chaska\Component\Mapaviajes\Administrator\Service\TripSubmission;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;

/**
 * POST ?option=com_mapaviajes&task=trip.submit
 *
 * Gates, in this order, each answering with a fixed generic message: POST only, same-origin AJAX header,
 * valid session token, logged-in user, permission to create. Only then does TripSubmission see the data.
 */
class TripController extends BaseController
{
	use JsonTrait;

	public function submit(): void
	{
		$app  = $this->app;
		$user = $app->getIdentity();

		if (strtoupper((string) $app->getInput()->getMethod()) !== 'POST')
		{
			header('Allow: POST');
			$this->json(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_GENERIC')], 405);
		}

		// A request bigger than post_max_size arrives EMPTY: say so instead of blaming the form.
		if ($_POST === [] && $_FILES === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0)
		{
			$this->json(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_REQUEST_TOO_BIG')], 413);
		}

		if (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) !== 'xmlhttprequest' || !Session::checkToken('post'))
		{
			$this->json(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_SESSION'), 'expired' => true], 403);
		}

		if ($user->guest)
		{
			$this->json(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_LOGIN'), 'expired' => true], 403);
		}

		if (!$user->authorise('mapaviajes.submit', 'com_mapaviajes'))
		{
			$this->json(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_FORBIDDEN')], 403);
		}

		if (!ImageProcessor::isSupported())
		{
			$this->json(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_IMAGE_SERVER')], 500);
		}

		$db       = Factory::getContainer()->get(DatabaseInterface::class);
		$settings = new Settings();

		try
		{
			$result = (new TripSubmission($db, $settings))->handle($user, $this->postFields(), $this->uploads());
		}
		catch (SubmissionException $e)
		{
			$key     = 'COM_MAPAVIAJES_ERR_' . strtoupper($e->key());
			$message = Text::_($key);

			if ($message === $key)
			{
				$message = Text::_('COM_MAPAVIAJES_ERR_GENERIC');
			}

			$ctx = $e->context();

			if (isset($ctx['photo']))
			{
				$message = Text::sprintf('COM_MAPAVIAJES_ERR_PHOTO_N', (int) $ctx['photo']) . ' ' . $message;
			}

			if (isset($ctx['max']))
			{
				$message = sprintf($message, (int) $ctx['max']);
			}

			$this->json(['ok' => false, 'error' => $message, 'field' => (string) ($ctx['field'] ?? ''), 'photo' => (int) ($ctx['photo'] ?? 0)], $e->status());
		}
		catch (\Throwable $e)
		{
			EventLog::add($db, (int) $user->id, 'error', 'unexpected');
			$this->json(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_SERVER')], 500);
		}

		$this->json([
			'ok'        => true,
			'id'        => $result['id'],
			'published' => $result['published'] === 1,
			'message'   => Text::_($result['published'] === 1 ? 'COM_MAPAVIAJES_SENT_PUBLISHED' : 'COM_MAPAVIAJES_SENT_REVIEW'),
		], 201);
	}

	/**
	 * Deletes a trip for good: its author deletes their own (a wrong pin they cannot otherwise remove), administrators with the
	 * "delete" permission delete any. Same gates as submit(): POST, AJAX header, session token, logged-in user.
	 */
	public function remove(): void
	{
		$app  = $this->app;
		$user = $app->getIdentity();

		if (strtoupper((string) $app->getInput()->getMethod()) !== 'POST')
		{
			header('Allow: POST');
			$this->json(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_GENERIC')], 405);
		}

		if (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) !== 'xmlhttprequest' || !Session::checkToken('post'))
		{
			$this->json(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_SESSION'), 'expired' => true], 403);
		}

		if ($user->guest)
		{
			$this->json(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_LOGIN'), 'expired' => true], 403);
		}

		$raw = $_POST['id'] ?? '';
		$id  = (\is_string($raw) && preg_match('/^\d{1,9}$/', $raw) === 1) ? (int) $raw : 0;
		$db  = Factory::getContainer()->get(DatabaseInterface::class);

		try
		{
			(new TripRemoval($db, new Settings()))->remove($user, $id);
		}
		catch (SubmissionException $e)
		{
			$this->json(['ok' => false, 'error' => Text::_($e->key() === 'login' ? 'COM_MAPAVIAJES_ERR_LOGIN' : 'COM_MAPAVIAJES_ERR_TRIP_GONE')], $e->status());
		}
		catch (\Throwable $e)
		{
			EventLog::add($db, (int) $user->id, 'error', 'remove');
			$this->json(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_SERVER')], 500);
		}

		$this->json(['ok' => true, 'id' => $id, 'message' => Text::_('COM_MAPAVIAJES_REMOVED')], 200);
	}

	/**
	 * Saves the edit screen of a trip of yours that is already on the map. POST multipart: id, delete[] (photo ids), ep[PHOTO_ID] = 0|1
	 * ("friends only" of the photos that stay), and for new photos photos[], captions[], priv[] (+ rights = 1).
	 */
	public function photos(): void
	{
		if ($_POST === [] && $_FILES === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0)
		{
			$this->json(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_REQUEST_TOO_BIG')], 413);
		}

		$user = $this->writeGate();

		if (!$user->authorise('mapaviajes.submit', 'com_mapaviajes'))
		{
			$this->json(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_FORBIDDEN')], 403);
		}

		$files = $this->uploads();

		if ($files !== [])
		{
			if (!ImageProcessor::isSupported())
			{
				$this->json(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_IMAGE_SERVER')], 500);
			}

			if (($_POST['rights'] ?? '') !== '1')
			{
				$this->json(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_RIGHTS'), 'field' => 'rights'], 422);
			}
		}

		$id     = $this->intOf($_POST['id'] ?? '');
		$delete = [];

		foreach (\is_array($_POST['delete'] ?? null) ? $_POST['delete'] : [] as $d)
		{
			$delete[] = $this->intOf($d);
		}

		$priv = [];

		foreach (\is_array($_POST['ep'] ?? null) ? $_POST['ep'] : [] as $pid => $v)
		{
			if (\is_int($pid) && ($v === '0' || $v === '1'))
			{
				$priv[$pid] = (int) $v;
			}
		}

		if (\count($delete) > 50 || \count($priv) > 50)
		{
			$this->json(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_GENERIC')], 400);
		}

		$db = Factory::getContainer()->get(DatabaseInterface::class);

		try
		{
			$result = (new PhotoEditor($db, new Settings()))->apply($user, $id, $delete, $priv, $files, \is_array($_POST['captions'] ?? null) ? $_POST['captions'] : [], \is_array($_POST['priv'] ?? null) ? $_POST['priv'] : []);
		}
		catch (SubmissionException $e)
		{
			$this->refuse($e);
		}
		catch (\Throwable $e)
		{
			EventLog::add($db, (int) $user->id, 'error', 'photos');
			$this->json(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_SERVER')], 500);
		}

		$this->json(['ok' => true] + $result + ['message' => Text::_($result['pending'] ? 'COM_MAPAVIAJES_PHOTOS_ADDED_REVIEW' : 'COM_MAPAVIAJES_PHOTOS_SAVED')], 200);
	}

	private function intOf(mixed $raw): int
	{
		return (\is_string($raw) && preg_match('/^\d{1,9}$/', $raw) === 1) ? (int) $raw : 0;
	}

	/** Flags a trip for the moderators (notice-and-action). POST: id, reason, note. Same gates as the other writes. */
	public function report(): void
	{
		$app  = $this->app;
		$user = $app->getIdentity();

		if (strtoupper((string) $app->getInput()->getMethod()) !== 'POST')
		{
			header('Allow: POST');
			$this->json(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_GENERIC')], 405);
		}

		if (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) !== 'xmlhttprequest' || !Session::checkToken('post'))
		{
			$this->json(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_SESSION'), 'expired' => true], 403);
		}

		if ($user->guest)
		{
			$this->json(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_LOGIN'), 'expired' => true], 403);
		}

		$raw    = $_POST['id'] ?? '';
		$id     = (\is_string($raw) && preg_match('/^\d{1,9}$/', $raw) === 1) ? (int) $raw : 0;
		$reason = \is_string($_POST['reason'] ?? null) ? (string) $_POST['reason'] : '';
		$note   = \is_string($_POST['note'] ?? null) ? (string) $_POST['note'] : '';
		$db     = Factory::getContainer()->get(DatabaseInterface::class);

		try
		{
			(new ReportService($db, new Settings()))->add($user, $id, $reason, $note);
		}
		catch (SubmissionException $e)
		{
			$key = 'COM_MAPAVIAJES_ERR_' . strtoupper($e->key());
			$msg = Text::_($key);

			$this->json(['ok' => false, 'error' => $msg !== $key ? $msg : Text::_('COM_MAPAVIAJES_ERR_GENERIC')], $e->status());
		}
		catch (\Throwable $e)
		{
			EventLog::add($db, (int) $user->id, 'error', 'report');
			$this->json(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_SERVER')], 500);
		}

		$this->json(['ok' => true, 'message' => Text::_('COM_MAPAVIAJES_JS_REPORT_DONE')]);
	}

	/** Opens or closes the comments of one trip (its author, or an administrator). POST: id, on = 0|1. Same gates as the other writes. */
	public function comments(): void
	{
		$app  = $this->app;
		$user = $app->getIdentity();

		if (strtoupper((string) $app->getInput()->getMethod()) !== 'POST')
		{
			header('Allow: POST');
			$this->json(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_GENERIC')], 405);
		}

		if (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) !== 'xmlhttprequest' || !Session::checkToken('post'))
		{
			$this->json(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_SESSION'), 'expired' => true], 403);
		}

		if ($user->guest)
		{
			$this->json(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_LOGIN'), 'expired' => true], 403);
		}

		$raw = $_POST['id'] ?? '';
		$on  = $_POST['on'] ?? '';
		$id  = (\is_string($raw) && preg_match('/^\d{1,9}$/', $raw) === 1) ? (int) $raw : 0;

		if ($on !== '0' && $on !== '1')
		{
			$this->json(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_GENERIC')], 400);
		}

		try
		{
			(new TripCommentsSwitch(Factory::getContainer()->get(DatabaseInterface::class)))->set($user, $id, $on === '1');
		}
		catch (SubmissionException $e)
		{
			$this->json(['ok' => false, 'error' => Text::_($e->key() === 'login' ? 'COM_MAPAVIAJES_ERR_LOGIN' : 'COM_MAPAVIAJES_ERR_TRIP_GONE')], $e->status());
		}
		catch (\Throwable $e)
		{
			$this->json(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_SERVER')], 500);
		}

		$this->json(['ok' => true, 'id' => $id, 'on' => (int) $on]);
	}

	/**
	 * Raw $_POST, no Joomla filtering: TextSanitizer is stricter than any generic filter and must see the original.
	 *
	 * @return array<string, mixed>
	 */
	private function postFields(): array
	{
		$out = [];

		foreach (['album_id', 'trip_year', 'title', 'place', 'lat', 'lon', 'allow_comments', 'notify', 'website', 'rights'] as $key)
		{
			$out[$key] = $_POST[$key] ?? '';
		}

		$out['captions'] = $_POST['captions'] ?? [];
		$out['priv']     = $_POST['priv'] ?? [];

		return $out;
	}

	/**
	 * @return array<int, array<string, mixed>> uploads from photos[], in order, ignoring empty slots
	 */
	private function uploads(): array
	{
		$f = $_FILES['photos'] ?? null;

		if (!\is_array($f) || !isset($f['name'], $f['tmp_name'], $f['error']) || !\is_array($f['name']) || !\is_array($f['tmp_name']) || !\is_array($f['error']))
		{
			return [];
		}

		$out = [];

		foreach ($f['name'] as $i => $name)
		{
			if (!\is_int($i) || $i > 50)
			{
				continue;
			}

			$error = $f['error'][$i] ?? UPLOAD_ERR_NO_FILE;
			$tmp   = $f['tmp_name'][$i] ?? '';

			if ($error === UPLOAD_ERR_NO_FILE)
			{
				continue;
			}

			$out[] = ['name' => \is_string($name) ? $name : '', 'tmp_name' => \is_string($tmp) ? $tmp : '', 'error' => (int) $error, 'size' => 0];
		}

		return $out;
	}
}
