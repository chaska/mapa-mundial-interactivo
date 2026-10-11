<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Site\Controller;

defined('_JEXEC') || die;

use Chaska\Component\Mapaviajes\Administrator\Service\Notifier;
use Chaska\Component\Mapaviajes\Administrator\Service\RankingService;
use Chaska\Component\Mapaviajes\Administrator\Service\Settings;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;

/**
 * GET  ?option=com_mapaviajes&task=ranking.data&format=json   the podium of the week, the last closed week and the all-time table
 * POST ?option=com_mapaviajes&task=ranking.hide&format=json   hidden=0|1: appear or not appear in the ranking (own choice)
 * Registered users only.
 */
class RankingController extends BaseController
{
	use JsonTrait;

	private function service(): RankingService
	{
		return new RankingService(Factory::getContainer()->get(DatabaseInterface::class), new Settings());
	}

	/** @param array<string, mixed> $r */
	private function row(array $r, bool $real, int $me): array
	{
		$alias = $real && trim((string) ($r['name'] ?? '')) !== '' ? (string) $r['name'] : (string) ($r['username'] ?? '');

		return [
			'n' => $alias, 'u' => Settings::userKey((int) $r['user_id']), 'me' => (int) $r['user_id'] === $me,
			'pos' => (int) ($r['position'] ?? 0), 'st' => (int) ($r['stars'] ?? 0), 's' => (int) ($r['score'] ?? $r['points'] ?? 0),
			't' => (int) ($r['trips'] ?? 0), 'p' => (int) ($r['photos'] ?? 0), 'l' => (int) ($r['likes'] ?? 0), 'c' => (int) ($r['comments'] ?? 0),
		];
	}

	public function data(): void
	{
		$user = $this->app->getIdentity();
		$set  = new Settings();

		if ($user->guest)
		{
			$this->json(['ok' => false, 'expired' => true], 403);
		}

		if (!$set->rankEnabled())
		{
			$this->json(['ok' => false], 404);
		}

		$svc  = $this->service();
		foreach ($svc->closePending() as $closedWeek)
		{
			(new Notifier(Factory::getContainer()->get(DatabaseInterface::class), $set))->podium($closedWeek, $svc->closedWeek($closedWeek) ?? []);
		}

		$real = $set->showRealNames();
		$me   = (int) $user->id;
		$week = RankingService::weekStart(time());
		$live = $svc->compute($week, 500);
		$mine = null;

		foreach ($live as $r)
		{
			if ((int) $r['user_id'] === $me)
			{
				$mine = $this->row($r, $real, $me);
			}
		}

		$lastWeek = $svc->lastClosedWeek();
		$last     = $lastWeek !== null ? array_map(fn (array $r): array => $this->row($r, $real, $me), $svc->closedWeek($lastWeek) ?? []) : [];
		$all      = $svc->allTime(500);
		$general  = [];
		$mineAll  = null;

		foreach ($all as $i => $r)
		{
			$row = [
				'n' => $real && trim((string) $r['name']) !== '' ? (string) $r['name'] : (string) $r['username'], 'u' => Settings::userKey((int) $r['user_id']),
				'me' => (int) $r['user_id'] === $me, 'pos' => $i + 1, 's' => (int) $r['points'], 'st' => (int) $r['stars'], 'w' => (int) $r['weeks'], 'pd' => (int) $r['podiums'],
			];

			if ($i < RankingService::TOP)
			{
				$general[] = $row;
			}

			if ($row['me'])
			{
				$mineAll = $row;
			}
		}

		$this->json([
			'ok'      => true,
			'hidden'  => $svc->isHidden($me),
			'weights' => $set->rankWeights(),
			'caps'    => ['likes' => $set->rankLikeCap(), 'comments' => $set->rankCommentCap()],
			'week'    => ['start' => $week, 'end' => gmdate('Y-m-d', strtotime(RankingService::weekEnd($week) . ' UTC') - 86400), 'rows' => array_map(fn (array $r): array => $this->row($r, $real, $me), \array_slice($live, 0, RankingService::TOP)), 'mine' => $mine, 'count' => \count($live)],
			'last'    => ['start' => $lastWeek, 'end' => $lastWeek !== null ? gmdate('Y-m-d', strtotime(RankingService::weekEnd($lastWeek) . ' UTC') - 86400) : null, 'rows' => $last],
			'general' => ['rows' => $general, 'mine' => $mineAll],
		]);
	}

	public function hide(): void
	{
		$user = $this->app->getIdentity();

		if (strtoupper((string) $this->app->getInput()->getMethod()) !== 'POST')
		{
			header('Allow: POST');
			$this->json(['ok' => false], 405);
		}

		if (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) !== 'xmlhttprequest' || !Session::checkToken('post') || $user->guest)
		{
			$this->json(['ok' => false, 'error' => Text::_('COM_MAPAVIAJES_ERR_SESSION'), 'expired' => true], 403);
		}

		$raw = $_POST['hidden'] ?? '';

		if (!\is_string($raw) || !\in_array($raw, ['0', '1'], true))
		{
			$this->json(['ok' => false], 400);
		}

		$this->service()->setHidden((int) $user->id, $raw === '1');
		$this->json(['ok' => true, 'hidden' => $raw === '1']);
	}
}
