<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Site\Controller;

defined('_JEXEC') || die;

use Chaska\Component\Mapaviajes\Administrator\Service\ReactionService;
use Chaska\Component\Mapaviajes\Administrator\Service\Settings;
use Chaska\Component\Mapaviajes\Administrator\Service\SubmissionException;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;

/** POST ?option=com_mapaviajes&task=reaction.toggle&format=json   fields: trip_id, kind (like|fan), token */
class ReactionController extends BaseController
{
	use JsonTrait;

	public function toggle(): void
	{
		$user = $this->app->getIdentity();

		if (strtoupper((string) $this->app->getInput()->getMethod()) !== 'POST')
		{
			header('Allow: POST');
			$this->json(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_GENERIC')], 405);
		}

		if (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) !== 'xmlhttprequest' || !Session::checkToken('post'))
		{
			$this->json(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_SESSION'), 'expired' => true], 403);
		}

		if ($user->guest)
		{
			$this->json(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_LOGIN'), 'expired' => true], 403);
		}

		$tripRaw = $_POST['trip_id'] ?? '';
		$kind    = $_POST['kind'] ?? '';
		$tripId  = (\is_string($tripRaw) && preg_match('/^\d{1,9}$/', $tripRaw) === 1) ? (int) $tripRaw : 0;

		try
		{
			$result = (new ReactionService(Factory::getContainer()->get(DatabaseInterface::class), new Settings()))->toggle($user, $tripId, \is_string($kind) ? $kind : '');
		}
		catch (SubmissionException $e)
		{
			$key     = 'COM_MAPAVIAJES_ERR_' . strtoupper($e->key());
			$message = Text::_($key);

			$this->json(['ok' => false, 'error' => $message === $key ? Text::_('COM_MAPAVIAJES_ERR_GENERIC') : $message], $e->status());
		}
		catch (\Throwable $e)
		{
			$this->json(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_SERVER')], 500);
		}

		$this->json(['ok' => true, 'kind' => $kind, 'active' => $result['active'], 'count' => $result['count']]);
	}
}
