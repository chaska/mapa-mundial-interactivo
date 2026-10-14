<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\View\Albums;

defined('_JEXEC') || die;

use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
	public $filterForm;
	public $activeFilters = [];
	protected $items = [];
	protected $pagination;
	protected $state;

	/** @var array<int, int> albums per state (0 unpublished, 1 published, -2 trashed) */
	protected array $counts = [];

	public function display($tpl = null): void
	{
		$model = $this->getModel();
		$model->setUseExceptions(true);

		$this->items         = $model->getItems();
		$this->pagination    = $model->getPagination();
		$this->state         = $model->getState();
		$this->filterForm    = $model->getFilterForm();
		$this->activeFilters = $model->getActiveFilters();

		$db = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

		foreach ($db->setQuery('SELECT ' . $db->quoteName('published') . ' AS s, COUNT(*) AS n FROM ' . $db->quoteName('#__mapaviajes_albums') . ' GROUP BY ' . $db->quoteName('published'))->loadAssocList() ?: [] as $row)
		{
			$this->counts[(int) $row['s']] = (int) $row['n'];
		}

		$canDo   = ContentHelper::getActions('com_mapaviajes');
		$toolbar = $this->getDocument()->getToolbar();

		ToolbarHelper::title(Text::_('COM_MAPAVIAJES_ALBUMS_TITLE'), 'globe');

		if ($canDo->get('core.create'))
		{
			$toolbar->addNew('album.add');
		}

		if ($canDo->get('core.edit.state'))
		{
			$dropdown = $toolbar->dropdownButton('status-group', 'JTOOLBAR_CHANGE_STATUS')->toggleSplit(false)->icon('icon-ellipsis-h')->buttonClass('btn btn-action')->listCheck(true);
			$child    = $dropdown->getChildToolbar();
			$child->publish('albums.publish')->listCheck(true);
			$child->unpublish('albums.unpublish')->listCheck(true);
			$child->trash('albums.trash')->listCheck(true);
		}

		if ((string) $this->state->get('filter.published') === '-2' && $canDo->get('core.delete'))
		{
			$toolbar->delete('albums.delete', 'JTOOLBAR_DELETE_FROM_TRASH')->message('JGLOBAL_CONFIRM_DELETE')->listCheck(true);
		}

		if ($canDo->get('core.admin') || $canDo->get('core.options'))
		{
			$toolbar->preferences('com_mapaviajes');
		}

		$this->filterForm->addControlField('task')->addControlField('boxchecked', '0');

		parent::display($tpl);
	}
}
