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
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\Database\ParameterType;

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

	/** A trashed album may be deleted only when no trip is left in it: nothing is orphaned, no photo file is forgotten. */
	protected function canDelete($record)
	{
		if (empty($record->id) || (int) $record->published !== -2 || !$this->getCurrentUser()->authorise('core.delete', 'com_mapaviajes'))
		{
			return false;
		}

		$db      = $this->getDatabase();
		$albumId = (int) $record->id;
		$count   = (int) $db->setQuery($db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__mapaviajes_trips'))->where($db->quoteName('album_id') . ' = :a')->bind(':a', $albumId, ParameterType::INTEGER))->loadResult();

		if ($count > 0)
		{
			$this->setError(Text::sprintf('COM_MAPAVIAJES_ERR_ALBUM_HAS_TRIPS', $count));
			Factory::getApplication()->enqueueMessage(Text::sprintf('COM_MAPAVIAJES_ERR_ALBUM_HAS_TRIPS', $count), 'warning');

			return false;
		}

		return true;
	}
}
