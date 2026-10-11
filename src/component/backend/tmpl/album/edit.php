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
use Joomla\CMS\Router\Route;

/** @var \Chaska\Component\Mapaviajes\Administrator\View\Album\HtmlView $this */

$this->getDocument()->getWebAssetManager()->useScript('keepalive')->useScript('form.validate');
?>
<form action="<?php echo Route::_('index.php?option=com_mapaviajes&layout=edit&id=' . (int) ($this->item->id ?? 0)); ?>" method="post" name="adminForm" id="album-form" class="form-validate" enctype="multipart/form-data">
	<div class="main-card p-3">
		<?php echo $this->form->renderField('title'); ?>
		<?php echo $this->form->renderField('description'); ?>
		<?php echo $this->form->renderField('published'); ?>
		<?php echo $this->form->renderField('access'); ?>
		<?php echo $this->form->renderField('allow_comments'); ?>
		<?php echo $this->form->renderField('ordering'); ?>
		<div class="control-group">
			<div class="control-label"><label for="logo_upload"><?php echo Text::_('COM_MAPAVIAJES_ALBUM_FIELD_LOGO'); ?></label></div>
			<div class="controls">
				<?php if ($this->logoPreview !== '') : ?>
					<p><img src="<?php echo $this->logoPreview; ?>" alt="<?php echo htmlspecialchars(Text::_('COM_MAPAVIAJES_ALBUM_LOGO_CURRENT'), ENT_QUOTES, 'UTF-8'); ?>" style="max-width:96px;max-height:96px;object-fit:contain;background:repeating-conic-gradient(#ddd 0 25%,#fff 0 50%) 0 0/16px 16px;border:1px solid #ccc;border-radius:8px"></p>
				<?php endif; ?>
				<input type="file" name="logo_upload" id="logo_upload" class="form-control" accept="image/png,image/jpeg,image/webp">
				<div class="form-text"><?php echo Text::_('COM_MAPAVIAJES_ALBUM_FIELD_LOGO_DESC'); ?></div>
				<?php if ($this->logoPreview !== '') : ?>
					<div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="logo_remove" id="logo_remove" value="1"><label class="form-check-label" for="logo_remove"><?php echo Text::_('COM_MAPAVIAJES_ALBUM_LOGO_REMOVE'); ?></label></div>
				<?php endif; ?>
			</div>
		</div>
		<?php echo $this->form->renderField('id'); ?>
	</div>
	<input type="hidden" name="task" value="">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
