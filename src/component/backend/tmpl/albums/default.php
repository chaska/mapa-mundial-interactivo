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

/** @var \Chaska\Component\Mapaviajes\Administrator\View\Albums\HtmlView $this */

$this->getDocument()->getWebAssetManager()->useScript('table.columns')->useScript('multiselect');
$e = static fn (?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<form action="<?php echo Route::_('index.php?option=com_mapaviajes&view=albums'); ?>" method="post" name="adminForm" id="adminForm">
	<div class="row"><div class="col-md-12"><div id="j-main-container" class="j-main-container">
		<?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>
		<?php if (empty($this->items)) : ?>
			<div class="alert alert-info"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></div>
		<?php else : ?>
			<table class="table" id="albumList">
				<caption class="visually-hidden"><?php echo Text::_('COM_MAPAVIAJES_ALBUMS_TITLE'); ?></caption>
				<thead><tr>
					<td class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></td>
					<th scope="col" class="w-1 text-center"><?php echo Text::_('JSTATUS'); ?></th>
					<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_MAPAVIAJES_COL_TITLE', 'a.title', $this->state->get('list.direction'), $this->state->get('list.ordering')); ?></th>
					<th scope="col" class="d-none d-md-table-cell"><?php echo Text::_('JGRID_HEADING_ACCESS'); ?></th>
					<th scope="col" class="d-none d-md-table-cell"><?php echo Text::_('COM_MAPAVIAJES_COL_TRIPS'); ?></th>
					<th scope="col" class="w-1 d-none d-md-table-cell"><?php echo Text::_('JGRID_HEADING_ID'); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ($this->items as $i => $item) : ?>
					<tr class="row<?php echo $i % 2; ?>">
						<td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, (int) $item->id, false, 'cid', 'cb', $e($item->title)); ?></td>
						<td class="text-center"><?php echo HTMLHelper::_('jgrid.published', (int) $item->published, $i, 'albums.', true, 'cb'); ?></td>
						<th scope="row"><a href="<?php echo Route::_('index.php?option=com_mapaviajes&task=album.edit&id=' . (int) $item->id); ?>"><?php echo $e($item->title); ?></a></th>
						<td class="d-none d-md-table-cell"><?php echo $e($item->access_level); ?></td>
						<td class="d-none d-md-table-cell"><?php echo (int) $item->trips; ?></td>
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
	</div></div></div>
</form>
