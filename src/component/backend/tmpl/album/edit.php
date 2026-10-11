<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

defined('_JEXEC') || die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

/** @var \Chaska\Component\Mapaviajes\Administrator\View\Album\HtmlView $this */

$this->getDocument()->getWebAssetManager()->useScript('keepalive')->useScript('form.validate');
?>
<form action="<?php echo Route::_('index.php?option=com_mapaviajes&layout=edit&id=' . (int) ($this->item->id ?? 0)); ?>" method="post" name="adminForm" id="album-form" class="form-validate">
	<div class="main-card p-3">
		<?php echo $this->form->renderField('title'); ?>
		<?php echo $this->form->renderField('description'); ?>
		<?php echo $this->form->renderField('published'); ?>
		<?php echo $this->form->renderField('access'); ?>
		<?php echo $this->form->renderField('allow_comments'); ?>
		<?php echo $this->form->renderField('ordering'); ?>
		<?php echo $this->form->renderField('id'); ?>
	</div>
	<input type="hidden" name="task" value="">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
