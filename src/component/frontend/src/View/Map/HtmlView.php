<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Site\View\Map;

defined('_JEXEC') || die;

use Chaska\Component\Mapaviajes\Administrator\Security\ImageProcessor;
use Chaska\Component\Mapaviajes\Administrator\Service\Settings;
use Chaska\Component\Mapaviajes\Administrator\Service\TripRepository;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;

class HtmlView extends BaseHtmlView
{
	/** @var list<array<string, mixed>> */
	protected array $albums = [];

	/** @var array<string, mixed>|null */
	protected ?array $album = null;

	protected bool $canSubmit = false;
	protected bool $commentsOn = false;
	protected int $maxPhotos = 5;
	protected int $maxWords = 256;
	protected string $userName = '';

	/** Strings the browser code needs, in the language of the visitor. */
	private const JS_STRINGS = [
		'COM_MAPAVIAJES_JS_ALBUMS_N', 'COM_MAPAVIAJES_JS_ALBUM_ONE', 'COM_MAPAVIAJES_JS_COUNT_ALBUMS', 'COM_MAPAVIAJES_JS_COUNT_PLACES',
		'COM_MAPAVIAJES_JS_OPEN', 'COM_MAPAVIAJES_JS_CLOSE', 'COM_MAPAVIAJES_JS_NEAR', 'COM_MAPAVIAJES_JS_POINT', 'COM_MAPAVIAJES_JS_SEA',
		'COM_MAPAVIAJES_JS_COORDS', 'COM_MAPAVIAJES_JS_USE_POINT', 'COM_MAPAVIAJES_JS_NO_PLACE', 'COM_MAPAVIAJES_JS_PHOTOS_N', 'COM_MAPAVIAJES_JS_PHOTO_ONE',
		'COM_MAPAVIAJES_JS_COMMENTS', 'COM_MAPAVIAJES_JS_PENDING', 'COM_MAPAVIAJES_JS_BY', 'COM_MAPAVIAJES_JS_CAPTION_PH', 'COM_MAPAVIAJES_JS_WORDS',
		'COM_MAPAVIAJES_JS_REMOVE', 'COM_MAPAVIAJES_JS_PHOTOS_OF', 'COM_MAPAVIAJES_JS_E_PLACE', 'COM_MAPAVIAJES_JS_E_TITLE', 'COM_MAPAVIAJES_JS_E_PHOTOS',
		'COM_MAPAVIAJES_JS_E_TYPE', 'COM_MAPAVIAJES_JS_E_SIZE', 'COM_MAPAVIAJES_JS_E_MAX', 'COM_MAPAVIAJES_JS_E_NET', 'COM_MAPAVIAJES_JS_E_MISSING',
		'COM_MAPAVIAJES_JS_SENDING', 'COM_MAPAVIAJES_JS_SEND', 'COM_MAPAVIAJES_JS_PREPARING', 'COM_MAPAVIAJES_JS_PREV', 'COM_MAPAVIAJES_JS_NEXT',
		'COM_MAPAVIAJES_JS_PHOTO_X_OF_Y', 'COM_MAPAVIAJES_JS_AGO_NOW', 'COM_MAPAVIAJES_JS_AGO_MIN', 'COM_MAPAVIAJES_JS_AGO_H', 'COM_MAPAVIAJES_JS_AGO_YESTERDAY',
		'COM_MAPAVIAJES_JS_AGO_D', 'COM_MAPAVIAJES_JS_LATEST_HELP', 'COM_MAPAVIAJES_JS_ON_MAP', 'COM_MAPAVIAJES_JS_NOTIFY_OFF', 'COM_MAPAVIAJES_JS_FULLSCREEN',
		'COM_MAPAVIAJES_JS_LOAD_ERROR', 'COM_MAPAVIAJES_JS_SESSION', 'COM_MAPAVIAJES_JS_LOGIN', 'COM_MAPAVIAJES_JS_OCEAN_PACIFIC', 'COM_MAPAVIAJES_JS_OCEAN_ATLANTIC',
		'COM_MAPAVIAJES_JS_OCEAN_INDIAN', 'COM_MAPAVIAJES_JS_OCEAN_ARCTIC', 'COM_MAPAVIAJES_JS_OCEAN_SOUTHERN', 'COM_MAPAVIAJES_JS_NO_TRIPS', 'COM_MAPAVIAJES_JS_TITLE_AUTO',
		'COM_MAPAVIAJES_JS_LIKE_1', 'COM_MAPAVIAJES_JS_LIKE_2', 'COM_MAPAVIAJES_JS_LIKE_3', 'COM_MAPAVIAJES_JS_LIKE_4', 'COM_MAPAVIAJES_JS_LIKE_5', 'COM_MAPAVIAJES_JS_LIKE_6', 'COM_MAPAVIAJES_JS_LIKE_7', 'COM_MAPAVIAJES_JS_LIKE_8', 'COM_MAPAVIAJES_JS_LIKE_9', 'COM_MAPAVIAJES_JS_LIKE_10', 'COM_MAPAVIAJES_JS_FAN_MSG', 'COM_MAPAVIAJES_JS_LIKE_LABEL', 'COM_MAPAVIAJES_JS_LIKE_UNDO', 'COM_MAPAVIAJES_JS_FAN_LABEL', 'COM_MAPAVIAJES_JS_FAN_UNDO', 'COM_MAPAVIAJES_JS_OWN_REACT', 'COM_MAPAVIAJES_JS_LIKES_N', 'COM_MAPAVIAJES_JS_TAB_NEW', 'COM_MAPAVIAJES_JS_TAB_FAV', 'COM_MAPAVIAJES_JS_FAV_EMPTY', 'COM_MAPAVIAJES_JS_FAV_EMPTY_HELP', 'COM_MAPAVIAJES_JS_FAV_HELP', 'COM_MAPAVIAJES_JS_REACT_ERR', 'COM_MAPAVIAJES_JS_COMMENTS_BTN',
	];

	public function display($tpl = null): void
	{
		$app  = Factory::getApplication();
		$user = $app->getIdentity();

		// The login gate also lives here: Joomla falls back to display() for unknown tasks on any controller.
		if ($user->guest)
		{
			throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$db   = Factory::getContainer()->get(DatabaseInterface::class);
		$repo = new TripRepository($db);

		$this->albums = $repo->albumsFor($user);
		$wanted       = (int) preg_replace('/\D/', '', substr((string) ($_GET['album'] ?? ''), 0, 9));

		foreach ($this->albums as $a)
		{
			if ($this->album === null || (int) $a['id'] === $wanted)
			{
				$this->album = $a;

				if ((int) $a['id'] === $wanted)
				{
					break;
				}
			}
		}

		$settings        = new Settings();
		$this->maxPhotos = $settings->maxPhotos();
		$this->maxWords  = $settings->maxWords();
		$this->userName  = (string) $user->name;
		$this->commentsOn = $settings->commentsProvider() !== 'none' && $this->album !== null && (int) $this->album['allow_comments'] === 1;
		$this->canSubmit = $this->album !== null && $user->authorise('mapaviajes.submit', 'com_mapaviajes') && ImageProcessor::isSupported();

		// Nothing here may be cached by the site, a proxy or the browser history: it is personal.
		$app->allowCache(false);
		$app->setHeader('X-Content-Type-Options', 'nosniff', true);
		$app->setHeader('Referrer-Policy', 'same-origin', true);
		$app->setHeader('Cache-Control', 'no-store, private', true);
		$this->getDocument()->setMetaData('robots', 'noindex, nofollow');
		$this->getDocument()->setTitle($this->album !== null ? (string) $this->album['title'] : Text::_('COM_MAPAVIAJES'));

		foreach (self::JS_STRINGS as $key)
		{
			Text::script($key);
		}

		$base    = Uri::root(true) . '/media/com_mapaviajes/';
		$options = [
			'worldUrl'   => $base . 'data/world.json',
			'searchUrl'  => Route::_('index.php?option=com_mapaviajes&task=places.search&format=json', false),
			'nearUrl'    => Route::_('index.php?option=com_mapaviajes&task=places.near&format=json', false),
			'dataUrl'    => Route::_('index.php?option=com_mapaviajes&task=trips.data&album=' . (int) ($this->album['id'] ?? 0), false),
			'submitUrl'  => Route::_('index.php?option=com_mapaviajes&task=trip.submit&format=json', false),
			'commentsOn' => $this->commentsOn,
			'commentsUrl' => Route::_('index.php?option=com_mapaviajes&view=tripcomments&tmpl=component&id=', false),
			'openTrip'   => (int) preg_replace('/\D/', '', substr((string) ($_GET['trip'] ?? ''), 0, 9)),
			'reactUrl'   => Route::_('index.php?option=com_mapaviajes&task=reaction.toggle&format=json', false),
			'photoUrl'   => Route::_('index.php?option=com_mapaviajes&task=photo.view&id=', false),
			'loginUrl'   => Route::_('index.php?option=com_users&view=login', false),
			'token'      => Session::getFormToken(),
			'albumId'    => (int) ($this->album['id'] ?? 0),
			'canSubmit'  => $this->canSubmit,
			'allowComments' => $this->album !== null && (int) $this->album['allow_comments'] === 1,
			'commentsDefault' => $settings->commentsDefault(),
			'maxPhotos'  => $this->maxPhotos,
			'maxWords'   => $this->maxWords,
			'maxBytes'   => $settings->maxUploadBytes(),
			'longSide'   => $settings->longSide(),
			'lang'       => $app->getLanguage()->getTag(),
		];

		$wa = $this->getDocument()->getWebAssetManager();
		$this->getDocument()->addScriptOptions('com_mapaviajes', $options);
		$wa->registerAndUseStyle('com_mapaviajes.fonts', 'com_mapaviajes/fonts.css')
			->registerAndUseStyle('com_mapaviajes.map', 'com_mapaviajes/mapa.css', [], [], ['com_mapaviajes.fonts'])
			->registerAndUseScript('com_mapaviajes.map', 'com_mapaviajes/mapa.js', [], ['defer' => true], ['core']);

		parent::display($tpl);
	}
}
