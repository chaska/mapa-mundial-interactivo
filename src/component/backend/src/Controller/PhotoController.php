<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\Controller;

defined('_JEXEC') || die;

use Chaska\Component\Mapaviajes\Administrator\Service\PhotoServer;
use Chaska\Component\Mapaviajes\Administrator\Service\Settings;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Database\DatabaseInterface;

/** Moderators see every photo (any state) so they can judge it. Anyone else: the same empty 404. */
class PhotoController extends BaseController
{
	public function view(): void
	{
		$user = $this->app->getIdentity();
		$id   = (int) preg_replace('/\D/', '', substr((string) ($_GET['id'] ?? ''), 0, 9));
		$ok   = !$user->guest && $user->authorise('core.manage', 'com_mapaviajes');

		PhotoServer::send($this->app, Factory::getContainer()->get(DatabaseInterface::class), new Settings(), $ok ? $id : 0, static fn (array $g): bool => true);
	}
}
