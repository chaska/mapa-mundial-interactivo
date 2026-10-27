<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\View\Cpanel;

defined('_JEXEC') || die;

use Chaska\Component\Mapaviajes\Administrator\Service\Settings;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;

/** The control panel: numbers that matter, the doors to everything, and the switches that save themselves. */
class HtmlView extends BaseHtmlView
{
	/** @var array<string, int> */
	protected array $stats = [];
	/** @var array<string, string> */
	protected array $values = [];
	protected bool $canOptions = false;
	protected bool $engage = false;
	protected string $token = '';

	public function display($tpl = null): void
	{
		$db  = Factory::getContainer()->get(DatabaseInterface::class);
		$one = static fn (string $sql): int => (int) $db->setQuery($sql)->loadResult();
		$this->stats = [
			'pending'   => $one('SELECT COUNT(*) FROM ' . $db->quoteName('#__mapaviajes_trips') . ' WHERE published = 0'),
			'published' => $one('SELECT COUNT(*) FROM ' . $db->quoteName('#__mapaviajes_trips') . ' WHERE published = 1'),
			'photos'    => $one('SELECT COUNT(*) FROM ' . $db->quoteName('#__mapaviajes_photos')),
			'albums'    => $one('SELECT COUNT(*) FROM ' . $db->quoteName('#__mapaviajes_albums') . ' WHERE published = 1'),
			'people'    => $one('SELECT COUNT(DISTINCT user_id) FROM ' . $db->quoteName('#__mapaviajes_trips') . ' WHERE published = 1 AND photo_count > 0'),
			'reactions' => $one('SELECT COUNT(*) FROM ' . $db->quoteName('#__mapaviajes_reactions') . ' WHERE active = 1'),
			'weeks'     => $one('SELECT COUNT(*) FROM ' . $db->quoteName('#__mapaviajes_weeks')),
		];

		$p = ComponentHelper::getParams('com_mapaviajes');
		$s = new Settings($p);
		$this->values = [
			'review_first'      => (string) (int) $s->reviewFirst(),
			'comments_default'  => (string) (int) $s->commentsDefault(),
			'show_real_names'   => (string) (int) $s->showRealNames(),
			'profile_photos'    => (string) (int) $s->profilePhotos(),
			'notify_admin'      => (string) (int) $s->notifyAdmin(),
			'notify_author'     => (string) (int) $s->notifyAuthor(),
			'rank_enabled'      => (string) (int) $s->rankEnabled(),
			'notify_podium'     => (string) (int) $s->notifyPodium(),
			'comments_provider' => $s->commentsProvider(),
			'map_user_choice'   => (string) (int) $s->mapUserChoice(),
			'map_style'         => $s->mapStyle(),
		];
		$canDo            = ContentHelper::getActions('com_mapaviajes');
		$this->canOptions = $canDo->get('core.options') || $canDo->get('core.admin');
		$this->engage     = ComponentHelper::isEnabled('com_engage');
		$this->token      = Session::getFormToken();

		ToolbarHelper::title(Text::_('COM_MAPAVIAJES'), 'globe');

		if ($this->canOptions)
		{
			$this->getDocument()->getToolbar()->preferences('com_mapaviajes');
		}

		$wa = $this->getDocument()->getWebAssetManager();
		$wa->useStyle('com_mapaviajes.admin')->useScript('com_mapaviajes.admin');
		$this->getDocument()->addScriptOptions('com_mapaviajes.admin', ['url' => 'index.php?option=com_mapaviajes&task=cpanel.setting&format=json', 'token' => $this->token, 'saved' => Text::_('COM_MAPAVIAJES_CP_SAVED'), 'error' => Text::_('COM_MAPAVIAJES_CP_ERROR')]);

		parent::display($tpl);
	}
}
