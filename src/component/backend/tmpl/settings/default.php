<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

defined('_JEXEC') || die;

use Chaska\Component\Mapaviajes\Administrator\Helper\AdminShell;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Chaska\Component\Mapaviajes\Administrator\View\Settings\HtmlView $this */
$this->getDocument()->getWebAssetManager()->useScript('keepalive')->useScript('form.validate')->useStyle('com_mapaviajes.admin')->useScript('com_mapaviajes.admin');
$e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<form action="<?php echo $e(Route::_('index.php?option=com_mapaviajes&task=settings.save', false)); ?>" method="post" name="adminForm" id="settings-form" class="form-validate">
<?php echo AdminShell::open('settings', Text::_('JOPTIONS'), Text::_('COM_MAPAVIAJES_SETTINGS_SUB'), [
	'<a class="mvA-btn g" href="' . $e(Route::_('index.php?option=com_config&view=component&component=com_mapaviajes', false)) . '"><span class="icon-lock" aria-hidden="true"></span>' . $e(Text::_('COM_MAPAVIAJES_SETTINGS_PERMS')) . '</a>',
	'<button type="button" class="mvA-btn p" data-mv-form="settings-form" data-mv-submit="settings.save"><span class="icon-check" aria-hidden="true"></span>' . Text::_('JSAVE') . '</button>',
]); ?>
	<div class="mvA-grid mvA-g2">
	<?php foreach ($this->sections as $name => $info) : ?>
		<section class="mvA-card">
			<h2><?php echo $e($info['label']); ?></h2>
			<?php if ($info['description'] !== '') : ?><p class="mvA-sub"><?php echo $e($info['description']); ?></p><?php endif; ?>
			<?php echo $this->form->renderFieldset($name); ?>
		</section>
	<?php endforeach; ?>
	</div>
<?php echo AdminShell::close(); ?>
	<input type="hidden" name="task" value="">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
