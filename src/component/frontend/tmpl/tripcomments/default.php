<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

defined('_JEXEC') || die;

use Joomla\CMS\Language\Text;

/** @var \Chaska\Component\Mapaviajes\Site\View\Tripcomments\HtmlView $this */
$e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="mv-comments-page" style="padding:12px 16px;max-width:760px;margin:0 auto">
	<h1 class="visually-hidden"><?php echo $e($this->title); ?></h1>
	<?php if ($this->comments !== '') : ?>
		<?php echo $this->comments; // HTML produced by the installed comment extension (trusted code), never by a visitor ?>
	<?php else : ?>
		<p><?php echo $e(Text::_('COM_MAPAVIAJES_COMMENTS_UNAVAILABLE')); ?></p>
	<?php endif; ?>
</div>
