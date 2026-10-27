<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\Service;

defined('_JEXEC') || die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\User\User;

/**
 * Profile photos from the optional MiFoto component (com_mifoto >= 0.2.0).
 *
 * MiFoto decides who may see each photo for the person looking, so the answer depends on the viewer and is never
 * stored or shared between people. Without MiFoto, with the option off, or if anything fails: no photos, and the map
 * keeps the coloured initial. The address comes from MiFoto's own domain-relative route, so only paths that start with
 * a single "/" are accepted.
 */
final class ProfilePhoto
{
	private static bool $logged = false;

	/** @param (\Closure(list<int>, int, User): array<int, mixed>)|null $source tests give their own; null = MiFoto */
	public function __construct(private readonly ?\Closure $source = null, private readonly ?bool $enabled = null)
	{
	}

	/**
	 * @param  list<int>  $userIds
	 *
	 * @return array<int, string>  user id => address of the photo, only for people the viewer can see a photo of
	 */
	public function urls(array $userIds, ?User $viewer = null, int $size = 64): array
	{
		$ids = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $i): bool => $i > 0)));

		if ($ids === [] || !($this->enabled ?? (new Settings())->profilePhotos()))
		{
			return [];
		}

		try
		{
			$viewer = $viewer ?? Factory::getApplication()->getIdentity();

			if ($viewer === null || $viewer->guest)
			{
				return [];
			}

			$found = $this->source !== null ? ($this->source)($ids, $size, $viewer) : $this->fromMifoto($ids, $size, $viewer);
		}
		catch (\Throwable $e)
		{
			// the map goes on with initials, but a broken or renamed MiFoto must leave a trace for the administrator (class and message only)
			self::$logged || Log::add('profile photos unavailable: ' . \get_class($e) . ': ' . substr($e->getMessage(), 0, 160), Log::WARNING, 'com_mapaviajes');
			self::$logged = true;

			return [];
		}

		$out = [];

		foreach ($found as $id => $photo)
		{
			$url = self::addressOf($photo);

			if (\in_array((int) $id, $ids, true) && $url !== null)
			{
				$out[(int) $id] = $url;
			}
		}

		return $out;
	}

	/** @param list<int> $ids @return array<int, mixed> */
	private function fromMifoto(array $ids, int $size, User $viewer): array
	{
		if (!ComponentHelper::isEnabled('com_mifoto'))
		{
			return [];
		}

		$component = Factory::getApplication()->bootComponent('com_mifoto');
		$service   = method_exists($component, 'getPhotoService') ? $component->getPhotoService() : null;

		if ($service === null || !method_exists($service, 'getPhotos'))
		{
			throw new \RuntimeException('com_mifoto does not offer getPhotoService()->getPhotos() (needs MiFoto 0.2.0 or later)');
		}

		return (array) $service->getPhotos($ids, $size, $viewer);
	}

	/** The address inside whatever MiFoto hands back (a string, an array or an object), or null when it is not a safe local path. */
	public static function addressOf(mixed $photo): ?string
	{
		$url = null;

		if (\is_string($photo))
		{
			$url = $photo;
		}
		elseif (\is_array($photo))
		{
			$url = $photo['url'] ?? null;
		}
		elseif (\is_object($photo))
		{
			$url = $photo->url ?? (method_exists($photo, 'getUrl') ? $photo->getUrl() : null);
		}

		return \is_string($url) ? self::safe($url) : null;
	}

	public static function safe(string $url): ?string
	{
		if ($url === '' || \strlen($url) > 300 || $url[0] !== '/' || str_starts_with($url, '//') || preg_match('/[\x00-\x20\\\\<>"\'`]/', $url) === 1)
		{
			return null;
		}

		return $url;
	}
}
