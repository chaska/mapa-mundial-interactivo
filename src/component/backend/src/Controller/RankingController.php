<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\Controller;

defined('_JEXEC') || die;

use Chaska\Component\Mapaviajes\Administrator\Service\Notifier;
use Chaska\Component\Mapaviajes\Administrator\Service\RankingService;
use Chaska\Component\Mapaviajes\Administrator\Service\Settings;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;

class RankingController extends BaseController
{
	/** Closes every finished week that is still open (the map does it by itself the first time somebody opens the podium). */
	public function close(): void
	{
		$this->checkToken('post');

		if (!$this->app->getIdentity()->authorise('core.manage', 'com_mapaviajes'))
		{
			throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$db       = Factory::getContainer()->get(DatabaseInterface::class);
		$settings = new Settings();
		$svc      = new RankingService($db, $settings);
		$weeks    = $svc->closePending();

		foreach ($weeks as $w)
		{
			(new Notifier($db, $settings))->podium($w, $svc->closedWeek($w) ?? []);
		}

		$this->setRedirect(Route::_('index.php?option=com_mapaviajes&view=ranking', false), Text::plural('COM_MAPAVIAJES_RANKING_CLOSED_N', \count($weeks)));
	}
}
