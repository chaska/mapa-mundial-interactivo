<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\Controller;

defined('_JEXEC') || die;

use Chaska\Component\Mapaviajes\Administrator\Service\AdminSettings;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

/** POST task=cpanel.setting  name=<option> value=<value> + token: one switch of the control panel, saved on the spot. */
class CpanelController extends BaseController
{
	public function setting(): void
	{
		$user = $this->app->getIdentity();
		$send = static function (array $payload, int $status): never {
			while (ob_get_level() > 0) { @ob_end_clean(); }
			http_response_code($status);
			header('Content-Type: application/json; charset=utf-8');
			header('X-Content-Type-Options: nosniff');
			header('Cache-Control: no-store');
			echo json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
			exit;
		};

		if (strtoupper((string) $this->app->getInput()->getMethod()) !== 'POST' || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) !== 'xmlhttprequest' || !Session::checkToken('post'))
		{
			$send(['ok' => false, 'error' => Text::_('JINVALID_TOKEN_NOTICE')], 403);
		}

		if (!$user->authorise('core.options', 'com_mapaviajes') && !$user->authorise('core.admin', 'com_mapaviajes'))
		{
			$send(['ok' => false, 'error' => Text::_('JERROR_ALERTNOAUTHOR')], 403);
		}

		$name  = $_POST['name'] ?? '';
		$value = $_POST['value'] ?? '';

		if (!\is_string($name) || !\is_string($value) || !AdminSettings::save($name, $value))
		{
			$send(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_GENERIC')], 400);
		}

		$send(['ok' => true, 'name' => $name, 'value' => $value], 200);
	}
}
