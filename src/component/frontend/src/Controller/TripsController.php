<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Site\Controller;

defined('_JEXEC') || die;

use Chaska\Component\Mapaviajes\Administrator\Service\Settings;
use Chaska\Component\Mapaviajes\Administrator\Service\TripRepository;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Database\DatabaseInterface;

/** GET ?option=com_mapaviajes&task=trips.data&album=ID  → what the map draws. Registered users only. */
class TripsController extends BaseController
{
	use JsonTrait;

	public function data(): void
	{
		$user = $this->app->getIdentity();

		if ($user->guest)
		{
			$this->json(['ok' => false, 'expired' => true], 403);
		}

		$albumId = (int) preg_replace('/\D/', '', substr((string) ($_GET['album'] ?? ''), 0, 9));
		$repo    = new TripRepository(Factory::getContainer()->get(DatabaseInterface::class));
		$albums  = array_filter($repo->albumsFor($user), static fn (array $a): bool => (int) $a['id'] === $albumId);

		if ($albums === [])
		{
			$this->json(['ok' => false], 404);
		}

		$db = Factory::getContainer()->get(DatabaseInterface::class);

		try
		{
			(new \Chaska\Component\Mapaviajes\Administrator\Service\Notifier($db, new Settings()))->followDigest();
		}
		catch (\Throwable $e)
		{
			// a mail problem never stops the map
		}

		$authors = [];
		$trips   = $repo->forMap($user, $albumId, 3000, (new Settings())->showRealNames(), $authors);
		$photos  = new \Chaska\Component\Mapaviajes\Administrator\Service\ProfilePhoto();
		$urls    = $photos->urls(array_values($authors), $user, 64);
		$av      = [];

		foreach ($authors as $key => $id)
		{
			if (isset($urls[$id]))
			{
				$av[$key] = $urls[$id];
			}
		}

		$this->json(['ok' => true, 'trips' => $trips, 'av' => (object) $av, 'follow' => (new \Chaska\Component\Mapaviajes\Administrator\Service\FollowService($db))->state((int) $user->id), 'friend' => (new \Chaska\Component\Mapaviajes\Administrator\Service\FriendService($db))->state((int) $user->id, (new Settings())->showRealNames(), $photos)]);
	}
}
