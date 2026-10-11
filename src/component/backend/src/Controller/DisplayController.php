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
		$view = \in_array($view, ['cpanel', 'trips', 'albums', 'album', 'ranking', 'raffle'], true) ? $view : 'cpanel';
		$this->input->set('view', $view);
		$layout = (string) $this->input->get('layout', 'default', 'cmd');
		$this->input->set('layout', ($view === 'album' && $layout === 'edit') ? 'edit' : 'default');

		return parent::display($cachable, $urlparams);
	}
}
