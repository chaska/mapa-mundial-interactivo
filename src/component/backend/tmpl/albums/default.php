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

/** @var \Chaska\Component\Mapaviajes\Administrator\View\Albums\HtmlView $this */

$this->getDocument()->getWebAssetManager()->useScript('table.columns')->useScript('multiselect')->useStyle('com_mapaviajes.admin');
$e = static fn (?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<form action="<?php echo Route::_('index.php?option=com_mapaviajes&view=albums'); ?>" method="post" name="adminForm" id="adminForm">
	<?php echo AdminShell::open('albums', Text::_('COM_MAPAVIAJES_ALBUMS_TITLE'), Text::_('COM_MAPAVIAJES_ALBUMS_SUB'), ['<a class="mvA-btn p" href="' . $e(Route::_('index.php?option=com_mapaviajes&task=album.add')) . '"><span class="icon-plus" aria-hidden="true"></span>' . $e(Text::_('COM_MAPAVIAJES_CP_NEW_ALBUM')) . '</a>']); ?>
		<?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>
		<?php if (empty($this->items)) : ?>
			<div class="mvA-card mvA-empty"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></div>
		<?php else : ?>
			<div class="mvA-grid mvA-g2" id="albumList">
			<?php foreach ($this->items as $i => $item) : $on = (int) $item->published === 1; ?>
				<article class="mvA-card mvA-album">
					<span class="mvA-logo" aria-hidden="true"><?php echo $e(mb_strtoupper(mb_substr((string) $item->title, 0, 1))); ?></span>
					<div class="grow">
						<h3><a href="<?php echo Route::_('index.php?option=com_mapaviajes&task=album.edit&id=' . (int) $item->id); ?>"><?php echo $e($item->title); ?></a></h3>
						<div class="m"><?php echo $e(Text::_('JGRID_HEADING_ACCESS')); ?>: <?php echo $e($item->access_level); ?> · #<?php echo (int) $item->id; ?></div>
						<span class="mvA-pill <?php echo $on ? 'ok' : 'n'; ?>"><?php echo Text::_($on ? 'JPUBLISHED' : 'JUNPUBLISHED'); ?></span>
						<span class="mvA-pill t"><?php echo (int) $item->trips; ?> <?php echo Text::_('COM_MAPAVIAJES_COL_TRIPS'); ?></span>
					</div>
					<span class="d-flex flex-column align-items-end gap-2">
						<label class="mvA-pick-inline"><?php echo HTMLHelper::_('grid.id', $i, (int) $item->id, false, 'cid', 'cb', $e($item->title)); ?></label>
						<a class="mvA-btn s" href="<?php echo Route::_('index.php?option=com_mapaviajes&task=album.edit&id=' . (int) $item->id); ?>"><?php echo Text::_('JACTION_EDIT'); ?></a>
					</span>
				</article>
			<?php endforeach; ?>
			</div>
			<?php echo $this->pagination->getListFooter(); ?>
		<?php endif; ?>
		<input type="hidden" name="task" value="">
		<input type="hidden" name="boxchecked" value="0">
		<?php echo HTMLHelper::_('form.token'); ?>
	<?php echo AdminShell::close(); ?>
</form>
