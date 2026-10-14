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

/** @var \Chaska\Component\Mapaviajes\Administrator\View\Raffle\HtmlView $this */
$e = static fn (?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$hidden = fn () => '<input type="hidden" name="from" value="' . $e($this->f['from']) . '"><input type="hidden" name="to" value="' . $e($this->f['to']) . '"><input type="hidden" name="min" value="' . (int) $this->f['min'] . '"><input type="hidden" name="album" value="' . (int) $this->f['album'] . '">' . HTMLHelper::_('form.token');
?>
<?php echo AdminShell::open('raffle', Text::_('COM_MAPAVIAJES_MENU_RAFFLE'), Text::_('COM_MAPAVIAJES_RAFFLE_SUB')); ?>
	<div class="alert alert-info"><?php echo $e(Text::_('COM_MAPAVIAJES_RAFFLE_NOTE')); ?></div>

	<section class="mvA-card">
		<h2><?php echo $e(Text::_('COM_MAPAVIAJES_RAFFLE_FILTERS')); ?></h2>
		<form method="get" action="<?php echo $e(Route::_('index.php', false)); ?>" class="mvA-filters">
			<input type="hidden" name="option" value="com_mapaviajes"><input type="hidden" name="view" value="raffle">
			<label><?php echo $e(Text::_('COM_MAPAVIAJES_RAFFLE_FROM')); ?><input type="date" name="from" value="<?php echo $e($this->f['from']); ?>" class="form-control"></label>
			<label><?php echo $e(Text::_('COM_MAPAVIAJES_RAFFLE_TO')); ?><input type="date" name="to" value="<?php echo $e($this->f['to']); ?>" class="form-control"></label>
			<label><?php echo $e(Text::_('COM_MAPAVIAJES_RAFFLE_MIN')); ?><input type="number" name="min" min="1" max="1000" value="<?php echo (int) $this->f['min']; ?>" class="form-control"></label>
			<label><?php echo $e(Text::_('COM_MAPAVIAJES_FILTER_ALBUM')); ?>
				<select name="album" class="form-select"><option value=""><?php echo $e(Text::_('JALL')); ?></option>
					<?php foreach ($this->albums as $a) : ?><option value="<?php echo (int) $a['id']; ?>"<?php echo (int) $a['id'] === (int) $this->f['album'] ? ' selected' : ''; ?>><?php echo $e((string) $a['title']); ?></option><?php endforeach; ?>
				</select></label>
			<button class="mvA-btn t" type="submit"><?php echo $e(Text::_('JSEARCH_FILTER')); ?></button>
		</form>
	</section>

	<?php if ($this->winners !== []) : ?>
	<section class="mvA-card mvA-winners" aria-live="polite">
		<h2><?php echo $e(Text::_('COM_MAPAVIAJES_RAFFLE_WINNERS')); ?></h2>
		<ol><?php foreach ($this->winners as $w) : ?><li><strong><?php echo $e($w['name'] !== '' ? $w['name'] : $w['username']); ?></strong> <small>(<?php echo $e($w['username']); ?> · <?php echo $e($w['email']); ?>)</small></li><?php endforeach; ?></ol>
	</section>
	<?php endif; ?>

	<section class="mvA-card">
		<h2><?php echo $e(sprintf(Text::_('COM_MAPAVIAJES_RAFFLE_LIST'), \count($this->people))); ?></h2>
		<div class="mvA-inline">
			<form method="post" action="<?php echo $e(Route::_('index.php?option=com_mapaviajes&task=raffle.csv', false)); ?>"><?php echo $hidden(); ?>
				<button class="mvA-btn" type="submit"<?php echo $this->people === [] ? ' disabled' : ''; ?>><?php echo $e(Text::_('COM_MAPAVIAJES_RAFFLE_CSV')); ?></button></form>
			<form method="post" action="<?php echo $e(Route::_('index.php?option=com_mapaviajes&task=raffle.draw', false)); ?>" class="mvA-inline"><?php echo $hidden(); ?>
				<label><?php echo $e(Text::_('COM_MAPAVIAJES_RAFFLE_WINNERS_N')); ?> <input type="number" name="winners" min="1" max="50" value="1" class="form-control w-auto d-inline-block"></label>
				<button class="mvA-btn p" type="submit"<?php echo $this->people === [] ? ' disabled' : ''; ?>><?php echo $e(Text::_('COM_MAPAVIAJES_RAFFLE_DRAW')); ?></button></form>
		</div>
		<?php if ($this->people === []) : ?>
			<p><?php echo $e(Text::_('JGLOBAL_NO_MATCHING_RESULTS')); ?></p>
		<?php else : ?>
			<table class="mvA-table" id="raffleList"><caption class="visually-hidden"><?php echo $e(Text::_('COM_MAPAVIAJES_MENU_RAFFLE')); ?></caption>
				<thead><tr><th>ID</th><th><?php echo $e(Text::_('COM_MAPAVIAJES_COL_AUTHOR')); ?></th><th><?php echo $e(Text::_('JGLOBAL_EMAIL')); ?></th><th><?php echo $e(Text::_('COM_MAPAVIAJES_COL_TRIPS')); ?></th><th><?php echo $e(Text::_('COM_MAPAVIAJES_COL_PHOTOS')); ?></th><th><?php echo $e(Text::_('COM_MAPAVIAJES_RAFFLE_FIRST')); ?></th><th><?php echo $e(Text::_('COM_MAPAVIAJES_RAFFLE_LAST')); ?></th><th><?php echo $e(Text::_('COM_MAPAVIAJES_RANKING_POINTS')); ?></th></tr></thead>
				<tbody>
				<?php foreach ($this->people as $p) : ?>
					<tr><td><?php echo (int) $p['id']; ?></td><td><?php echo $e($p['name'] !== '' ? $p['name'] . ' (' . $p['username'] . ')' : $p['username']); ?></td><td><?php echo $e($p['email']); ?></td><td><?php echo (int) $p['trips']; ?></td><td><?php echo (int) $p['photos']; ?></td><td><?php echo $e(substr((string) $p['first_trip'], 0, 10)); ?></td><td><?php echo $e(substr((string) $p['last_trip'], 0, 10)); ?></td><td><?php echo (int) $p['points']; ?></td></tr>
				<?php endforeach; ?>
				</tbody></table>
		<?php endif; ?>
	</section>
<?php echo AdminShell::close(); ?>

