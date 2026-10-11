<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Plugin\User\Mapaviajes\Extension;

defined('_JEXEC') || die;

use Chaska\Component\Mapaviajes\Administrator\Service\UserData;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Utilities\ArrayHelper;

/** When an account is deleted, its trips, photos, reactions and ranking rows go with it (nothing is left behind with a dead id). */
final class Mapaviajes extends CMSPlugin implements SubscriberInterface
{
	use DatabaseAwareTrait;

	protected $allowLegacyListeners = false;

	public static function getSubscribedEvents(): array
	{
		return ['onUserAfterDelete' => 'onUserAfterDelete'];
	}

	public function onUserAfterDelete(Event $event): void
	{
		[$user, $success] = array_values($event->getArguments());
		$result = $event->getArgument('result', []);
		$event->setArgument('result', array_merge($result, [true]));

		$userId = ArrayHelper::getValue((array) $user, 'id', 0, 'int');

		// Only when Joomla really removed the account, and only if our component is there
		if (!$success || $userId <= 0 || !ComponentHelper::isEnabled('com_mapaviajes'))
		{
			return;
		}

		try
		{
			(new UserData($this->getDatabase()))->erase($userId);
		}
		catch (\Throwable $e)
		{
			// the account is already gone: a failure here must not break the Users manager
		}
	}
}
