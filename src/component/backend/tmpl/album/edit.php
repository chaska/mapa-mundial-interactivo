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
use Joomla\CMS\Router\Route;

/** @var \Chaska\Component\Mapaviajes\Administrator\View\Album\HtmlView $this */

$this->getDocument()->getWebAssetManager()->useScript('keepalive')->useScript('form.validate')->useStyle('com_mapaviajes.admin');
$e = static fn (?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<form action="<?php echo Route::_('index.php?option=com_mapaviajes&layout=edit&id=' . (int) ($this->item->id ?? 0)); ?>" method="post" name="adminForm" id="album-form" class="form-validate" enctype="multipart/form-data">
	<?php echo AdminShell::open('albums', Text::_('COM_MAPAVIAJES_ALBUM_EDIT'), Text::_('COM_MAPAVIAJES_ALBUM_EDIT_SUB'), [
		'<button type="button" class="mvA-btn g" data-mv-form="album-form" data-mv-submit="album.cancel">' . Text::_('JCANCEL') . '</button>',
		'<button type="button" class="mvA-btn" data-mv-form="album-form" data-mv-submit="album.apply">' . Text::_('JAPPLY') . '</button>',
		'<button type="button" class="mvA-btn p" data-mv-form="album-form" data-mv-submit="album.save"><span class="icon-check" aria-hidden="true"></span>' . Text::_('JSAVE') . '</button>',
	]); ?>
		<div class="mvA-grid mvA-g23">
			<div>
				<section class="mvA-card"><h2><?php echo Text::_('COM_MAPAVIAJES_ALBUM_DATA'); ?></h2><p class="mvA-sub"><?php echo Text::_('COM_MAPAVIAJES_ALBUM_DATA_SUB'); ?></p>
					<?php echo $this->form->renderField('title'); ?>
					<?php echo $this->form->renderField('description'); ?>
					<?php echo $this->form->renderField('published'); ?>
					<?php echo $this->form->renderField('access'); ?>
					<?php echo $this->form->renderField('allow_comments'); ?>
					<?php echo $this->form->renderField('ordering'); ?>
					<?php echo $this->form->renderField('id'); ?>
				</section>
			</div>
			<section class="mvA-card"><h2><?php echo Text::_('COM_MAPAVIAJES_ALBUM_FIELD_LOGO'); ?></h2><p class="mvA-sub"><?php echo Text::_('COM_MAPAVIAJES_ALBUM_FIELD_LOGO_DESC'); ?></p>
				<div class="mvA-drop">
					<?php if ($this->logoPreview !== '') : ?><span class="mvA-logo"><img src="<?php echo $this->logoPreview; ?>" alt="<?php echo $e(Text::_('COM_MAPAVIAJES_ALBUM_LOGO_CURRENT')); ?>"></span><?php endif; ?>
					<div><label for="logo_upload" class="visually-hidden"><?php echo Text::_('COM_MAPAVIAJES_ALBUM_FIELD_LOGO'); ?></label>
						<input type="file" name="logo_upload" id="logo_upload" class="form-control" accept="image/png,image/jpeg,image/webp"></div>
				</div>
				<?php if ($this->logoPreview !== '') : ?>
					<div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="logo_remove" id="logo_remove" value="1"><label class="form-check-label" for="logo_remove"><?php echo Text::_('COM_MAPAVIAJES_ALBUM_LOGO_REMOVE'); ?></label></div>
				<?php endif; ?>
			</section>
		</div>
	<?php echo AdminShell::close(); ?>
	<input type="hidden" name="task" value="">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
