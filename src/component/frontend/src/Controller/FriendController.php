<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Site\Controller;

defined('_JEXEC') || die;

use Chaska\Component\Mapaviajes\Administrator\Service\FriendService;
use Chaska\Component\Mapaviajes\Administrator\Service\Settings;
use Chaska\Component\Mapaviajes\Administrator\Service\SubmissionException;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Database\DatabaseInterface;

/**
 * POST ?option=com_mapaviajes&task=friend.request|respond|cancel|block|unblock&format=json   (token + AJAX header, logged-in users)
 *   u = the opaque key of the person;  respond also takes action = accept | decline | block
 * Every answer carries the person's whole friends state, so the map never has to guess.
 */
class FriendController extends BaseController
{
	use JsonTrait;

	public function request(): void
	{
		$this->run(static fn (FriendService $s, $user, string $key) => $s->request($user, $key));
	}

	public function respond(): void
	{
		$action = \is_string($_POST['action'] ?? null) ? (string) $_POST['action'] : '';
		$this->run(static fn (FriendService $s, $user, string $key) => $s->respond($user, $key, $action));
	}

	public function cancel(): void
	{
		$this->run(static fn (FriendService $s, $user, string $key) => $s->cancel($user, $key));
	}

	public function block(): void
	{
		$this->run(static fn (FriendService $s, $user, string $key) => $s->block($user, $key));
	}

	public function unblock(): void
	{
		$this->run(static fn (FriendService $s, $user, string $key) => $s->unblock($user, $key));
	}

	private function run(callable $action): void
	{
		$user    = $this->writeGate();
		$db      = Factory::getContainer()->get(DatabaseInterface::class);
		$service = new FriendService($db);
		$key     = \is_string($_POST['u'] ?? null) ? (string) $_POST['u'] : '';

		try
		{
			$result = $action($service, $user, $key);
		}
		catch (SubmissionException $e)
		{
			$this->refuse($e);
		}
		catch (\Throwable $e)
		{
			$this->json(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_SERVER')], 500);
		}

		$this->json(['ok' => true, 'result' => \is_array($result) ? ($result['status'] ?? '') : '', 'friend' => $service->state((int) $user->id, (new Settings())->showRealNames())]);
	}
}
