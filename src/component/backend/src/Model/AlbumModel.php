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
use Chaska\Component\Mapaviajes\Administrator\Security\ImageProcessor;
use Chaska\Component\Mapaviajes\Administrator\Security\SanitizeException;
use Chaska\Component\Mapaviajes\Administrator\Service\Settings;
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

	/**
	 * The design shows the logo in a square box of 52 CSS px on a computer and 36 CSS px on a phone (<= 720 px wide).
	 * What is KEPT is at most 160 px on the long side: 3 device pixels per CSS pixel on the biggest box (52 x 3 = 156), so it is
	 * sharp on any retina screen and weighs a few kilobytes, however big the file that was uploaded. Aspect ratio untouched.
	 * What is ACCEPTED (so that a big file is shrunk instead of refused) is bounded by the three limits below.
	 */
	public const LOGO_BOX_DESKTOP = 177;
	public const LOGO_BOX_MOBILE  = 108;
	public const LOGO_MAX_BYTES   = 5 * 1048576;
	public const LOGO_SIDE        = 512;
	public const LOGO_MAX_SIDE  = 4096;
	public const LOGO_MAX_PIXELS = 16000000;

	/**
	 * Saves the map and, if a logo came with it, turns that logo into a new, harmless WebP (PNG transparency kept,
	 * long side at most 512 px, aspect ratio untouched) BEFORE anything is stored: a refused logo refuses the whole save,
	 * so the form is shown again with the reason and the old logo is still there. The file name is never taken from the form.
	 */
	public function save($data)
	{
		unset($data['logo']); // the column is ours alone

		// Creating or editing a map does not include publishing it: that is its own permission
		if (!$this->getCurrentUser()->authorise('core.edit.state', 'com_mapaviajes'))
		{
			unset($data['published']);
		}

		$storage = (new Settings())->storage();
		$storage->prepare();
		$tmp     = null;
		$file    = $this->uploadedLogo();

		if ($file !== null)
		{
			$tmp = $storage->newTemporaryPath();

			try
			{
				(new ImageProcessor(self::LOGO_MAX_BYTES, self::LOGO_SIDE, 100, false, self::LOGO_MAX_PIXELS, self::LOGO_MAX_SIDE, null, false, true))->process($file, $tmp);
			}
			catch (SanitizeException $e)
			{
				$this->setError(Text::_('COM_MAPAVIAJES_ERR_' . strtoupper($e->getReason())));

				return false;
			}
		}

		$remove = !empty($_POST['logo_remove']) && $_POST['logo_remove'] === '1';

		if (!parent::save($data))
		{
			if ($tmp !== null)
			{
				$storage->discard($tmp);
			}

			return false;
		}

		$id = (int) $this->getState($this->getName() . '.id');

		if ($id > 0 && ($tmp !== null || $remove))
		{
			$db  = $this->getDatabase();
			$old = (string) $db->setQuery($db->getQuery(true)->select($db->quoteName('logo'))->from($db->quoteName('#__mapaviajes_albums'))->where($db->quoteName('id') . ' = :i')->bind(':i', $id, ParameterType::INTEGER))->loadResult();
			$new = $tmp !== null ? $storage->commitLogo($tmp) : '';
			$bind = $new;
			$id2  = $id;
			$db->setQuery($db->getQuery(true)->update($db->quoteName('#__mapaviajes_albums'))->set($db->quoteName('logo') . ' = :l')->where($db->quoteName('id') . ' = :i')->bind(':l', $bind)->bind(':i', $id2, ParameterType::INTEGER))->execute();

			if ($old !== '' && $old !== $new)
			{
				$storage->delete($old);
			}
		}

		return true;
	}

	/** The upload field "logo_upload", flattened and checked to be exactly one file (a list, nested names or nothing give null). */
	private function uploadedLogo(): ?array
	{
		$f = $_FILES['logo_upload'] ?? null;

		if (!\is_array($f) || !isset($f['error']) || \is_array($f['error']) || \is_array($f['tmp_name'] ?? null) || (int) $f['error'] === UPLOAD_ERR_NO_FILE)
		{
			return null;
		}

		return ['name' => (string) ($f['name'] ?? ''), 'tmp_name' => (string) ($f['tmp_name'] ?? ''), 'error' => (int) $f['error'], 'size' => (int) ($f['size'] ?? 0), 'type' => ''];
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

	/** Deleting a map also deletes its logo file (only for the maps Joomla really deleted). */
	public function delete(&$pks)
	{
		$pks   = array_values(array_filter(array_map('intval', (array) $pks)));
		$db    = $this->getDatabase();
		$logos = [];

		foreach ($pks as $pk)
		{
			$id = $pk;
			$logos[$pk] = (string) $db->setQuery($db->getQuery(true)->select($db->quoteName('logo'))->from($db->quoteName('#__mapaviajes_albums'))->where($db->quoteName('id') . ' = :i')->bind(':i', $id, ParameterType::INTEGER))->loadResult();
		}

		$result  = parent::delete($pks);
		$storage = (new Settings())->storage();

		foreach ($logos as $pk => $name)
		{
			$c      = $pk;
			$exists = (int) $db->setQuery($db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__mapaviajes_albums'))->where($db->quoteName('id') . ' = :c')->bind(':c', $c, ParameterType::INTEGER))->loadResult();

			if ($exists === 0 && $name !== '')
			{
				$storage->delete($name);
			}
		}

		return $result;
	}
}
