<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\View\Album;

defined('_JEXEC') || die;

use Chaska\Component\Mapaviajes\Administrator\Model\AlbumModel;
use Chaska\Component\Mapaviajes\Administrator\Service\Settings;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
	protected $form;
	protected $item;
	protected $state;
	/** The current logo as a data: URI for the preview (the back end has no public URL for private files); '' when there is none. */
	protected string $logoPreview = '';

	public function display($tpl = null): void
	{
		$model       = $this->getModel();
		$this->form  = $model->getForm();
		$this->item  = $model->getItem();
		$this->state = $model->getState();

		$logo = (string) ($this->item->logo ?? '');
		$path = $logo !== '' ? (new Settings())->storage()->pathFor($logo) : null;

		if ($path !== null && (int) filesize($path) <= AlbumModel::LOGO_MAX_BYTES)
		{
			$this->logoPreview = 'data:image/png;base64,' . base64_encode((string) file_get_contents($path));
		}

		Factory::getApplication()->getInput()->set('hidemainmenu', true);
		$isNew = (int) ($this->item->id ?? 0) === 0;

		ToolbarHelper::title(Text::_($isNew ? 'COM_MAPAVIAJES_ALBUM_NEW' : 'COM_MAPAVIAJES_ALBUM_EDIT'), 'globe');
		ToolbarHelper::apply('album.apply');
		ToolbarHelper::save('album.save');
		ToolbarHelper::cancel('album.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');

		parent::display($tpl);
	}
}
