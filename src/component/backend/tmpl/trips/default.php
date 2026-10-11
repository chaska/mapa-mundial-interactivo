<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

defined('_JEXEC') || die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Chaska\Component\Mapaviajes\Administrator\View\Trips\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('table.columns')->useScript('multiselect');

$e   = static fn (?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$map = [1 => ['bg-success', 'JPUBLISHED'], 0 => ['bg-warning text-dark', 'COM_MAPAVIAJES_STATE_PENDING'], 2 => ['bg-secondary', 'COM_MAPAVIAJES_STATE_HIDDEN'], -2 => ['bg-dark', 'JTRASHED'], -3 => ['bg-danger', 'COM_MAPAVIAJES_STATE_SPAM']];
?>
<form action="<?php echo Route::_('index.php?option=com_mapaviajes&view=trips'); ?>" method="post" name="adminForm" id="adminForm">
	<div class="row">
		<div class="col-md-12">
			<div id="j-main-container" class="j-main-container">
				<?php foreach ($this->warnings as $w) : ?>
					<div class="alert alert-warning" role="alert"><?php echo $e($w); ?></div>
				<?php endforeach; ?>
				<?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>
				<?php if (empty($this->items)) : ?>
					<div class="alert alert-info"><span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span> <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></div>
				<?php else : ?>
					<table class="table" id="tripList">
						<caption class="visually-hidden"><?php echo Text::_('COM_MAPAVIAJES_TRIPS_TITLE'); ?></caption>
						<thead>
							<tr>
								<td class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></td>
								<th scope="col" class="w-1"><?php echo Text::_('COM_MAPAVIAJES_COL_PHOTO'); ?></th>
								<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_MAPAVIAJES_COL_TITLE', 'a.title', $this->state->get('list.direction'), $this->state->get('list.ordering')); ?></th>
								<th scope="col" class="d-none d-md-table-cell"><?php echo Text::_('COM_MAPAVIAJES_COL_AUTHOR'); ?></th>
								<th scope="col" class="d-none d-md-table-cell"><?php echo Text::_('COM_MAPAVIAJES_COL_ALBUM'); ?></th>
								<th scope="col" class="w-10"><?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.published', $this->state->get('list.direction'), $this->state->get('list.ordering')); ?></th>
								<th scope="col" class="w-10 d-none d-md-table-cell"><?php echo HTMLHelper::_('searchtools.sort', 'COM_MAPAVIAJES_COL_DATE', 'a.created', $this->state->get('list.direction'), $this->state->get('list.ordering')); ?></th>
								<th scope="col" class="w-1 d-none d-md-table-cell"><?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $this->state->get('list.direction'), $this->state->get('list.ordering')); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ($this->items as $i => $item) : [$cls, $label] = $map[(int) $item->published] ?? ['bg-secondary', 'JUNPUBLISHED']; ?>
							<tr class="row<?php echo $i % 2; ?>">
								<td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, (int) $item->id, false, 'cid', 'cb', $e($item->title)); ?></td>
								<td>
									<?php if ($item->first_photo) : ?>
										<a href="<?php echo Route::_('index.php?option=com_mapaviajes&task=photo.view&id=' . (int) $item->first_photo); ?>" target="_blank" rel="noopener noreferrer">
											<img src="<?php echo Route::_('index.php?option=com_mapaviajes&task=photo.view&id=' . (int) $item->first_photo); ?>" alt="" width="56" height="56" loading="lazy" style="object-fit:cover;border-radius:6px">
										</a>
									<?php endif; ?>
								</td>
								<th scope="row">
									<?php echo $e($item->title); ?>
									<div class="small text-muted"><?php echo $e($item->place); ?> · <?php echo (int) $item->photo_count; ?> <?php echo Text::_('COM_MAPAVIAJES_COL_PHOTOS'); ?></div>
								</th>
								<td class="d-none d-md-table-cell"><?php echo $e($item->author_name ?: $item->author_username); ?></td>
								<td class="d-none d-md-table-cell"><?php echo $e($item->album_title); ?></td>
								<td><span class="badge <?php echo $cls; ?>"><?php echo Text::_($label); ?></span></td>
								<td class="d-none d-md-table-cell"><?php echo HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC4')); ?></td>
								<td class="d-none d-md-table-cell"><?php echo (int) $item->id; ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<?php echo $this->pagination->getListFooter(); ?>
				<?php endif; ?>
				<input type="hidden" name="task" value="">
				<input type="hidden" name="boxchecked" value="0">
				<?php echo HTMLHelper::_('form.token'); ?>
			</div>
		</div>
	</div>
</form>
