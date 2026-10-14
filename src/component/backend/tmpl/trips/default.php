<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

defined('_JEXEC') || die;

use Joomla\CMS\HTML\HTMLHelper;
use Chaska\Component\Mapaviajes\Administrator\Helper\AdminShell;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Chaska\Component\Mapaviajes\Administrator\View\Trips\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('table.columns')->useScript('multiselect')->useStyle('com_mapaviajes.admin')->useScript('com_mapaviajes.admin');

$e   = static fn (?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$map = [1 => ['ok', 'JPUBLISHED'], 0 => ['w', 'COM_MAPAVIAJES_STATE_PENDING'], 2 => ['n', 'COM_MAPAVIAJES_STATE_HIDDEN'], -2 => ['b', 'JTRASHED']];
$cur = (string) $this->state->get('filter.published');
$c   = $this->counts;
$chips = [
	['', 'COM_MAPAVIAJES_CHIP_ALL', ($c[0] ?? 0) + ($c[1] ?? 0) + ($c[2] ?? 0)],
	['0', 'COM_MAPAVIAJES_STATE_PENDING', $c[0] ?? 0],
	['1', 'JPUBLISHED', $c[1] ?? 0],
	['2', 'COM_MAPAVIAJES_STATE_HIDDEN', $c[2] ?? 0],
	['-2', 'JTRASHED', $c[-2] ?? 0],
];
$canState = $this->getCurrentUser()->authorise('core.edit.state', 'com_mapaviajes');
$photo = static fn (int $id): string => Route::_('index.php?option=com_mapaviajes&task=photo.view&id=' . $id);
$canDelete = $this->getCurrentUser()->authorise('core.delete', 'com_mapaviajes');
$headActions = [];

if ((new \Chaska\Component\Mapaviajes\Administrator\Service\Settings())->commentsProvider() === 'engage' && \Joomla\CMS\Component\ComponentHelper::isEnabled('com_engage') && $this->getCurrentUser()->authorise('core.manage', 'com_engage'))
{
	$headActions[] = '<a class="mvA-btn t" href="' . $e(Route::_('index.php?option=com_engage&view=comments', false)) . '"><span class="icon-comments" aria-hidden="true"></span>' . $e(Text::_('COM_MAPAVIAJES_BTN_COMMENTS')) . '</a>';
}

$ok = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12.500 4.500 4.500L19 7.500"/></svg>';
?>
<form action="<?php echo Route::_('index.php?option=com_mapaviajes&view=trips'); ?>" method="post" name="adminForm" id="adminForm">
	<?php echo AdminShell::open('trips', Text::_('COM_MAPAVIAJES_TRIPS_TITLE'), Text::_('COM_MAPAVIAJES_TRIPS_SUB'), $headActions); ?>
		<?php foreach ($this->warnings as $w) : ?>
			<div class="alert alert-warning" role="alert"><?php echo $e($w); ?></div>
		<?php endforeach; ?>
		<nav class="mvA-chips" aria-label="<?php echo $e(Text::_('JSTATUS')); ?>">
			<?php foreach ($chips as [$val, $label, $n]) : ?>
				<a class="mvA-chip<?php echo $cur === $val ? ' on' : ''; ?>" href="<?php echo $e(Route::_('index.php?option=com_mapaviajes&view=trips&filter[published]=' . $val)); ?>"<?php echo $cur === $val ? ' aria-current="true"' : ''; ?>><?php echo Text::_($label); ?><b><?php echo (int) $n; ?></b></a>
			<?php endforeach; ?>
		</nav>
		<?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>
		<?php if (empty($this->items)) : ?>
			<div class="mvA-card mvA-empty"><span class="icon-info-circle" aria-hidden="true"></span> <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></div>
		<?php else : ?>
			<div class="mvA-grid mvA-g3" id="tripList">
			<?php foreach ($this->items as $i => $item) : [$cls, $label] = $map[(int) $item->published] ?? ['n', 'JUNPUBLISHED']; $st = (int) $item->published; $name = (string) ($item->author_name ?: $item->author_username); ?>
				<article class="mvA-card mvA-trip">
					<label class="mvA-pick" title="<?php echo $e(Text::_('JSELECT')); ?>"><?php echo HTMLHelper::_('grid.id', $i, (int) $item->id, false, 'cid', 'cb', $e($item->title)); ?><?php echo $ok; ?></label>
					<div class="mvA-ph">
						<?php if ($item->first_photo) : ?><a href="<?php echo $photo((int) $item->first_photo); ?>" target="_blank" rel="noopener noreferrer"><img src="<?php echo $photo((int) $item->first_photo); ?>" alt="" loading="lazy" decoding="async"></a>
						<?php else : ?><span class="icon-image" aria-hidden="true"></span><?php endif; ?>
					</div>
					<div><h3><?php echo $e($item->title); ?></h3>
						<div class="m"><?php echo $e($item->place); ?> · <?php echo (int) $item->photo_count; ?> <?php echo Text::_('COM_MAPAVIAJES_COL_PHOTOS'); ?> · #<?php echo (int) $item->id; ?></div></div>
					<div class="mvA-who"><span class="mvA-av" aria-hidden="true"><?php echo $e(mb_strtoupper(mb_substr($name, 0, 2))); ?></span><?php echo $e($name); ?> · <?php echo HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC4')); ?>
						<span class="mvA-pill <?php echo $cls; ?>"><?php echo Text::_($label); ?></span></div>
					<?php if ($canState) : ?>
					<div class="mvA-acts">
						<?php if ($st === 0 || $st === 2) : ?><button type="button" class="mvA-btn p s" data-mv-task="trips.publish" data-mv-cb="cb<?php echo $i; ?>"><span class="icon-publish" aria-hidden="true"></span><?php echo Text::_('COM_MAPAVIAJES_ACT_PUBLISH'); ?></button><?php endif; ?>
						<?php if ($st === 1) : ?><button type="button" class="mvA-btn s" data-mv-task="trips.archive" data-mv-cb="cb<?php echo $i; ?>"><span class="icon-eye-close" aria-hidden="true"></span><?php echo Text::_('COM_MAPAVIAJES_BTN_HIDE'); ?></button><?php endif; ?>
						<?php if ($st === -2) : ?><button type="button" class="mvA-btn t s" data-mv-task="trips.unpublish" data-mv-cb="cb<?php echo $i; ?>"><?php echo Text::_('COM_MAPAVIAJES_ACT_RESTORE'); ?></button><?php endif; ?>
						<?php if ($st !== -2) : ?><button type="button" class="mvA-btn g d s" data-mv-task="trips.trash" data-mv-cb="cb<?php echo $i; ?>" aria-label="<?php echo $e(Text::_('JTOOLBAR_TRASH')); ?>"><span class="icon-trash" aria-hidden="true"></span></button><?php endif; ?>
					</div>
					<?php endif; ?>
				</article>
			<?php endforeach; ?>
			</div>
			<?php if ($canState) : ?>
			<div class="mvA-bulk" id="mvA-bulk" role="region" aria-label="<?php echo $e(Text::_('COM_MAPAVIAJES_BULK')); ?>">
				<span><span id="mvA-bulkN">0</span> <?php echo Text::_('COM_MAPAVIAJES_SELECTED'); ?></span>
				<button type="button" class="mvA-btn p" data-mv-bulk="trips.publish"><?php echo Text::_('COM_MAPAVIAJES_ACT_PUBLISH'); ?></button>
				<button type="button" class="mvA-btn" data-mv-bulk="trips.archive"><?php echo Text::_('COM_MAPAVIAJES_BTN_HIDE'); ?></button>
				<?php if ($cur !== '-2') : ?><button type="button" class="mvA-btn" data-mv-bulk="trips.report"><?php echo Text::_('COM_MAPAVIAJES_BTN_SPAM'); ?></button><?php endif; ?>
				<?php if ($cur !== '-2') : ?><button type="button" class="mvA-btn d" data-mv-bulk="trips.trash"><?php echo Text::_('JTOOLBAR_TRASH'); ?></button><?php endif; ?>
				<?php if ($cur !== '-2' && $canDelete) : ?><button type="button" class="mvA-btn d" data-mv-bulk="trips.purge" data-mv-confirm="<?php echo $e(Text::_('COM_MAPAVIAJES_PURGE_CONFIRM')); ?>" data-mv-ok="<?php echo $e(Text::_('COM_MAPAVIAJES_BTN_PURGE')); ?>" data-mv-no="<?php echo $e(Text::_('JCANCEL')); ?>"><?php echo Text::_('COM_MAPAVIAJES_BTN_PURGE'); ?></button><?php endif; ?>
				<?php if ($cur === '-2' && $canDelete) : ?><button type="button" class="mvA-btn d" data-mv-bulk="trips.delete" data-mv-confirm="<?php echo $e(Text::_('JGLOBAL_CONFIRM_DELETE')); ?>" data-mv-ok="<?php echo $e(Text::_('JTOOLBAR_DELETE_FROM_TRASH')); ?>" data-mv-no="<?php echo $e(Text::_('JCANCEL')); ?>"><?php echo Text::_('JTOOLBAR_DELETE_FROM_TRASH'); ?></button><?php endif; ?>
			</div>
			<?php endif; ?>
			<?php echo $this->pagination->getListFooter(); ?>
		<?php endif; ?>
		<input type="hidden" name="task" value="">
		<input type="hidden" name="boxchecked" value="0">
		<?php echo HTMLHelper::_('form.token'); ?>
	<?php echo AdminShell::close(); ?>
</form>
