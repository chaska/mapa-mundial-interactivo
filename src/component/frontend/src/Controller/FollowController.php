<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Site\Controller;

defined('_JEXEC') || die;

use Chaska\Component\Mapaviajes\Administrator\Service\FollowService;
use Chaska\Component\Mapaviajes\Administrator\Service\SubmissionException;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;

/**
 * POST ?option=com_mapaviajes&task=follow.toggle|follow.seen|follow.digest&format=json   (token + AJAX header, logged-in users)
 *   toggle: u = the opaque key of the traveller      seen: nothing      digest: on = 0|1 (weekly summary by e-mail, no links in it)
 */
class FollowController extends BaseController
{
	use JsonTrait;

	public function toggle(): void
	{
		$user    = $this->gate();
		$service = new FollowService(Factory::getContainer()->get(DatabaseInterface::class));
		$key     = $_POST['u'] ?? '';

		try
		{
			$result = $service->toggle($user, \is_string($key) ? $key : '');
		}
		catch (SubmissionException $e)
		{
			$k = 'COM_MAPAVIAJES_ERR_' . strtoupper($e->key());
			$m = Text::_($k);
			$this->json(['ok' => false, 'error' => $m === $k ? Text::_('COM_MAPAVIAJES_ERR_GENERIC') : $m], $e->status());
		}
		catch (\Throwable $e)
		{
			$this->json(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_SERVER')], 500);
		}

		$this->json(['ok' => true, 'following' => $result['following'], 'follow' => $service->state((int) $user->id)]);
	}

	public function seen(): void
	{
		$user = $this->gate();
		(new FollowService(Factory::getContainer()->get(DatabaseInterface::class)))->markSeen((int) $user->id);
		$this->json(['ok' => true]);
	}

	public function digest(): void
	{
		$user = $this->gate();
		$on   = $_POST['on'] ?? '';

		if ($on !== '0' && $on !== '1')
		{
			$this->json(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_GENERIC')], 400);
		}

		(new FollowService(Factory::getContainer()->get(DatabaseInterface::class)))->setDigest((int) $user->id, $on === '1');
		$this->json(['ok' => true, 'digest' => (int) $on]);
	}

	/** The same gates as every other write: POST only, AJAX header, session token, logged-in user. */
	private function gate(): \Joomla\CMS\User\User
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

		return $user;
	}
}
