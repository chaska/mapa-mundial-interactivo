<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Plugin\Content\Mapaviajes\Extension;

defined('_JEXEC') || die;

use Chaska\Component\Mapaviajes\Administrator\Service\Settings;
use Chaska\Component\Mapaviajes\Administrator\Service\TripRepository;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

/**
 * Tells Akeeba Engage / Engage what the assets named "com_mapaviajes.trip.<id>" are (a trip of the map), so its comment
 * form, notices, moderation lists and permission checks work for them. It does nothing when Engage is not installed.
 */
final class Mapaviajes extends CMSPlugin implements SubscriberInterface
{
	use DatabaseAwareTrait;

	protected $allowLegacyListeners = false;

	public static function getSubscribedEvents(): array
	{
		if (!ComponentHelper::isEnabled('com_engage') || !ComponentHelper::isEnabled('com_mapaviajes'))
		{
			return [];
		}

		return [
			'onAkeebaEngageGetAssetMeta'       => 'onAkeebaEngageGetAssetMeta',
			'onAkeebaEngageGetAssetIDsByTitle' => 'onAkeebaEngageGetAssetIDsByTitle',
		];
	}

	public function onAkeebaEngageGetAssetMeta(Event $event): void
	{
		[$assetId] = array_values($event->getArguments());
		$result    = $event->getArgument('result', []);
		$row       = (int) $assetId > 0 ? (new TripRepository($this->getDatabase()))->forComments(0, (int) $assetId) : null;

		if ($row === null)
		{
			$event->setArgument('result', array_merge($result, [null]));

			return;
		}

		$settings = new Settings();
		$site     = $this->getApplication()->isClient('site');
		$public   = Route::link('site', 'index.php?option=com_mapaviajes&album=' . (int) $row['album_id'] . '&trip=' . (int) $row['id'], false, Route::TLS_IGNORE, true);
		$author   = $settings->showRealNames() ? (string) ($row['name'] ?? '') : (string) ($row['username'] ?? '');

		$event->setArgument('result', array_merge($result, [[
			'type'               => 'mapaviajes', // Engage 0.8.3 wants it to match the component (com_mapaviajes), whatever the asset name continues with
			'title'              => (string) $row['title'],
			'category'           => (string) $row['album_title'],
			'author_name'        => $author,
			'author_email'       => '', // never handed to a third party
			'url'                => $site ? $public : 'index.php?option=com_mapaviajes&view=trips',
			'public_url'         => $public,
			'published'          => (int) $row['published'] === 1 && (int) $row['album_published'] === 1,
			'published_on'       => Factory::getDate((string) $row['created']),
			'access'             => (int) $row['album_access'],
			'parent_access'      => (int) $row['album_access'],
			'category_published' => (int) $row['album_published'],
			'parameters'         => new Registry([
				'comments_enabled'    => TripRepository::commentsAllowed($row) ? 1 : 0,
				'comments_close_after' => 0,
				'default_publish'     => -1,
				'comments_show'       => 1,
			]),
		]]));
	}

	public function onAkeebaEngageGetAssetIDsByTitle(Event $event): void
	{
		[$filter] = array_values($event->getArguments());
		$result   = $event->getArgument('result', []);
		$filter   = trim((string) $filter);

		if ($filter === '')
		{
			$event->setArgument('result', $result);

			return;
		}

		$db   = $this->getDatabase();
		$like = (str_contains($filter, '%') ? $filter : '%' . addcslashes($filter, '\\_') . '%');
		$q    = $db->getQuery(true)->select($db->quoteName('asset_id'))->from($db->quoteName('#__mapaviajes_trips'))
			->where($db->quoteName('title') . ' LIKE :l')->where($db->quoteName('asset_id') . ' > 0')->where($db->quoteName('published') . ' = 1')->bind(':l', $like, ParameterType::STRING);

		try
		{
			$ids = $db->setQuery($q)->loadColumn() ?: [];
		}
		catch (\Throwable $e)
		{
			$ids = [];
		}

		$event->setArgument('result', array_merge($result, $ids));
	}
}
