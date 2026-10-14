<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\Controller;

defined('_JEXEC') || die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Joomla\Database\ParameterType;

/** publish / unpublish / archive (= hide) / trash / report (= spam) / delete: the standard Joomla list actions. */
class TripsController extends AdminController
{
	protected $text_prefix = 'COM_MAPAVIAJES_TRIPS';

	/** Batch (copy / move / change access in bulk) is not offered: a copy would skip the submission limits and the text and photo checks. */
	public function batch($model = null)
	{
		$this->setRedirect(\Joomla\CMS\Router\Route::_('index.php?option=com_mapaviajes&view=trips', false), \Joomla\CMS\Language\Text::_('JERROR_ALERTNOAUTHOR'), 'error');

		return false;
	}

	public function getModel($name = 'Trip', $prefix = 'Administrator', $config = ['ignore_request' => true])
	{
		return parent::getModel($name, $prefix, $config);
	}

	/**
	 * Delete for good in one step: trashes the selected trips and then deletes them, which also removes their photos, their
	 * reactions and their comment thread (the same deletion as "Delete from trash").
	 */
	public function purge(): void
	{
		$this->checkToken();

		if (!$this->app->getIdentity()->authorise('core.delete', 'com_mapaviajes'))
		{
			$this->app->enqueueMessage(Text::_('JERROR_CORE_DELETE_NOT_PERMITTED'), 'error');
			$this->setRedirect(Route::_('index.php?option=com_mapaviajes&view=trips', false));

			return;
		}

		$ids = array_values(array_filter(array_map('intval', (array) $this->input->get('cid', [], 'array'))));

		if ($ids === [])
		{
			$this->app->enqueueMessage(Text::_('JERROR_NO_ITEMS_SELECTED'), 'warning');
			$this->setRedirect(Route::_('index.php?option=com_mapaviajes&view=trips', false));

			return;
		}

		$db = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
		$q  = $db->getQuery(true)->update($db->quoteName('#__mapaviajes_trips'))->set($db->quoteName('published') . ' = -2')->whereIn($db->quoteName('id'), $ids);
		$db->setQuery($q)->execute();

		$model = $this->getModel();
		$model->delete($ids);
		$this->app->enqueueMessage(Text::plural('COM_MAPAVIAJES_TRIPS_N_PURGED', \count($ids)));
		$this->setRedirect(Route::_('index.php?option=com_mapaviajes&view=trips', false));
	}
}
