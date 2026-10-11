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
use Joomla\Database\DatabaseInterface;

return new class implements InstallerScriptInterface {
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
		return true;
	}

	/** Switch the plugins on at a fresh install and when updating from a version that did not have them all (before 1.3.0). */
	private bool $enable = false;

	public function preflight(string $type, InstallerAdapter $adapter): bool
	{
		$this->enable = ($type === 'install');

		if ($type === 'update')
		{
			$db  = Factory::getContainer()->get(DatabaseInterface::class);
			$row = (string) $db->setQuery($db->getQuery(true)->select($db->quoteName('manifest_cache'))->from($db->quoteName('#__extensions'))
				->where($db->quoteName('type') . ' = ' . $db->quote('package'))->where($db->quoteName('element') . ' = ' . $db->quote('pkg_mapaviajes')))->loadResult();
			$old = (string) (json_decode($row, true)['version'] ?? '0');
			$this->enable = version_compare($old, '1.3.0', '<');
		}

		return true;
	}

	/** The three small plugins (comments bridge, account deletion, privacy tools) are harmless on their own: switched on at install time. */
	public function postflight(string $type, InstallerAdapter $adapter): bool
	{
		if ($this->enable)
		{
			$db = Factory::getContainer()->get(DatabaseInterface::class);
			$db->setQuery($db->getQuery(true)->update($db->quoteName('#__extensions'))->set($db->quoteName('enabled') . ' = 1')
				->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))->where($db->quoteName('folder') . ' IN (' . implode(',', array_map([$db, 'quote'], ['content', 'user', 'privacy'])) . ')')->where($db->quoteName('element') . ' = ' . $db->quote('mapaviajes')))->execute();
		}

		return true;
	}
};
