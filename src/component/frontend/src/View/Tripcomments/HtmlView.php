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

	public function display($tpl = null): void
	{
		$app  = Factory::getApplication();
		$user = $app->getIdentity();

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
		$this->comments = (new CommentsBridge($db, new Settings()))->render($user, $trip);

		$app->allowCache(false);
		$app->setHeader('X-Content-Type-Options', 'nosniff', true);
		$app->setHeader('Referrer-Policy', 'same-origin', true);
		$app->setHeader('Cache-Control', 'no-store, private', true);
		$this->getDocument()->setMetaData('robots', 'noindex, nofollow');
		$this->getDocument()->setTitle($this->title);

		parent::display($tpl);
	}
}
