<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

defined('_JEXEC') || die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

return new class () implements InstallerScriptInterface {
	public function install(InstallerAdapter $adapter): bool
	{
		return true;
	}

	public function update(InstallerAdapter $adapter): bool
	{
		return true;
	}

	public function uninstall(InstallerAdapter $adapter): bool
	{
		// Photos are NOT deleted on uninstall: they belong to the site owner. The folder stays where it is.
		return true;
	}

	public function preflight(string $type, InstallerAdapter $adapter): bool
	{
		if (\in_array($type, ['install', 'update'], true) && version_compare(PHP_VERSION, '8.1.0', '<'))
		{
			Factory::getApplication()->enqueueMessage('Mapa de viajes necesita PHP 8.1 o superior. / Trip map needs PHP 8.1 or newer.', 'error');

			return false;
		}

		// Joomla itself must be on a release that closes the published security advisories (checked 11/10/2026:
		// 5.4.9 and 6.1.4). This component does not try to protect a site running a vulnerable core.
		if (\in_array($type, ['install', 'update'], true) && !self::joomlaIsPatched(JVERSION))
		{
			Factory::getApplication()->enqueueMessage(
				'Mapa de viajes no se instala en un Joomla con fallos de seguridad conocidos. Actualiza Joomla a 5.4.9 / 6.1.4 o superior. / Trip map will not install on a Joomla with known security flaws. Update Joomla to 5.4.9 / 6.1.4 or newer.',
				'error'
			);

			return false;
		}

		return true;
	}

	/** Same rule the admin warning uses. */
	public static function joomlaIsPatched(string $version): bool
	{
		return version_compare($version, '6.0.0', '>=') ? version_compare($version, '6.1.4', '>=') : version_compare($version, '5.4.9', '>=');
	}

	public function postflight(string $type, InstallerAdapter $adapter): bool
	{
		$app = Factory::getApplication();

		if (!\function_exists('imagewebp'))
		{
			$app->enqueueMessage('GD con soporte WebP no está disponible: no se podrán subir fotos hasta activarlo. / GD with WebP support is missing: photo uploads stay disabled until it is enabled.', 'warning');
		}

		if ($type !== 'install')
		{
			return true;
		}

		// A fresh install lets Registered users add trips (core.create). Everything else keeps Joomla's defaults.
		try
		{
			$db    = Factory::getContainer()->get(DatabaseInterface::class);
			$query = $db->getQuery(true)->select($db->quoteName(['id', 'rules']))->from($db->quoteName('#__assets'))->where($db->quoteName('name') . ' = ' . $db->quote('com_mapaviajes'));
			$row   = $db->setQuery($query)->loadObject();

			if ($row)
			{
				$rules = json_decode((string) $row->rules, true);
				$rules = \is_array($rules) ? $rules : [];

				if (!isset($rules['core.create']))
				{
					$rules['core.create'] = ['2' => 1];
					$json = json_encode($rules, JSON_THROW_ON_ERROR);
					$id   = (int) $row->id;
					$db->setQuery($db->getQuery(true)->update($db->quoteName('#__assets'))->set($db->quoteName('rules') . ' = :r')->where($db->quoteName('id') . ' = :i')->bind(':r', $json)->bind(':i', $id, \Joomla\Database\ParameterType::INTEGER))->execute();
				}
			}
		}
		catch (\Throwable $e)
		{
			$app->enqueueMessage('No se pudo dar permiso de envío a los usuarios registrados: actívalo en Opciones > Permisos. / Could not grant the submit permission to Registered users: enable it in Options > Permissions.', 'warning');
		}

		return true;
	}
};
