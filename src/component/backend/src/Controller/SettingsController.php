<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\Controller;

defined('_JEXEC') || die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** Saves the options form of our own Options screen into the component parameters (same place Joomla's screen writes to). */
class SettingsController extends BaseController
{
	public function save($key = null, $urlVar = null)
	{
		$back = Route::_('index.php?option=com_mapaviajes&view=settings', false);

		if (!Session::checkToken('post'))
		{
			$this->setRedirect($back, Text::_('JINVALID_TOKEN_NOTICE'), 'error');

			return false;
		}

		$user = $this->app->getIdentity();

		if (!$user->authorise('core.options', 'com_mapaviajes') && !$user->authorise('core.admin', 'com_mapaviajes'))
		{
			$this->setRedirect($back, Text::_('JERROR_ALERTNOAUTHOR'), 'error');

			return false;
		}

		$form = Form::getInstance('com_mapaviajes.options.save', JPATH_ADMINISTRATOR . '/components/com_mapaviajes/config.xml', ['control' => 'jform'], false, '/config');
		$data = $this->input->post->get('jform', [], 'array');
		unset($data['rules']); // the permissions table is saved by Joomla's own screen
		$data = $form->filter($data);
		$ok   = $form->validate($data);

		if ($ok === false)
		{
			$errors = array_map(static fn ($e) => $e instanceof \Exception ? $e->getMessage() : (string) $e, $form->getErrors());
			$this->setRedirect($back, implode(' · ', $errors), 'error');

			return false;
		}

		$params = clone ComponentHelper::getParams('com_mapaviajes');

		// Only the fields the form defines (never rules or anything unknown), over what is stored
		foreach ($form->getFieldsets() as $name => $set)
		{
			if ($name === 'permissions')
			{
				continue;
			}

			foreach ($form->getFieldset($name) as $field)
			{
				$short = (string) $field->fieldname;

				if (\array_key_exists($short, $data))
				{
					$params->set($short, $data[$short]);
				}
			}
		}

		$json = (string) $params;
		$db   = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
		$q    = $db->getQuery(true)->update($db->quoteName('#__extensions'))->set($db->quoteName('params') . ' = :p')
			->where($db->quoteName('element') . ' = ' . $db->quote('com_mapaviajes'))->where($db->quoteName('type') . ' = ' . $db->quote('component'))->bind(':p', $json);
		$db->setQuery($q)->execute();

		try
		{
			Factory::getContainer()->get(\Joomla\CMS\Cache\CacheControllerFactoryInterface::class)->createCacheController('callback', ['defaultgroup' => '_system'])->clean();
		}
		catch (\Throwable $e)
		{
			// the next request reads the database anyway when the cache is off
		}

		$this->setRedirect($back, Text::_('COM_MAPAVIAJES_SETTINGS_SAVED'));

		return true;
	}
}
