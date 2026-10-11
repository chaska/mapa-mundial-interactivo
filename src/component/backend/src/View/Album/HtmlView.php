<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\View\Album;

defined('_JEXEC') || die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
	protected $form;
	protected $item;
	protected $state;

	public function display($tpl = null): void
	{
		$model       = $this->getModel();
		$this->form  = $model->getForm();
		$this->item  = $model->getItem();
		$this->state = $model->getState();

		Factory::getApplication()->getInput()->set('hidemainmenu', true);
		$isNew = (int) ($this->item->id ?? 0) === 0;

		ToolbarHelper::title(Text::_($isNew ? 'COM_MAPAVIAJES_ALBUM_NEW' : 'COM_MAPAVIAJES_ALBUM_EDIT'), 'globe');
		ToolbarHelper::apply('album.apply');
		ToolbarHelper::save('album.save');
		ToolbarHelper::cancel('album.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');

		parent::display($tpl);
	}
}
