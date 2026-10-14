<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\Controller;

defined('_JEXEC') || die;

use Joomla\CMS\MVC\Controller\BaseController;

class DisplayController extends BaseController
{
	protected $default_view = 'cpanel';

	public function display($cachable = false, $urlparams = [])
	{
		// Only our own views, and only the layouts they really have: nothing the URL can steer into a file include.
		$view = (string) $this->input->get('view', 'cpanel', 'cmd');
		$view = \in_array($view, ['cpanel', 'trips', 'albums', 'album', 'ranking', 'raffle', 'settings'], true) ? $view : 'cpanel';
		$this->input->set('view', $view);
		// Our screens bring their own menu and title bar: Joomla's side menu and top bar are not shown (the menu has a way back to Joomla)
		$this->input->set('tmpl', 'component');
		$layout = (string) $this->input->get('layout', 'default', 'cmd');
		$this->input->set('layout', ($view === 'album' && $layout === 'edit') ? 'edit' : 'default');

		return parent::display($cachable, $urlparams);
	}
}
