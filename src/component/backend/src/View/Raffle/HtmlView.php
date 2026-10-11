<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\View\Raffle;

defined('_JEXEC') || die;

use Chaska\Component\Mapaviajes\Administrator\Service\RaffleService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;

class HtmlView extends BaseHtmlView
{
	/** @var array{from: string, to: string, min: int, album: int} */
	protected array $f = ['from' => '', 'to' => '', 'min' => 1, 'album' => 0];
	/** @var list<array<string, mixed>> */
	protected array $people = [];
	/** @var list<array<string, mixed>> */
	protected array $albums = [];
	/** @var list<array<string, mixed>> */
	protected array $winners = [];

	public function display($tpl = null): void
	{
		$app = Factory::getApplication();

		if (!$app->getIdentity()->authorise('core.edit.state', 'com_mapaviajes'))
		{
			throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$db            = Factory::getContainer()->get(DatabaseInterface::class);
		$this->f       = RaffleService::filters($_GET);
		$this->people  = (new RaffleService($db))->participants($this->f);
		$this->albums  = $db->setQuery($db->getQuery(true)->select($db->quoteName(['id', 'title']))->from($db->quoteName('#__mapaviajes_albums'))->order($db->quoteName('ordering') . ' ASC'))->loadAssocList() ?: [];
		$this->winners = (array) $app->getUserState('com_mapaviajes.raffle.winners', []);
		$app->setUserState('com_mapaviajes.raffle.winners', null); // shown once

		ToolbarHelper::title(Text::_('COM_MAPAVIAJES_MENU_RAFFLE'), 'users');
		$this->getDocument()->getWebAssetManager()->useStyle('com_mapaviajes.admin');

		parent::display($tpl);
	}
}
