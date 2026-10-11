<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\Service;

defined('_JEXEC') || die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\Database\DatabaseInterface;

/**
 * Hands out one photo, and only after the caller's own rule says yes. Whatever goes wrong (no such photo,
 * not allowed, file missing) the answer is the same empty 404, so nobody can probe which ids exist.
 */
final class PhotoServer
{
	/**
	 * @param   callable(array<string, int|string>): bool  $mayView  Receives the gate data of the photo.
	 */
	public static function send(CMSApplicationInterface $app, DatabaseInterface $db, Settings $settings, int $photoId, callable $mayView): never
	{
		$gate = (new TripRepository($db))->photoGate($photoId);
		$path = ($gate !== null && $mayView($gate)) ? $settings->storage()->pathFor($gate['filename']) : null;

		while (ob_get_level() > 0)
		{
			@ob_end_clean();
		}

		self::deliver($app, $path, (string) ($gate['filename'] ?? ''), 'foto.webp');
	}

	/** The logo of a map: the same empty 404 for anything that is not allowed or not there. */
	public static function sendLogo(CMSApplicationInterface $app, DatabaseInterface $db, Settings $settings, int $albumId, callable $mayView): never
	{
		$gate = (new TripRepository($db))->logoGate($albumId);
		$path = ($gate !== null && $gate['logo'] !== '' && $mayView($gate)) ? $settings->storage()->pathFor($gate['logo']) : null;

		while (ob_get_level() > 0)
		{
			@ob_end_clean();
		}

		self::deliver($app, $path, $gate['logo'] ?? '', 'logo.webp');
	}

	private static function deliver(CMSApplicationInterface $app, ?string $path, string $etagSeed, string $downloadName): never
	{
		if ($path === null)
		{
			http_response_code(404);
			header('Content-Type: text/plain; charset=utf-8');
			header('X-Content-Type-Options: nosniff');
			header('Cache-Control: no-store');
			$app->close();
			exit;
		}

		$size = (int) filesize($path);
		$etag = '"' . substr($etagSeed, 0, 32) . '"';

		header('X-Content-Type-Options: nosniff');
		header('Cache-Control: private, no-cache'); // revalidate every time: a hidden or trashed photo must stop showing at once
		header('Vary: Cookie');
		header('ETag: ' . $etag);
		header('Content-Security-Policy: default-src \'none\'; sandbox');
		header('Cross-Origin-Resource-Policy: same-origin');
		header('Referrer-Policy: no-referrer');

		if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag)
		{
			http_response_code(304);
			$app->close();
			exit;
		}

		header('Content-Type: image/webp');
		header('Content-Length: ' . $size);
		header('Content-Disposition: inline; filename="' . $downloadName . '"');

		readfile($path);
		$app->close();
		exit;
	}
}
