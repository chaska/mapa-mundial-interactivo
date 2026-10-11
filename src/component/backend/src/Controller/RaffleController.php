<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\Controller;

defined('_JEXEC') || die;

use Chaska\Component\Mapaviajes\Administrator\Service\EventLog;
use Chaska\Component\Mapaviajes\Administrator\Service\RaffleService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;

/** The raffle list of participants. Moderators only (core.edit.state); both actions need the form token. */
class RaffleController extends BaseController
{
	private function guard(): array
	{
		$this->checkToken('post');

		if (!$this->app->getIdentity()->authorise('core.edit.state', 'com_mapaviajes'))
		{
			throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		return RaffleService::filters($_POST);
	}

	private function query(array $f): string
	{
		return http_build_query(['option' => 'com_mapaviajes', 'view' => 'raffle', 'from' => $f['from'], 'to' => $f['to'], 'min' => $f['min'], 'album' => $f['album'] ?: '']);
	}

	public function csv(): void
	{
		$f      = $this->guard();
		$db     = Factory::getContainer()->get(DatabaseInterface::class);
		$people = (new RaffleService($db))->participants($f);
		EventLog::add($db, (int) $this->app->getIdentity()->id, 'raffle_export', (string) \count($people));

		while (ob_get_level() > 0) { @ob_end_clean(); }

		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="participantes-' . gmdate('Ymd-His') . '.csv"');
		header('X-Content-Type-Options: nosniff');
		header('Cache-Control: no-store');
		echo RaffleService::csv($people);
		exit;
	}

	public function draw(): void
	{
		$f      = $this->guard();
		$db     = Factory::getContainer()->get(DatabaseInterface::class);
		$raw    = $_POST['winners'] ?? '1';
		$n      = (\is_string($raw) && preg_match('/^\d{1,3}$/', $raw) === 1) ? max(1, min(50, (int) $raw)) : 1;
		$people = (new RaffleService($db))->participants($f);
		$won    = RaffleService::draw($people, $n);
		EventLog::add($db, (int) $this->app->getIdentity()->id, 'raffle_draw', \count($won) . '/' . \count($people));
		$this->app->setUserState('com_mapaviajes.raffle.winners', array_map(static fn (array $p): array => ['id' => (int) $p['id'], 'username' => (string) $p['username'], 'name' => (string) $p['name'], 'email' => (string) $p['email']], $won));
		$this->setRedirect(Route::_('index.php?' . $this->query($f), false));
	}
}
