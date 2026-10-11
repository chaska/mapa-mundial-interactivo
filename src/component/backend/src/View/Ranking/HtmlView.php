<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\View\Ranking;

defined('_JEXEC') || die;

use Chaska\Component\Mapaviajes\Administrator\Service\RankingService;
use Chaska\Component\Mapaviajes\Administrator\Service\Settings;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;

class HtmlView extends BaseHtmlView
{
	/** @var list<string> */
	protected array $weeks = [];
	protected string $week = '';
	/** @var list<array<string, mixed>> */
	protected array $rows = [];
	/** @var list<array<string, mixed>> */
	protected array $all = [];
	protected bool $enabled = true;

	public function display($tpl = null): void
	{
		$db  = Factory::getContainer()->get(DatabaseInterface::class);
		$set = new Settings();
		$svc = new RankingService($db, $set);
		$this->enabled = $set->rankEnabled();
		$this->weeks   = array_map('strval', $db->setQuery($db->getQuery(true)->select($db->quoteName('week_start'))->from($db->quoteName('#__mapaviajes_weeks'))->order($db->quoteName('week_start') . ' DESC'), 0, 52)->loadColumn() ?: []);
		$asked         = $_GET['week'] ?? '';
		$this->week    = (\is_string($asked) && \in_array($asked, $this->weeks, true)) ? $asked : ($this->weeks[0] ?? '');
		$this->rows    = $this->week !== '' ? ($svc->closedWeek($this->week) ?? []) : [];
		$this->all     = $svc->allTime(25);

		ToolbarHelper::title(Text::_('COM_MAPAVIAJES_MENU_RANKING'), 'star');

		parent::display($tpl);
	}
}
