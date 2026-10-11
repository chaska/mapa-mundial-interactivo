<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Site\Controller;

defined('_JEXEC') || die;

use Chaska\Component\Mapaviajes\Administrator\Service\PhotoServer;
use Chaska\Component\Mapaviajes\Administrator\Service\Settings;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Database\DatabaseInterface;

/** GET ?option=com_mapaviajes&task=photo.view&id=N */
class PhotoController extends BaseController
{
	public function view(): void
	{
		$user = $this->app->getIdentity();
		$id   = (int) preg_replace('/\D/', '', substr((string) ($_GET['id'] ?? ''), 0, 9));

		$levels = array_map('intval', $user->getAuthorisedViewLevels());
		$uid    = (int) $user->id;

		PhotoServer::send(
			$this->app,
			Factory::getContainer()->get(DatabaseInterface::class),
			new Settings(),
			$user->guest ? 0 : $id,
			static fn (array $g): bool => (int) $g['album_published'] === 1
				&& \in_array((int) $g['album_access'], $levels, true)
				&& ((int) $g['trip_published'] === 1 || ((int) $g['trip_user'] === $uid && (int) $g['trip_published'] === 0))
		);
	}

	/** GET ?option=com_mapaviajes&task=photo.logo&id=ALBUM : the logo of a map, for people who may see that map. */
	public function logo(): void
	{
		$user = $this->app->getIdentity();
		$id   = (int) preg_replace('/\D/', '', substr((string) ($_GET['id'] ?? ''), 0, 9));
		$lv   = array_map('intval', $user->getAuthorisedViewLevels());

		PhotoServer::sendLogo(
			$this->app,
			Factory::getContainer()->get(DatabaseInterface::class),
			new Settings(),
			$user->guest ? 0 : $id,
			static fn (array $g): bool => (int) $g['published'] === 1 && \in_array((int) $g['access'], $lv, true)
		);
	}
}
