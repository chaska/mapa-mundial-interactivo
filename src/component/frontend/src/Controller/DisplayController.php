<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Site\Controller;

defined('_JEXEC') || die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

class DisplayController extends BaseController
{
	protected $default_view = 'map';

	public function display($cachable = false, $urlparams = [])
	{
		// Only registered users see the map. Guests are sent to the login and brought back here.
		if ($this->app->getIdentity()->guest)
		{
			$return = base64_encode(Uri::getInstance()->toString(['path', 'query']));
			$this->app->redirect(Route::_('index.php?option=com_users&view=login&return=' . $return, false));

			return $this;
		}

		// Two pages exist: the map, and the comments of one trip (its view is named tripcomments because Joomla caches views by NAME across controllers and Engage owns the name "comments") (shown inside a panel of the map). Nothing else can be asked for.
		$view = $this->input->getCmd('view', 'map') === 'tripcomments' ? 'tripcomments' : 'map';
		$this->input->set('view', $view);
		$this->input->set('layout', 'default'); // never a layout chosen by the request (core LFI via ?layout=, CVE-2026-40383)
		$this->input->set('tmpl', 'component'); // the map is an app: no site header or footer around it

		return parent::display(false, []);
	}
}
