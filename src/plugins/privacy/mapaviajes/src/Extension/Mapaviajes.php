<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Plugin\Privacy\Mapaviajes\Extension;

defined('_JEXEC') || die;

use Chaska\Component\Mapaviajes\Administrator\Service\UserData;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\Component\Privacy\Administrator\Plugin\PrivacyPlugin;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

/** Joomla's Privacy tools: what we keep about a person (access request) and its removal (erasure request). */
final class Mapaviajes extends PrivacyPlugin implements SubscriberInterface
{
	use DatabaseAwareTrait;

	protected $allowLegacyListeners = false;

	public static function getSubscribedEvents(): array
	{
		if (!ComponentHelper::isEnabled('com_mapaviajes'))
		{
			return [];
		}

		return ['onPrivacyExportRequest' => 'onPrivacyExportRequest', 'onPrivacyRemoveData' => 'onPrivacyRemoveData'];
	}

	public function onPrivacyExportRequest(Event $event): void
	{
		[$request, $user] = array_values($event->getArguments());
		$result = $event->getArgument('result', []);

		// A request for an e-mail address with no account has no user: export nothing (id 0 is where erased people's log rows live)
		if (!$user || (int) $user->id <= 0)
		{
			return;
		}

		$data   = (new UserData($this->getDatabase()))->export((int) $user->id);
		$titles = ['trips' => 'Trips on the map', 'photos' => 'Photos of those trips (data about each photo, not the file)', 'reactions' => 'Hearts and stars given to trips', 'ranking' => 'Weekly ranking', 'settings' => 'Map settings', 'events' => 'Security log entries (codes only)'];
		$domains = [];

		foreach ($data as $name => $rows)
		{
			$domain = $this->createDomain('mapaviajes_' . $name, $titles[$name] ?? $name);

			foreach ($rows as $i => $row)
			{
				$domain->addItem($this->createItemFromArray($row, (string) ($row['id'] ?? $i)));
			}

			$domains[] = $domain;
		}

		$event->setArgument('result', array_merge($result, [$domains]));
	}

	public function onPrivacyRemoveData(Event $event): void
	{
		[$request, $user] = array_values($event->getArguments());
		(new UserData($this->getDatabase()))->erase((int) $user->id);
	}
}
