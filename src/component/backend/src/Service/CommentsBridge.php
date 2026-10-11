<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\Service;

defined('_JEXEC') || die;

use Joomla\CMS\Event\Content\AfterDisplayEvent;
use Joomla\CMS\Event\Content\AfterTitleEvent;
use Joomla\CMS\Event\Content\BeforeDisplayEvent;
use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Table\Asset;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\Dispatcher;
use Joomla\Registry\Registry;

/**
 * Hands the comments of a trip to another extension.
 *
 * Joomla itself has no comment system. Comment extensions are content plugins that answer the content events with a
 * context and an item that has an id. We never pretend a trip is an article (that would let a comment system look up
 * the wrong row of #__content): each trip owns a row of #__assets named "com_mapaviajes.trip.<id>" and the context is
 * "com_mapaviajes.trip".
 *
 *  - engage: Akeeba Engage / Engage identifies content by asset id and asks plugins what an asset is
 *            (onAkeebaEngageGetAssetMeta): plg_content_mapaviajes answers for our assets, and we dispatch com_engage.
 *  - events: the standard sequence onContentPrepare, onContentAfterTitle, onContentBeforeDisplay, onContentAfterDisplay
 *            with context com_mapaviajes.trip, for any extension that listens to it.
 */
final class CommentsBridge
{
	public const CONTEXT = 'com_mapaviajes.trip';
	public const ASSET   = 'com_mapaviajes.trip.';

	public function __construct(private DatabaseInterface $db, private Settings $settings)
	{
	}

	public function enabled(): bool
	{
		return $this->settings->commentsProvider() !== 'none';
	}

	/** The asset id of a trip, creating the row the first time (old trips get theirs on first use). */
	public function assetId(int $tripId, string $title = ''): int
	{
		$id  = $tripId;
		$cur = (int) $this->db->setQuery($this->db->getQuery(true)->select($this->db->quoteName('asset_id'))->from($this->db->quoteName('#__mapaviajes_trips'))->where($this->db->quoteName('id') . ' = :i')->bind(':i', $id, ParameterType::INTEGER))->loadResult();

		if ($cur > 0)
		{
			return $cur;
		}

		$asset = new Asset($this->db);
		$parent = new Asset($this->db);
		$parent->loadByName('com_mapaviajes');
		$asset->name  = self::ASSET . $tripId;
		$asset->title = mb_substr($title !== '' ? $title : ('Trip ' . $tripId), 0, 100, 'UTF-8');
		$asset->rules = '{}';

		if ($parent->id)
		{
			$asset->setLocation((int) $parent->id, 'last-child');
		}

		if (!$asset->check() || !$asset->store())
		{
			return 0;
		}

		$new  = (int) $asset->id;
		$bind = $new; // bind() turns the variable it is given into a string
		$tid  = $tripId;
		$this->db->setQuery($this->db->getQuery(true)->update($this->db->quoteName('#__mapaviajes_trips'))->set($this->db->quoteName('asset_id') . ' = :a')->where($this->db->quoteName('id') . ' = :i')->bind(':a', $bind, ParameterType::INTEGER)->bind(':i', $tid, ParameterType::INTEGER))->execute();

		return $new;
	}

	/**
	 * Called AFTER trips were deleted: removes their assets and, when Engage is installed, their comments.
	 *
	 * @param  list<int>  $assetIds
	 */
	public function forget(array $assetIds): void
	{
		foreach ($assetIds as $assetId)
		{
			$assetId = (int) $assetId;

			if ($assetId <= 0)
			{
				continue;
			}

			try
			{
				$tables = $this->db->getTableList();
				$prefix = $this->db->getPrefix();

				foreach (['engage_comments'] as $t)
				{
					if (\in_array($prefix . $t, $tables, true))
					{
						$a = $assetId;
						$this->db->setQuery($this->db->getQuery(true)->delete($this->db->quoteName('#__' . $t))->where($this->db->quoteName('asset_id') . ' = :a')->bind(':a', $a, ParameterType::INTEGER))->execute();
					}
				}

				$asset = new Asset($this->db);

				if ($asset->load($assetId) && str_starts_with((string) $asset->name, self::ASSET))
				{
					$asset->delete();
				}
			}
			catch (\Throwable $e)
			{
				// A comment system we do not know failing must never stop a moderator from deleting a trip.
			}
		}
	}

	/** The HTML of the comments of one trip for this user, or '' when there is nothing to show. */
	public function render(User $user, array $trip): string
	{
		$repo = new TripRepository($this->db);

		if (!$this->enabled() || !$repo->commentsVisible($user, $trip) || !TripRepository::commentsAllowed($trip))
		{
			return '';
		}

		return match ($this->settings->commentsProvider())
		{
			'engage' => $this->renderEngage($trip),
			'events' => $this->renderEvents($trip),
			default  => '',
		};
	}

	private function renderEngage(array $trip): string
	{
		$app = Factory::getApplication();

		if (!\Joomla\CMS\Component\ComponentHelper::isEnabled('com_engage'))
		{
			return '';
		}

		$assetId = $this->assetId((int) $trip['id'], (string) $trip['title']);

		if ($assetId <= 0)
		{
			return '';
		}

		// Engage only renders its comments when its own content plugin asks for them ("we must be called by the plugin").
		// So we ask that plugin, and ONLY that plugin (a private dispatcher: other content plugins never see this item),
		// the way Joomla asks it for an article. The asset id is what ties the thread to the trip; the row is a stand-in.
		try
		{
			$dispatcher = new Dispatcher();
			PluginHelper::importPlugin('content', 'engage', true, $dispatcher);

			$item = (object) [
				'id'           => (int) $trip['id'],
				'asset_id'     => $assetId,
				'title'        => (string) $trip['title'],
				'alias'        => 'trip-' . (int) $trip['id'],
				'state'        => 1,
				'catid'        => 1, // Joomla's root category: it always exists, so Engage's inheritance lookup ends at once
				'attribs'      => '{}',
				'publish_up'   => (string) $trip['created'],
				'publish_down' => null,
				'access'       => (int) $trip['album_access'],
				'created_by'   => (int) $trip['user_id'],
				'language'     => '*',
			];
			$args  = ['context' => 'com_content.article', 'subject' => $item, 'params' => new Registry(), 'page' => 0];
			$event = $dispatcher->dispatch('onContentAfterDisplay', new AfterDisplayEvent('onContentAfterDisplay', $args));

			return trim(implode("\n", (array) $event->getArgument('result', [])));
		}
		catch (\Throwable $e)
		{
			return '';
		}
	}

	private function renderEvents(array $trip): string
	{
		$app        = Factory::getApplication();
		$dispatcher = $app->getDispatcher();
		PluginHelper::importPlugin('content');

		$item = (object) [
			'id'        => (int) $trip['id'],
			'asset_id'  => $this->assetId((int) $trip['id'], (string) $trip['title']),
			'title'     => (string) $trip['title'],
			'alias'     => 'trip-' . (int) $trip['id'],
			'text'      => '',
			'introtext' => '',
			'fulltext'  => '',
			'catid'     => 0,
			'created_by' => (int) $trip['user_id'],
			'created'   => (string) $trip['created'],
			'access'    => (int) $trip['album_access'],
			'state'     => 1,
			'attribs'   => '{}',
			'metadata'  => '{}',
			'language'  => '*',
		];
		$params = new Registry(['show_title' => 0]);
		$args   = ['context' => self::CONTEXT, 'subject' => $item, 'params' => $params, 'page' => 0];
		$out    = '';

		$dispatcher->dispatch('onContentPrepare', new ContentPrepareEvent('onContentPrepare', $args));
		$dispatcher->dispatch('onContentAfterTitle', new AfterTitleEvent('onContentAfterTitle', $args));
		$out .= trim(implode("\n", (array) $dispatcher->dispatch('onContentBeforeDisplay', new BeforeDisplayEvent('onContentBeforeDisplay', $args))->getArgument('result', [])));
		$out .= trim(implode("\n", (array) $dispatcher->dispatch('onContentAfterDisplay', new AfterDisplayEvent('onContentAfterDisplay', $args))->getArgument('result', [])));

		return $out;
	}
}
