<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

defined('_JEXEC') || die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Chaska\Component\Mapaviajes\Administrator\View\Cpanel\HtmlView $this */
$e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$tile = function (string $link, string $icon, string $label, string $hint, ?int $n = null, bool $alert = false) use ($e) {
	return '<a class="mvA-tile' . ($alert ? ' mvA-alert' : '') . '" href="' . $e(Route::_($link)) . '"><span class="mvA-ico icon-' . $e($icon) . '" aria-hidden="true"></span><span class="mvA-txt"><strong>' . $e($label) . '</strong><small>' . $e($hint) . '</small></span>'
		. ($n !== null ? '<b class="mvA-n">' . $n . '</b>' : '') . '</a>';
};
$sw = function (string $name, string $label, string $desc) use ($e) {
	$on = $this->values[$name] === '1';
	return '<label class="mvA-row"><span class="mvA-lab"><strong>' . $e($label) . '</strong><small>' . $e($desc) . '</small></span>'
		. '<input type="checkbox" role="switch" class="mvA-sw" data-name="' . $e($name) . '"' . ($on ? ' checked' : '') . ($this->canOptions ? '' : ' disabled') . '><span class="mvA-track" aria-hidden="true"></span></label>';
};
?>
<div class="mvA-wrap">
	<section class="mvA-tiles" aria-label="<?php echo $e(Text::_('COM_MAPAVIAJES_CP_DOORS')); ?>">
		<?php echo $tile('index.php?option=com_mapaviajes&view=trips&filter[published]=0', 'clock', Text::_('COM_MAPAVIAJES_CP_PENDING'), Text::_('COM_MAPAVIAJES_CP_PENDING_HINT'), $this->stats['pending'], $this->stats['pending'] > 0); ?>
		<?php echo $tile('index.php?option=com_mapaviajes&view=trips&filter[published]=1', 'image', Text::_('COM_MAPAVIAJES_CP_PUBLISHED'), sprintf(Text::_('COM_MAPAVIAJES_CP_PUBLISHED_HINT'), $this->stats['photos']), $this->stats['published']); ?>
		<?php echo $tile('index.php?option=com_mapaviajes&view=albums', 'globe', Text::_('COM_MAPAVIAJES_MENU_ALBUMS'), Text::_('COM_MAPAVIAJES_CP_ALBUMS_HINT'), $this->stats['albums']); ?>
		<?php echo $tile('index.php?option=com_mapaviajes&view=ranking', 'star', Text::_('COM_MAPAVIAJES_MENU_RANKING'), Text::_('COM_MAPAVIAJES_CP_RANKING_HINT'), $this->stats['weeks']); ?>
		<?php echo $tile('index.php?option=com_mapaviajes&view=raffle', 'users', Text::_('COM_MAPAVIAJES_MENU_RAFFLE'), Text::_('COM_MAPAVIAJES_CP_RAFFLE_HINT'), $this->stats['people']); ?>
		<?php if ($this->canOptions) : ?>
			<?php echo $tile('index.php?option=com_config&view=component&component=com_mapaviajes', 'cog', Text::_('JOPTIONS'), Text::_('COM_MAPAVIAJES_CP_OPTIONS_HINT')); ?>
		<?php endif; ?>
	</section>

	<section class="mvA-panel" aria-labelledby="mvA-h-switches">
		<h2 id="mvA-h-switches"><?php echo $e(Text::_('COM_MAPAVIAJES_CP_SWITCHES')); ?></h2>
		<p class="mvA-sub"><?php echo $e(Text::_($this->canOptions ? 'COM_MAPAVIAJES_CP_AUTOSAVE' : 'COM_MAPAVIAJES_CP_READONLY')); ?> <span id="mvA-status" role="status" aria-live="polite"></span></p>
		<div class="mvA-group">
			<?php echo $sw('review_first', Text::_('COM_MAPAVIAJES_CONFIG_REVIEW_FIRST'), Text::_('COM_MAPAVIAJES_CP_REVIEW_HINT')); ?>
			<?php echo $sw('comments_default', Text::_('COM_MAPAVIAJES_CONFIG_COMMENTS_DEFAULT'), Text::_('COM_MAPAVIAJES_CP_COMMENTS_HINT')); ?>
			<?php echo $sw('show_real_names', Text::_('COM_MAPAVIAJES_CONFIG_REAL_NAMES'), Text::_('COM_MAPAVIAJES_CP_NAMES_HINT')); ?>
		</div>
		<div class="mvA-group">
			<?php echo $sw('rank_enabled', Text::_('COM_MAPAVIAJES_CONFIG_RANK_ENABLED'), Text::_('COM_MAPAVIAJES_CP_RANK_HINT')); ?>
			<?php echo $sw('notify_podium', Text::_('COM_MAPAVIAJES_CONFIG_NOTIFY_PODIUM'), Text::_('COM_MAPAVIAJES_CP_PODIUM_MAIL_HINT')); ?>
		</div>
		<div class="mvA-group">
			<?php echo $sw('notify_admin', Text::_('COM_MAPAVIAJES_CONFIG_NOTIFY_ADMIN'), Text::_('COM_MAPAVIAJES_CP_ADMIN_MAIL_HINT')); ?>
			<?php echo $sw('notify_author', Text::_('COM_MAPAVIAJES_CONFIG_NOTIFY_AUTHOR'), Text::_('COM_MAPAVIAJES_CP_AUTHOR_MAIL_HINT')); ?>
		</div>
		<div class="mvA-group">
			<label class="mvA-row"><span class="mvA-lab"><strong><?php echo $e(Text::_('COM_MAPAVIAJES_CONFIG_COMMENTS_PROVIDER')); ?></strong>
				<small><?php echo $e(Text::_($this->engage ? 'COM_MAPAVIAJES_CP_PROVIDER_ENGAGE_ON' : 'COM_MAPAVIAJES_CP_PROVIDER_ENGAGE_OFF')); ?></small></span>
				<select class="mvA-select" data-name="comments_provider"<?php echo $this->canOptions ? '' : ' disabled'; ?>>
					<?php foreach (['none' => 'COM_MAPAVIAJES_CONFIG_PROVIDER_NONE', 'engage' => 'COM_MAPAVIAJES_CONFIG_PROVIDER_ENGAGE', 'events' => 'COM_MAPAVIAJES_CONFIG_PROVIDER_EVENTS'] as $v => $k) : ?>
						<option value="<?php echo $e($v); ?>"<?php echo $this->values['comments_provider'] === $v ? ' selected' : ''; ?>><?php echo $e(Text::_($k)); ?></option>
					<?php endforeach; ?>
				</select></label>
		</div>
	</section>
</div>
