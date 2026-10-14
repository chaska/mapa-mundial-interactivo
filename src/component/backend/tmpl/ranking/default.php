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

/** @var \Chaska\Component\Mapaviajes\Administrator\View\Ranking\HtmlView $this */
$this->getDocument()->getWebAssetManager()->useStyle('com_mapaviajes.admin');
$e = static fn (?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$who = static fn (array $r): string => $r['name'] !== '' && $r['name'] !== null ? $r['name'] . ' (' . $r['username'] . ')' : (string) $r['username'];
?>
<?php echo AdminShell::open('ranking', Text::_('COM_MAPAVIAJES_MENU_RANKING'), Text::_('COM_MAPAVIAJES_RANKING_SUB')); ?>
	<?php if (!$this->enabled) : ?><div class="alert alert-warning"><?php echo $e(Text::_('COM_MAPAVIAJES_RANKING_OFF')); ?></div><?php endif; ?>
	<form action="<?php echo $e(Route::_('index.php?option=com_mapaviajes&task=ranking.close')); ?>" method="post" class="mvA-inline">
		<button class="mvA-btn p" type="submit"><?php echo $e(Text::_('COM_MAPAVIAJES_RANKING_CLOSE')); ?></button>
		<small><?php echo $e(Text::_('COM_MAPAVIAJES_RANKING_CLOSE_HINT')); ?></small>
		<?php echo HTMLHelper::_('form.token'); ?>
	</form>

	<?php if (\count($this->rows) >= 1) : $top = \array_slice($this->rows, 0, 3); $order = [1, 0, 2]; $cols = ['#9aa0a6', '#d4a017', '#b0713a']; ?>
	<div class="mvA-podium" aria-label="<?php echo $e(Text::_('COM_MAPAVIAJES_RANKING_WEEK')); ?>">
		<?php foreach ($order as $k) : if (!isset($top[$k])) { continue; } $r = $top[$k]; ?>
			<div class="mvA-card mvA-pod<?php echo $k === 0 ? ' first' : ''; ?>"><div class="m" style="background:<?php echo $cols[$k]; ?>"><?php echo $k + 1; ?></div><strong><?php echo $e($who($r)); ?></strong><div class="pts"><?php echo (int) $r['points']; ?></div><small><?php echo (int) $r['trips']; ?> <?php echo $e(Text::_('COM_MAPAVIAJES_COL_TRIPS')); ?> · <?php echo (int) $r['likes']; ?> ♥ · <?php echo (int) $r['comments']; ?> ✎</small></div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<section class="mvA-card">
		<h2><?php echo $e(Text::_('COM_MAPAVIAJES_RANKING_WEEK')); ?></h2>
		<?php if ($this->weeks === []) : ?>
			<p><?php echo $e(Text::_('COM_MAPAVIAJES_RANKING_NONE')); ?></p>
		<?php else : ?>
			<form method="get" action="<?php echo $e(Route::_('index.php')); ?>" class="mvA-inline">
				<input type="hidden" name="option" value="com_mapaviajes"><input type="hidden" name="view" value="ranking">
				<label for="mvA-week"><?php echo $e(Text::_('COM_MAPAVIAJES_RANKING_PICK')); ?></label>
				<select id="mvA-week" name="week" class="form-select w-auto" onchange="this.form.submit()">
					<?php foreach ($this->weeks as $w) : ?><option value="<?php echo $e($w); ?>"<?php echo $w === $this->week ? ' selected' : ''; ?>><?php echo $e($w); ?></option><?php endforeach; ?>
				</select>
			</form>
			<table class="mvA-table"><caption class="visually-hidden"><?php echo $e(Text::_('COM_MAPAVIAJES_RANKING_WEEK')); ?></caption>
				<thead><tr><th>#</th><th><?php echo $e(Text::_('COM_MAPAVIAJES_COL_AUTHOR')); ?></th><th>★</th><th><?php echo $e(Text::_('COM_MAPAVIAJES_RANKING_SCORE')); ?></th><th><?php echo $e(Text::_('COM_MAPAVIAJES_COL_TRIPS')); ?></th><th><?php echo $e(Text::_('COM_MAPAVIAJES_COL_PHOTOS')); ?></th><th>♥</th><th>✎</th><th><?php echo $e(Text::_('COM_MAPAVIAJES_RANKING_POINTS')); ?></th></tr></thead>
				<tbody>
				<?php foreach ($this->rows as $r) : ?>
					<tr><td><?php echo (int) $r['position']; ?></td><td><?php echo $e($who($r)); ?></td><td><?php echo str_repeat('★', (int) $r['stars']); ?></td><td><?php echo (int) $r['score']; ?></td><td><?php echo (int) $r['trips']; ?></td><td><?php echo (int) $r['photos']; ?></td><td><?php echo (int) $r['likes']; ?></td><td><?php echo (int) $r['comments']; ?></td><td><?php echo (int) $r['points']; ?></td></tr>
				<?php endforeach; ?>
				</tbody></table>
		<?php endif; ?>
	</section>

	<section class="mvA-card">
		<h2><?php echo $e(Text::_('COM_MAPAVIAJES_RANKING_ALL')); ?></h2>
		<?php if ($this->all === []) : ?>
			<p><?php echo $e(Text::_('COM_MAPAVIAJES_RANKING_NONE')); ?></p>
		<?php else : ?>
			<table class="mvA-table"><caption class="visually-hidden"><?php echo $e(Text::_('COM_MAPAVIAJES_RANKING_ALL')); ?></caption>
				<thead><tr><th>#</th><th><?php echo $e(Text::_('COM_MAPAVIAJES_COL_AUTHOR')); ?></th><th><?php echo $e(Text::_('COM_MAPAVIAJES_RANKING_POINTS')); ?></th><th>★</th><th><?php echo $e(Text::_('COM_MAPAVIAJES_RANKING_PODIUMS')); ?></th><th><?php echo $e(Text::_('COM_MAPAVIAJES_RANKING_WEEKS')); ?></th></tr></thead>
				<tbody>
				<?php foreach ($this->all as $i => $r) : ?>
					<tr><td><?php echo $i + 1; ?></td><td><?php echo $e($who($r)); ?></td><td><?php echo (int) $r['points']; ?></td><td><?php echo (int) $r['stars']; ?></td><td><?php echo (int) $r['podiums']; ?></td><td><?php echo (int) $r['weeks']; ?></td></tr>
				<?php endforeach; ?>
				</tbody></table>
		<?php endif; ?>
	</section>
<?php echo AdminShell::close(); ?>

