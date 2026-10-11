<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\Model;

defined('_JEXEC') || die;

use Chaska\Component\Mapaviajes\Administrator\Service\CommentsBridge;
use Chaska\Component\Mapaviajes\Administrator\Service\Settings;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\Database\ParameterType;

class TripModel extends AdminModel
{
	public function getTable($name = 'Trip', $prefix = 'Administrator', $options = [])
	{
		return parent::getTable($name, $prefix, $options);
	}

	public function getForm($data = [], $loadData = true)
	{
		return false; // trips are moderated, not edited
	}

	/** Only a trashed trip can be deleted, and only by someone allowed to delete. */
	protected function canDelete($record)
	{
		return !empty($record->id) && (int) $record->published === -2 && $this->getCurrentUser()->authorise('core.delete', 'com_mapaviajes');
	}

	/**
	 * Deleting a trip also deletes its photos, but ONLY for the trips Joomla really deleted (a request may mix
	 * trashed and non-trashed ids). Rows first, then the files, so nothing is left orphaned either way.
	 */
	public function delete(&$pks)
	{
		$pks = array_values(array_filter(array_map('intval', (array) $pks)));
		$db  = $this->getDatabase();
		$files = [];
		$assets = [];

		foreach ($pks as $pk)
		{
			$aq = $pk;
			$assets[$pk] = (int) $db->setQuery($db->getQuery(true)->select($db->quoteName('asset_id'))->from($db->quoteName('#__mapaviajes_trips'))->where($db->quoteName('id') . ' = :aq')->bind(':aq', $aq, ParameterType::INTEGER))->loadResult();
			$query = $db->getQuery(true)->select($db->quoteName('filename'))->from($db->quoteName('#__mapaviajes_photos'))->where($db->quoteName('trip_id') . ' = :t')->bind(':t', $pk, ParameterType::INTEGER);
			$files[$pk] = $db->setQuery($query)->loadColumn() ?: [];
		}

		$result  = parent::delete($pks);
		$storage = (new Settings())->storage();

		foreach ($files as $pk => $names)
		{
			$check  = $pk;
			$exists = (int) $db->setQuery($db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__mapaviajes_trips'))->where($db->quoteName('id') . ' = :c')->bind(':c', $check, ParameterType::INTEGER))->loadResult();

			if ($exists === 0)
			{
				$del = $pk;
				$db->setQuery($db->getQuery(true)->delete($db->quoteName('#__mapaviajes_photos'))->where($db->quoteName('trip_id') . ' = :d')->bind(':d', $del, ParameterType::INTEGER))->execute();
				$del2 = $pk;
				$db->setQuery($db->getQuery(true)->delete($db->quoteName('#__mapaviajes_reactions'))->where($db->quoteName('trip_id') . ' = :d2')->bind(':d2', $del2, ParameterType::INTEGER))->execute();

				foreach ($names as $name)
				{
					$storage->delete((string) $name);
				}

				// its comment thread and its asset row (whatever comment system was in use)
				(new CommentsBridge($db, new Settings()))->forget([$assets[$pk] ?? 0]);
			}
		}

		return $result;
	}
}
