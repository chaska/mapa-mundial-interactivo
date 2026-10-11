<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\Model;

defined('_JEXEC') || die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\AdminModel;

class AlbumModel extends AdminModel
{
	public function getTable($name = 'Album', $prefix = 'Administrator', $options = [])
	{
		return parent::getTable($name, $prefix, $options);
	}

	public function getForm($data = [], $loadData = true)
	{
		return $this->loadForm('com_mapaviajes.album', 'album', ['control' => 'jform', 'load_data' => $loadData]) ?: false;
	}

	protected function loadFormData()
	{
		$data = Factory::getApplication()->getUserState('com_mapaviajes.edit.album.data', []);

		return empty($data) ? $this->getItem() : $data;
	}
}
