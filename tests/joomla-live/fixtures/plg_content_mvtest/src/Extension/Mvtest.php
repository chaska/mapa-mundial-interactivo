<?php
namespace Mvtest\Plugin\Content\Mvtest\Extension;
defined('_JEXEC') || die;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
final class Mvtest extends CMSPlugin implements SubscriberInterface
{
	protected $allowLegacyListeners = false;
	public static function getSubscribedEvents(): array { return ['onContentAfterDisplay' => 'after', 'onContentBeforeDisplay' => 'before']; }
	public function before($event): void { $a = array_values($event->getArguments()); if ($a[0] === 'com_mapaviajes.trip') { $event->addResult('<!--mv-before-->'); } }
	public function after($event): void
	{
		$a = array_values($event->getArguments());
		// A real comment extension would store/show comments for $a[1]->id under the context $a[0].
		$event->addResult('<div id="mv-events-ok" data-context="' . htmlspecialchars((string) $a[0]) . '" data-id="' . (int) $a[1]->id . '" data-asset="' . (int) ($a[1]->asset_id ?? 0) . '"></div>');
	}
}
