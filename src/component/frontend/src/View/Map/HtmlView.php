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
	protected bool $rankOn = false;
	protected bool $selfReact = false;
	protected string $mapStyle = 'pergamino';
	protected bool $mapChoice = false;

	/** @var list<array{label: string, kind: string, value: string}> */
	protected array $menuExtras = [];

	/** @var array<string, array{label: string, water: string, land: string}> */
	protected array $mapStyles = [];
	protected int $maxPhotos = 5;
	protected int $maxWords = 256;
	protected string $userName = '';

	/** Strings the browser code needs, in the language of the visitor. */
	private const JS_STRINGS = [
		'COM_MAPAVIAJES_JS_ALBUMS_N', 'COM_MAPAVIAJES_JS_POD_HOW_S1', 'COM_MAPAVIAJES_JS_POD_HOW_S2', 'COM_MAPAVIAJES_JS_POD_HOW_S3', 'COM_MAPAVIAJES_JS_POD_HOW_S4', 'COM_MAPAVIAJES_JS_TAGS_MORE', 'COM_MAPAVIAJES_JS_FILTER_CHIP_N', 'COM_MAPAVIAJES_JS_FILTER_NONE', 'COM_MAPAVIAJES_JS_E_YEAR', 'COM_MAPAVIAJES_JS_COUNT_ALBUMS_1', 'COM_MAPAVIAJES_JS_COUNT_PLACES_1', 'COM_MAPAVIAJES_JS_ALBUM_ONE', 'COM_MAPAVIAJES_JS_COUNT_ALBUMS', 'COM_MAPAVIAJES_JS_COUNT_PLACES',
		'COM_MAPAVIAJES_JS_OPEN', 'COM_MAPAVIAJES_JS_CLOSE', 'COM_MAPAVIAJES_JS_NEAR', 'COM_MAPAVIAJES_JS_POINT', 'COM_MAPAVIAJES_JS_SEA',
		'COM_MAPAVIAJES_JS_COORDS', 'COM_MAPAVIAJES_JS_USE_POINT', 'COM_MAPAVIAJES_JS_NO_PLACE', 'COM_MAPAVIAJES_JS_PHOTOS_N', 'COM_MAPAVIAJES_JS_PHOTO_ONE',
		'COM_MAPAVIAJES_JS_COMMENTS', 'COM_MAPAVIAJES_JS_CM_ON', 'COM_MAPAVIAJES_JS_CM_OFF', 'COM_MAPAVIAJES_JS_CM_ON_DONE', 'COM_MAPAVIAJES_JS_CM_OFF_DONE', 'COM_MAPAVIAJES_JS_FOLLOW_A', 'COM_MAPAVIAJES_JS_FOLLOWING_A', 'COM_MAPAVIAJES_JS_FOLLOW_ON', 'COM_MAPAVIAJES_JS_FOLLOW_OFF', 'COM_MAPAVIAJES_JS_FOL_HELP', 'COM_MAPAVIAJES_JS_FOL_EMPTY', 'COM_MAPAVIAJES_JS_FOL_EMPTY_HELP', 'COM_MAPAVIAJES_JS_FOL_NEW', 'COM_MAPAVIAJES_JS_FOL_DIGEST', 'COM_MAPAVIAJES_JS_FOL_DIGEST_HELP', 'COM_MAPAVIAJES_JS_FOL_ERR', 'COM_MAPAVIAJES_JS_DEL_CONFIRM', 'COM_MAPAVIAJES_JS_DEL_OK', 'COM_MAPAVIAJES_JS_DEL_NO', 'COM_MAPAVIAJES_JS_DEL_DONE', 'COM_MAPAVIAJES_JS_DEL_ERR', 'COM_MAPAVIAJES_JS_PENDING', 'COM_MAPAVIAJES_JS_BY', 'COM_MAPAVIAJES_JS_FR_ASK', 'COM_MAPAVIAJES_JS_FR_OUT', 'COM_MAPAVIAJES_JS_FR_IN', 'COM_MAPAVIAJES_JS_FR_OK', 'COM_MAPAVIAJES_JS_FR_SENT', 'COM_MAPAVIAJES_JS_FR_NOW', 'COM_MAPAVIAJES_JS_FR_GONE', 'COM_MAPAVIAJES_JS_FR_ERR', 'COM_MAPAVIAJES_JS_FR_H_IN', 'COM_MAPAVIAJES_JS_FR_H_FRIENDS', 'COM_MAPAVIAJES_JS_FR_H_OUT', 'COM_MAPAVIAJES_JS_FR_H_BLOCKED', 'COM_MAPAVIAJES_JS_FR_EMPTY', 'COM_MAPAVIAJES_JS_FR_EMPTY_HELP', 'COM_MAPAVIAJES_JS_FR_ACCEPT', 'COM_MAPAVIAJES_JS_FR_DECLINE', 'COM_MAPAVIAJES_JS_FR_BLOCK', 'COM_MAPAVIAJES_JS_FR_REMOVE', 'COM_MAPAVIAJES_JS_FR_CANCEL', 'COM_MAPAVIAJES_JS_FR_UNBLOCK', 'COM_MAPAVIAJES_JS_FR_REMOVE_CONFIRM', 'COM_MAPAVIAJES_JS_FR_BLOCK_CONFIRM', 'COM_MAPAVIAJES_JS_FR_NO', 'COM_MAPAVIAJES_JS_LOCK_1', 'COM_MAPAVIAJES_JS_LOCK_N', 'COM_MAPAVIAJES_JS_FO_BADGE', 'COM_MAPAVIAJES_JS_PD_BADGE', 'COM_MAPAVIAJES_JS_FO', 'COM_MAPAVIAJES_JS_FO_HELP', 'COM_MAPAVIAJES_JS_E_PUBLIC', 'COM_MAPAVIAJES_JS_ED_TITLE', 'COM_MAPAVIAJES_JS_ED_HELP', 'COM_MAPAVIAJES_JS_ED_ADD', 'COM_MAPAVIAJES_JS_ED_UPLOAD', 'COM_MAPAVIAJES_JS_ED_CLOSE', 'COM_MAPAVIAJES_JS_ED_DEL', 'COM_MAPAVIAJES_JS_ED_SURE', 'COM_MAPAVIAJES_JS_ED_FULL', 'COM_MAPAVIAJES_JS_ED_UPLOADING', 'COM_MAPAVIAJES_JS_ED_ERR', 'COM_MAPAVIAJES_JS_ED_RIGHTS', 'COM_MAPAVIAJES_JS_POD_HOW_PHOTO_PRIV',  'COM_MAPAVIAJES_JS_REPORT_TITLE', 'COM_MAPAVIAJES_JS_REPORT_HELP', 'COM_MAPAVIAJES_JS_REPORT_NOTE', 'COM_MAPAVIAJES_JS_REPORT_SEND', 'COM_MAPAVIAJES_JS_REPORT_CANCEL', 'COM_MAPAVIAJES_JS_REPORT_ERR', 'COM_MAPAVIAJES_JS_REPORT_CONTACT', 'COM_MAPAVIAJES_JS_REPORT_DONE', 'COM_MAPAVIAJES_JS_E_RIGHTS', 'COM_MAPAVIAJES_REPORT_REASON_ILLEGAL', 'COM_MAPAVIAJES_REPORT_REASON_RIGHTS', 'COM_MAPAVIAJES_REPORT_REASON_PRIVACY', 'COM_MAPAVIAJES_REPORT_REASON_MINOR', 'COM_MAPAVIAJES_REPORT_REASON_OTHER', 'COM_MAPAVIAJES_JS_CAPTION_PH', 'COM_MAPAVIAJES_JS_WORDS',
		'COM_MAPAVIAJES_JS_REMOVE', 'COM_MAPAVIAJES_JS_PHOTOS_OF', 'COM_MAPAVIAJES_JS_E_PLACE', 'COM_MAPAVIAJES_JS_E_TITLE', 'COM_MAPAVIAJES_JS_E_PHOTOS',
		'COM_MAPAVIAJES_JS_E_TYPE', 'COM_MAPAVIAJES_JS_E_SIZE', 'COM_MAPAVIAJES_JS_E_MAX', 'COM_MAPAVIAJES_JS_E_NET', 'COM_MAPAVIAJES_JS_E_MISSING',
		'COM_MAPAVIAJES_JS_SENDING', 'COM_MAPAVIAJES_JS_SEND', 'COM_MAPAVIAJES_JS_PREPARING', 'COM_MAPAVIAJES_JS_PREV', 'COM_MAPAVIAJES_JS_NEXT',
		'COM_MAPAVIAJES_JS_PHOTO_X_OF_Y', 'COM_MAPAVIAJES_JS_AGO_NOW', 'COM_MAPAVIAJES_JS_AGO_MIN', 'COM_MAPAVIAJES_JS_AGO_H', 'COM_MAPAVIAJES_JS_AGO_YESTERDAY',
		'COM_MAPAVIAJES_JS_AGO_D', 'COM_MAPAVIAJES_JS_LATEST_HELP', 'COM_MAPAVIAJES_JS_ON_MAP', 'COM_MAPAVIAJES_JS_NOTIFY_OFF', 'COM_MAPAVIAJES_JS_FULLSCREEN',
		'COM_MAPAVIAJES_JS_LOAD_ERROR', 'COM_MAPAVIAJES_JS_SESSION', 'COM_MAPAVIAJES_JS_LOGIN', 'COM_MAPAVIAJES_JS_OCEAN_PACIFIC', 'COM_MAPAVIAJES_JS_OCEAN_ATLANTIC',
		'COM_MAPAVIAJES_JS_OCEAN_INDIAN', 'COM_MAPAVIAJES_JS_OCEAN_ARCTIC', 'COM_MAPAVIAJES_JS_OCEAN_SOUTHERN', 'COM_MAPAVIAJES_JS_NO_TRIPS', 'COM_MAPAVIAJES_JS_TITLE_AUTO',
		'COM_MAPAVIAJES_JS_LIKE_1', 'COM_MAPAVIAJES_JS_LIKE_2', 'COM_MAPAVIAJES_JS_LIKE_3', 'COM_MAPAVIAJES_JS_LIKE_4', 'COM_MAPAVIAJES_JS_LIKE_5', 'COM_MAPAVIAJES_JS_LIKE_6', 'COM_MAPAVIAJES_JS_LIKE_7', 'COM_MAPAVIAJES_JS_LIKE_8', 'COM_MAPAVIAJES_JS_LIKE_9', 'COM_MAPAVIAJES_JS_LIKE_10', 'COM_MAPAVIAJES_JS_FAN_MSG', 'COM_MAPAVIAJES_JS_LIKE_LABEL', 'COM_MAPAVIAJES_JS_LIKE_UNDO', 'COM_MAPAVIAJES_JS_FAN_LABEL', 'COM_MAPAVIAJES_JS_FAN_UNDO', 'COM_MAPAVIAJES_JS_OWN_REACT', 'COM_MAPAVIAJES_JS_LIKES_N', 'COM_MAPAVIAJES_JS_TAB_NEW', 'COM_MAPAVIAJES_JS_TAB_FAV', 'COM_MAPAVIAJES_JS_FAV_EMPTY', 'COM_MAPAVIAJES_JS_FAV_EMPTY_HELP', 'COM_MAPAVIAJES_JS_FAV_HELP', 'COM_MAPAVIAJES_JS_REACT_ERR', 'COM_MAPAVIAJES_JS_COMMENTS_BTN', 'COM_MAPAVIAJES_JS_POD_TAB_WEEK', 'COM_MAPAVIAJES_JS_POD_TAB_LAST', 'COM_MAPAVIAJES_JS_POD_TAB_ALL', 'COM_MAPAVIAJES_JS_POD_TITLE_WEEK', 'COM_MAPAVIAJES_JS_POD_TITLE_LAST', 'COM_MAPAVIAJES_JS_POD_TITLE_ALL', 'COM_MAPAVIAJES_JS_POD_RANGE', 'COM_MAPAVIAJES_JS_POD_NONE', 'COM_MAPAVIAJES_JS_POD_REST', 'COM_MAPAVIAJES_JS_POD_MINE', 'COM_MAPAVIAJES_JS_POD_MINE_N', 'COM_MAPAVIAJES_JS_POD_MINE_NONE', 'COM_MAPAVIAJES_JS_POD_MINE_ALL', 'COM_MAPAVIAJES_JS_POD_HIDE', 'COM_MAPAVIAJES_JS_POD_HIDE_HELP', 'COM_MAPAVIAJES_JS_POD_HOW', 'COM_MAPAVIAJES_JS_POD_HOW_TRIP', 'COM_MAPAVIAJES_JS_POD_HOW_PHOTO', 'COM_MAPAVIAJES_JS_POD_HOW_LIKE', 'COM_MAPAVIAJES_JS_POD_HOW_COMMENT', 'COM_MAPAVIAJES_JS_POD_HOW_CAP', 'COM_MAPAVIAJES_JS_POD_HOW_WEEK', 'COM_MAPAVIAJES_JS_POD_PTS', 'COM_MAPAVIAJES_JS_POD_STATS', 'COM_MAPAVIAJES_JS_POD_ALLSTATS', 'COM_MAPAVIAJES_JS_POD_FILTER', 'COM_MAPAVIAJES_JS_POD_ERR', 'COM_MAPAVIAJES_JS_POD_LOADING', 'COM_MAPAVIAJES_JS_FILTER_CHIP', 'COM_MAPAVIAJES_JS_FILTER_CLEAR', 'COM_MAPAVIAJES_JS_POD_LAST_NONE',
	];

	public function display($tpl = null): void
	{
		$app  = Factory::getApplication();
		$user = $app->getIdentity();
		// These pages carry the session's CSRF token and per-person content: no cache of any kind (browser, proxy, LiteSpeed) may keep them
		$app->allowCache(false);
		$app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, private', true);
		$app->setHeader('Pragma', 'no-cache', true);
		$app->setHeader('X-LiteSpeed-Cache-Control', 'no-cache', true);

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
		$this->rankOn = $settings->rankEnabled();
		$this->selfReact = $settings->canSelfReact($user);
		$this->mapStyle  = $settings->mapStyle();
		$this->mapChoice = $settings->mapUserChoice();
		$this->menuExtras = $settings->menuExtras();
		$this->mapStyles = \Chaska\Component\Mapaviajes\Administrator\Service\MapStyles::all();
		$this->canSubmit = $this->album !== null && $user->authorise('mapaviajes.submit', 'com_mapaviajes') && ImageProcessor::isSupported();

		// Nothing here may be cached by the site, a proxy or the browser history: it is personal.
		$app->allowCache(false);
		$app->setHeader('X-Content-Type-Options', 'nosniff', true);
		// Only this site may frame these pages (clickjacking of like / favourite / comment buttons)
		$app->setHeader('X-Frame-Options', 'SAMEORIGIN', true);
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
			'commentsSwitchUrl' => Route::_('index.php?option=com_mapaviajes&task=trip.comments&format=json', false),
			'commentsProvider'  => $settings->commentsProvider() !== 'none',
			'followUrl'  => Route::_('index.php?option=com_mapaviajes&task=follow.toggle&format=json', false),
			'seenUrl'    => Route::_('index.php?option=com_mapaviajes&task=follow.seen&format=json', false),
			'digestUrl'  => Route::_('index.php?option=com_mapaviajes&task=follow.digest&format=json', false),
			'reportUrl'  => Route::_('index.php?option=com_mapaviajes&task=trip.report&format=json', false),
			'contactEmail' => $settings->contactEmail(),
			'friendUrl'  => Route::_('index.php?option=com_mapaviajes&task=friend.', false),
			'photosUrl'  => Route::_('index.php?option=com_mapaviajes&task=trip.photos&format=json', false),
			'photoPrivacyUrl' => Route::_('index.php?option=com_mapaviajes&task=trip.photoprivacy&format=json', false),
			'photoRemoveUrl'  => Route::_('index.php?option=com_mapaviajes&task=trip.photoremove&format=json', false),
			'removeUrl'  => Route::_('index.php?option=com_mapaviajes&task=trip.remove&format=json', false),
			'canEditState' => $user->authorise('core.edit.state', 'com_mapaviajes'),
			'canDelete'  => $user->authorise('core.delete', 'com_mapaviajes'),
			'submitUrl'  => Route::_('index.php?option=com_mapaviajes&task=trip.submit&format=json', false),
			'commentsOn' => $this->commentsOn,
			'commentsUrl' => Route::_('index.php?option=com_mapaviajes&view=tripcomments&tmpl=component&id=', false),
			'openTrip'   => (int) preg_replace('/\D/', '', substr((string) ($_GET['trip'] ?? ''), 0, 9)),
			'rankOn'     => $this->rankOn,
			'selfReact'  => $this->selfReact,
			'mapStyle'   => $this->mapStyle,
			'mapChoice'  => $this->mapChoice,
			'mapStyles'  => $this->mapChoice ? array_map(static fn (string $id, array $s): array => ['id' => $id, 'label' => $s['label'], 'water' => $s['water'], 'land' => $s['land']], array_keys($this->mapStyles), array_values($this->mapStyles)) : [],
			'isAdmin'    => $user->authorise('core.admin'),
			'rankUrl'    => Route::_('index.php?option=com_mapaviajes&task=ranking.data&format=json', false),
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
		$wa->registerAndUseStyle('com_mapaviajes.fonts', 'com_mapaviajes/fonts.css', ['version' => 'auto'])
			->registerAndUseStyle('com_mapaviajes.map', 'com_mapaviajes/mapa.css', ['version' => 'auto'], [], ['com_mapaviajes.fonts'])
			->registerAndUseScript('com_mapaviajes.map', 'com_mapaviajes/mapa.js', ['version' => 'auto'], ['defer' => true], ['core']);

		if (($css = $settings->customCss()) !== '')
		{
			$wa->addInlineStyle($css, [], [], ['com_mapaviajes.map']);
		}

		// Only the looks that can be shown: the chosen one, or all of them when each person may pick (each file is a few kilobytes)
		foreach (array_keys($this->mapStyles) as $id)
		{
			if ($id !== 'pergamino' && ($this->mapChoice || $id === $this->mapStyle))
			{
				$wa->registerAndUseStyle('com_mapaviajes.mapa.' . $id, 'com_mapaviajes/maps/' . $id . '.css', ['version' => 'auto'], [], ['com_mapaviajes.map']);
			}
		}

		parent::display($tpl);
	}
}
