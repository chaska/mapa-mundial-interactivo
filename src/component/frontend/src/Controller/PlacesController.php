<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Site\Controller;

defined('_JEXEC') || die;

use Chaska\Component\Mapaviajes\Administrator\Service\PlaceSearch;
use Joomla\CMS\MVC\Controller\BaseController;

/**
 * GET ?option=com_mapaviajes&task=places.search&q=...      → up to 8 places
 * GET ?option=com_mapaviajes&task=places.near&lat=..&lon=.. → the closest place, if any
 * Registered users only. The data is our own GeoNames file; the query is never stored or sent anywhere.
 */
class PlacesController extends BaseController
{
	use JsonTrait;

	private function guard(): void
	{
		if ($this->app->getIdentity()->guest)
		{
			$this->json(['ok' => false, 'expired' => true], 403);
		}

		if (strtoupper((string) $this->app->getInput()->getMethod()) !== 'GET')
		{
			$this->json(['ok' => false], 405);
		}
	}

	private function engine(): PlaceSearch
	{
		return new PlaceSearch(JPATH_ROOT . '/media/com_mapaviajes/data/places.tsv');
	}

	public function search(): void
	{
		$this->guard();
		$q = $_GET['q'] ?? '';

		if (!\is_string($q) || mb_strlen($q, 'UTF-8') > 80 || !mb_check_encoding($q, 'UTF-8'))
		{
			$this->json(['ok' => true, 'places' => []]);
		}

		$this->json(['ok' => true, 'places' => $this->engine()->search($q)]);
	}

	public function near(): void
	{
		$this->guard();
		$lat = $_GET['lat'] ?? '';
		$lon = $_GET['lon'] ?? '';
		$num = '/^-?\d{1,3}(?:\.\d{1,6})?$/';

		if (!\is_string($lat) || !\is_string($lon) || preg_match($num, $lat) !== 1 || preg_match($num, $lon) !== 1 || abs((float) $lat) > 90 || abs((float) $lon) > 180)
		{
			$this->json(['ok' => false], 400);
		}

		$this->json(['ok' => true, 'place' => $this->engine()->nearest((float) $lat, (float) $lon)]);
	}
}
