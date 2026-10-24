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
use Chaska\Component\Mapaviajes\Administrator\Service\WorldCountries;
use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Language\Text;
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
 * 1. Short codes for articles: {mapaviajes stats} -> "51 albums · 16 countries", {mapaviajes albums} -> 51, {mapaviajes countries} -> 16
 *    (add album=ID to count a single map). Only totals of published trips: no names, no places, no photos.
 * 2. Tells Akeeba Engage / Engage what the assets named "com_mapaviajes.trip.<id>" are (a trip of the map), so its comment
 *    form, notices, moderation lists and permission checks work for them. It does nothing when Engage is not installed.
 */
final class Mapaviajes extends CMSPlugin implements SubscriberInterface
{
	use DatabaseAwareTrait;

	protected $allowLegacyListeners = false;

	protected $autoloadLanguage = true;

	public static function getSubscribedEvents(): array
	{
		if (!ComponentHelper::isEnabled('com_mapaviajes'))
		{
			return [];
		}

		$events = ['onContentPrepare' => 'onContentPrepare'];

		if (ComponentHelper::isEnabled('com_engage'))
		{
			$events['onAkeebaEngageGetAssetMeta']       = 'onAkeebaEngageGetAssetMeta';
			$events['onAkeebaEngageGetAssetIDsByTitle'] = 'onAkeebaEngageGetAssetIDsByTitle';
		}

		return $events;
	}

	/** Replaces {mapaviajes stats|albums|countries [album=ID]} in the text of an article (or any content item). */
	public function onContentPrepare(ContentPrepareEvent $event): void
	{
		if ($this->getApplication()->isClient('api') || $event->getContext() === 'com_finder.indexer')
		{
			return;
		}

		$item = $event->getItem();

		if (!isset($item->text) || !\is_string($item->text) || stripos($item->text, '{mapaviajes') === false)
		{
			return;
		}

		$item->text = preg_replace_callback(
			'/\{mapaviajes\s+(stats|albums|countries)(?:\s+album\s*=\s*(\d{1,9}))?\s*\}/i',
			function (array $m): string {
				try
				{
					[$albums, $countries] = $this->totals(isset($m[2]) ? (int) $m[2] : 0);
				}
				catch (\Throwable $e)
				{
					return ''; // an article never breaks because of a counter
				}

				return match (strtolower($m[1]))
				{
					'albums'    => (string) $albums,
					'countries' => (string) $countries,
					default     => '<span class="mv-stats">' . htmlspecialchars(Text::plural('PLG_CONTENT_MAPAVIAJES_ALBUMS', $albums) . ' · ' . Text::plural('PLG_CONTENT_MAPAVIAJES_COUNTRIES', $countries), ENT_QUOTES, 'UTF-8') . '</span>',
				};
			},
			$item->text
		) ?? $item->text;
	}

	/**
	 * Published trips of published maps (one map when $albumId > 0): how many, and in how many countries. Cached for ten minutes.
	 *
	 * @return array{0: int, 1: int}
	 */
	private function totals(int $albumId): array
	{
		$cache = Factory::getContainer()->get(\Joomla\CMS\Cache\CacheControllerFactoryInterface::class)->createCacheController('callback', ['defaultgroup' => 'com_mapaviajes', 'lifetime' => 10]);

		return $cache->get(function () use ($albumId): array {
			$db = $this->getDatabase();
			$q  = $db->getQuery(true)->select([$db->quoteName('t.lat'), $db->quoteName('t.lon')])
				->from($db->quoteName('#__mapaviajes_trips', 't'))
				->join('INNER', $db->quoteName('#__mapaviajes_albums', 'a') . ' ON ' . $db->quoteName('a.id') . ' = ' . $db->quoteName('t.album_id'))
				->where($db->quoteName('t.published') . ' = 1')->where($db->quoteName('a.published') . ' = 1');

			if ($albumId > 0)
			{
				$a = $albumId;
				$q->where($db->quoteName('a.id') . ' = :a')->bind(':a', $a, ParameterType::INTEGER);
			}

			$rows   = $db->setQuery($q)->loadAssocList() ?: [];
			$places = array_map(static fn (array $r): array => [(float) $r['lat'], (float) $r['lon']], $rows);

			return [\count($rows), (new WorldCountries(JPATH_ROOT . '/media/com_mapaviajes/data/world.json'))->count($places)];
		}, [$albumId], 'stats-' . $albumId);
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
