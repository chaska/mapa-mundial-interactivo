<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Site\View\Tripcomments;

defined('_JEXEC') || die;

use Chaska\Component\Mapaviajes\Administrator\Service\CommentsBridge;
use Chaska\Component\Mapaviajes\Administrator\Service\Settings;
use Chaska\Component\Mapaviajes\Administrator\Service\TripRepository;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Database\DatabaseInterface;

/**
 * The comments of ONE trip, rendered by whatever comment system is configured. The map shows this page inside a panel.
 * Same visibility rule as the map: registered users, published trip, published album, access level.
 */
class HtmlView extends BaseHtmlView
{
	protected string $comments = '';
	protected string $title    = '';

	/** @var array<string, string> What the comment system is told about this trip (shown to administrators only, to find out why a thread stays closed). */
	protected array $diag = [];

	public function display($tpl = null): void
	{
		$app  = Factory::getApplication();
		$user = $app->getIdentity();
		// These pages carry the session's CSRF token and per-person content: no cache of any kind (browser, proxy, LiteSpeed) may keep them
		$app->allowCache(false);
		$app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, private', true);
		$app->setHeader('Pragma', 'no-cache', true);
		$app->setHeader('X-LiteSpeed-Cache-Control', 'no-cache', true);

		if ($user->guest)
		{
			throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$raw    = $_GET['id'] ?? ''; // "id" because Engage's return-URL whitelist keeps id but drops unknown names
		$tripId = (\is_string($raw) && preg_match('/^\d{1,9}$/', $raw) === 1) ? (int) $raw : 0;
		$db     = Factory::getContainer()->get(DatabaseInterface::class);
		$trip   = (new TripRepository($db))->forComments($tripId);

		if ($trip === null || !(new TripRepository($db))->commentsVisible($user, $trip))
		{
			throw new \RuntimeException(Text::_('JERROR_LAYOUT_PAGE_NOT_FOUND'), 404);
		}

		$this->title    = (string) $trip['title'];
		$settings       = new Settings();
		$bridge         = new CommentsBridge($db, $settings);
		$this->comments = $bridge->render($user, $trip);

		if ($user->authorise('core.admin'))
		{
			$yes = Text::_('JYES');
			$no  = Text::_('JNO');
			$lvl = (int) $trip['album_access'];

			$this->diag = [
				'COM_MAPAVIAJES_DIAG_PROVIDER'  => $settings->commentsProvider(),
				'COM_MAPAVIAJES_DIAG_ENGAGE'    => \Joomla\CMS\Component\ComponentHelper::isEnabled('com_engage') ? $yes : $no,
				'COM_MAPAVIAJES_DIAG_ASSET'     => (string) $bridge->assetId((int) $trip['id'], (string) $trip['title']),
				'COM_MAPAVIAJES_DIAG_PUBLISHED' => ((int) $trip['published'] === 1 && (int) $trip['album_published'] === 1) ? $yes : $no,
				'COM_MAPAVIAJES_DIAG_OPEN'      => TripRepository::commentsAllowed($trip) ? $yes : $no,
				'COM_MAPAVIAJES_DIAG_LEVEL'     => $lvl . ' (' . (\in_array($lvl, $user->getAuthorisedViewLevels(), true) ? $yes : $no) . ')',
				'COM_MAPAVIAJES_DIAG_HTML'      => $this->comments === '' ? $no : $yes,
				'COM_MAPAVIAJES_DIAG_CAPTCHA'   => $this->captchaName(),
			];
		}

		$app->allowCache(false);
		$app->setHeader('X-Content-Type-Options', 'nosniff', true);
		$app->setHeader('Referrer-Policy', 'same-origin', true);
		$app->setHeader('Cache-Control', 'no-store, private', true);
		$this->getDocument()->setMetaData('robots', 'noindex, nofollow');
		$this->getDocument()->setTitle($this->title);

		parent::display($tpl);
	}

	/**
	 * Which captcha Engage uses for its comment form: "name (for whom)". Read only, for the administrator's diagnostic.
	 * Engage stores the plugin element in its "captcha" option; empty or 0 means none, "default" the site-wide one.
	 */
	private function captchaName(): string
	{
		$params = \Joomla\CMS\Component\ComponentHelper::getParams('com_engage');
		$name   = (string) $params->get('captcha', '');
		$for    = (string) $params->get('captcha_for', 'guests');

		if ($name === '' || $name === '0')
		{
			return Text::_('COM_MAPAVIAJES_DIAG_CAPTCHA_NONE');
		}

		if ($name === 'default' || $name === '1')
		{
			$name = (string) Factory::getApplication()->get('captcha', '');
		}

		$out = $name . ' (' . $for . ')';

		return stripos($name, 'recaptcha') !== false ? $out . ' — ' . Text::_('COM_MAPAVIAJES_DIAG_CAPTCHA_V3HINT') : $out;
	}
}
