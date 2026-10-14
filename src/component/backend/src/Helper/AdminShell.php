<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\Helper;

defined('_JEXEC') || die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * The frame of every administrator screen of this component: its own side menu and title bar, so the component looks like an app of its own
 * (the screens are shown without Joomla's side menu and top bar; "Back to Joomla" is always in the menu).
 */
final class AdminShell
{
	/** @param string $active cpanel|trips|albums|ranking|raffle|settings   @param list<string> $actions ready HTML (buttons) shown at the right of the title */
	public static function open(string $active, string $title, string $sub = '', array $actions = []): string
	{
		$e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		$user = Factory::getApplication()->getIdentity();
		$nav = [
			'cpanel'   => ['COM_MAPAVIAJES_MENU_DASHBOARD', 'icon-home', 'index.php?option=com_mapaviajes'],
			'trips'    => ['COM_MAPAVIAJES_TRIPS_TITLE', 'icon-image', 'index.php?option=com_mapaviajes&view=trips'],
			'albums'   => ['COM_MAPAVIAJES_MENU_ALBUMS', 'icon-globe', 'index.php?option=com_mapaviajes&view=albums'],
			'ranking'  => ['COM_MAPAVIAJES_MENU_RANKING', 'icon-star', 'index.php?option=com_mapaviajes&view=ranking'],
			'raffle'   => ['COM_MAPAVIAJES_MENU_RAFFLE', 'icon-users', 'index.php?option=com_mapaviajes&view=raffle'],
		];

		if ($user->authorise('core.options', 'com_mapaviajes') || $user->authorise('core.admin', 'com_mapaviajes'))
		{
			$nav['settings'] = ['JOPTIONS', 'icon-cog', 'index.php?option=com_mapaviajes&view=settings'];
		}

		$html  = '<div class="mvA-app mvA-wrap"><nav class="mvA-side" aria-label="' . $e(Text::_('COM_MAPAVIAJES')) . '">';
		$html .= '<a class="mvA-brand" href="' . $e(Route::_('index.php?option=com_mapaviajes')) . '"><span class="mvA-mark" aria-hidden="true"><span class="icon-globe"></span></span><span class="mvA-brandt">' . $e(Text::_('COM_MAPAVIAJES')) . '</span></a>';
		$html .= '<ul class="mvA-nav">';

		foreach ($nav as $key => [$label, $icon, $url])
		{
			$html .= '<li><a href="' . $e(Route::_($url)) . '"' . ($key === $active ? ' class="on" aria-current="page"' : '') . '><span class="' . $e($icon) . '" aria-hidden="true"></span><span class="mvA-navt">' . $e(Text::_($label)) . '</span></a></li>';
		}

		$html .= '</ul><ul class="mvA-nav mvA-nav-end">';
		$html .= '<li><a href="' . $e(Uri::root() . 'index.php?option=com_mapaviajes') . '" target="_blank" rel="noopener"><span class="icon-eye" aria-hidden="true"></span><span class="mvA-navt">' . $e(Text::_('COM_MAPAVIAJES_CP_VIEW_MAP')) . '</span></a></li>';
		$html .= '<li><a href="' . $e(Uri::base() . 'index.php') . '"><span class="icon-arrow-left" aria-hidden="true"></span><span class="mvA-navt">' . $e(Text::_('COM_MAPAVIAJES_BACK_TO_JOOMLA')) . '</span></a></li>';
		$html .= '</ul></nav>';
		$html .= '<main class="mvA-main"><div class="mvA-head"><h1>' . $e($title) . ($sub !== '' ? '<small>' . $e($sub) . '</small>' : '') . '</h1>' . implode('', $actions) . '</div>';

		return $html;
	}

	public static function close(): string
	{
		return '</main></div>';
	}
}
