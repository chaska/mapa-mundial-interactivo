<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\Controller;

defined('_JEXEC') || die;

use Joomla\CMS\MVC\Controller\AdminController;

class AlbumsController extends AdminController
{
	protected $text_prefix = 'COM_MAPAVIAJES_ALBUMS';

	public function getModel($name = 'Album', $prefix = 'Administrator', $config = ['ignore_request' => true])
	{
		return parent::getModel($name, $prefix, $config);
	}
}
