<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

defined('_JEXEC') || die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/** @var \Chaska\Component\Mapaviajes\Site\View\Tripcomments\HtmlView $this */
$e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="mv-comments-page" style="padding:12px 16px;max-width:760px;margin:0 auto">
	<h1 class="visually-hidden"><?php echo $e($this->title); ?></h1>
	<?php // The messages left by the comment extension (why a comment was refused, "awaiting moderation"...) are shown here: not every template prints them in a bare page
	foreach (Factory::getApplication()->getMessageQueue(true) as $m) :
		$type = in_array($m['type'] ?? '', ['error', 'warning', 'notice', 'message', 'info', 'success'], true) ? (string) $m['type'] : 'info';
		$bg   = ['error' => '#fde4e0', 'warning' => '#fff1cf', 'notice' => '#e3eef7', 'message' => '#e3f3e6', 'success' => '#e3f3e6', 'info' => '#e3eef7'][$type]; ?>
		<div role="alert" style="margin:0 0 10px;padding:10px 14px;border-radius:8px;background:<?php echo $bg; ?>;color:#2d1b10;border:1px solid rgba(45,27,16,.25)"><?php echo $e((string) ($m['message'] ?? '')); ?></div>
	<?php endforeach; ?>
	<?php if ($this->comments !== '') : ?>
		<?php echo $this->comments; // HTML produced by the installed comment extension (trusted code), never by a visitor ?>
	<?php else : ?>
		<p><?php echo $e(Text::_('COM_MAPAVIAJES_COMMENTS_UNAVAILABLE')); ?></p>
	<?php endif; ?>
	<?php if ($this->diag) : ?>
		<details style="margin:18px 0 0;font-size:.8125rem;color:#6b4f3a"><summary><?php echo $e(Text::_('COM_MAPAVIAJES_DIAG_TITLE')); ?></summary>
			<ul style="margin:6px 0;padding-left:18px">
			<?php foreach ($this->diag as $k => $v) : ?><li><?php echo $e(Text::_($k)); ?>: <strong><?php echo $e($v); ?></strong></li><?php endforeach; ?>
			</ul></details>
	<?php endif; ?>
</div>
