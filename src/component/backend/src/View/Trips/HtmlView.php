<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\View\Trips;

defined('_JEXEC') || die;

use Chaska\Component\Mapaviajes\Administrator\Security\ImageProcessor;
use Chaska\Component\Mapaviajes\Administrator\Service\Settings;
use Joomla\CMS\Factory;
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

	/** @var string[] */
	protected array $warnings = [];

	public function display($tpl = null): void
	{
		$model = $this->getModel();
		$model->setUseExceptions(true);

		$this->items         = $model->getItems();
		$this->pagination    = $model->getPagination();
		$this->state         = $model->getState();
		$this->filterForm    = $model->getFilterForm();
		$this->activeFilters = $model->getActiveFilters();

		if (version_compare(JVERSION, '6.0.0', '>=') ? version_compare(JVERSION, '6.1.4', '<') : version_compare(JVERSION, '5.4.9', '<'))
		{
			$this->warnings[] = Text::sprintf('COM_MAPAVIAJES_WARN_JOOMLA', JVERSION);
		}

		if (!ImageProcessor::isSupported())
		{
			$this->warnings[] = Text::_('COM_MAPAVIAJES_WARN_GD');
		}

		try
		{
			$storage = (new Settings())->storage();
			$storage->prepare();
		}
		catch (\Throwable $e)
		{
			$this->warnings[] = Text::_('COM_MAPAVIAJES_WARN_STORAGE');
		}

		$this->addToolbar();
		$this->filterForm->addControlField('task')->addControlField('boxchecked', '0');

		parent::display($tpl);
	}

	protected function addToolbar(): void
	{
		$canDo   = ContentHelper::getActions('com_mapaviajes');
		$toolbar = $this->getDocument()->getToolbar();

		ToolbarHelper::title(Text::_('COM_MAPAVIAJES_TRIPS_TITLE'), 'globe');

		if ($canDo->get('core.edit.state'))
		{
			$dropdown = $toolbar->dropdownButton('status-group', 'JTOOLBAR_CHANGE_STATUS')->toggleSplit(false)->icon('icon-ellipsis-h')->buttonClass('btn btn-action')->listCheck(true);
			$child    = $dropdown->getChildToolbar();
			$child->publish('trips.publish')->listCheck(true);
			$child->unpublish('trips.unpublish')->listCheck(true);
			$child->archive('trips.archive', 'COM_MAPAVIAJES_BTN_HIDE')->listCheck(true);
			$child->standardButton('spam', 'COM_MAPAVIAJES_BTN_SPAM', 'trips.report')->icon('icon-ban')->listCheck(true);

			if ((string) $this->state->get('filter.published') !== '-2')
			{
				$child->trash('trips.trash')->listCheck(true);
			}
		}

		if ((string) $this->state->get('filter.published') === '-2' && $canDo->get('core.delete'))
		{
			$toolbar->delete('trips.delete', 'JTOOLBAR_DELETE_FROM_TRASH')->message('JGLOBAL_CONFIRM_DELETE')->listCheck(true);
		}

		if ($canDo->get('core.admin') || $canDo->get('core.options'))
		{
			$toolbar->preferences('com_mapaviajes');
		}
	}
}
