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
use Joomla\CMS\Uri\Uri;

/** @var \Chaska\Component\Mapaviajes\Site\View\Map\HtmlView $this */

$e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$t = static fn (string $key): string => htmlspecialchars(Text::_($key), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="mv-root" id="mv-root">
<?php if ($this->album === null) : ?>
	<main class="mv-empty" style="padding:48px 24px;max-width:560px;margin:0 auto;font:16px/1.5 system-ui,sans-serif">
		<h1><?php echo $t('COM_MAPAVIAJES'); ?></h1>
		<p><?php echo $t('COM_MAPAVIAJES_NO_ALBUMS'); ?></p>
		<p><a href="<?php echo $e(Uri::root(true) ?: '/'); ?>"><?php echo $t('COM_MAPAVIAJES_BACK_TO_SITE'); ?></a></p>
	</main>
</div>
<?php return; endif; ?>

<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
	<defs>
		<radialGradient id="mv-gHead" cx=".34" cy=".28" r=".85"><stop offset="0" stop-color="#f0a48c"/><stop offset=".38" stop-color="#a8321f"/><stop offset="1" stop-color="#4a0f09"/></radialGradient>
		<radialGradient id="mv-gDraft" cx=".34" cy=".28" r=".85"><stop offset="0" stop-color="#ffffff"/><stop offset=".55" stop-color="#dff1ee"/><stop offset="1" stop-color="#4fa39b"/></radialGradient>
		<linearGradient id="mv-gNeedle" x1="0" x2="1"><stop offset="0" stop-color="#9a8a7a"/><stop offset=".45" stop-color="#fff7ea"/><stop offset="1" stop-color="#75665a"/></linearGradient>
		<linearGradient id="mv-gLand" gradientUnits="userSpaceOnUse" x1="0" y1="0" x2="0" y2="520"><stop offset="0" stop-color="#e4b86e"/><stop offset=".55" stop-color="#cf9550"/><stop offset="1" stop-color="#b66d3e"/></linearGradient>
		<radialGradient id="mv-gShadow"><stop offset="0" stop-color="#2d1b10" stop-opacity=".5"/><stop offset="1" stop-color="#2d1b10" stop-opacity="0"/></radialGradient>
	</defs>
</svg>

<main class="mv-stage" id="mv-stage" tabindex="0" aria-label="<?php echo $t('COM_MAPAVIAJES_MAP_LABEL'); ?>">
	<svg id="mv-svg" role="img" aria-label="<?php echo $t('COM_MAPAVIAJES_MAP_SVG'); ?>"><g id="mv-world">
		<path class="mv-sphere" id="mv-sphere"/>
		<path class="mv-grat" id="mv-grat"/>
		<g class="mv-rip mv-r1" id="mv-r1"></g><g class="mv-rip mv-r2" id="mv-r2"></g><g class="mv-rip mv-r3" id="mv-r3"></g>
		<g class="mv-land" id="mv-land"></g>
		<g class="mv-olabs" id="mv-olabs"></g>
	</g></svg>
	<div class="mv-grain"></div>
	<div id="mv-pins"></div>
</main>

<header class="mv-top mv-on-ocean">
	<div class="mv-brand">
		<svg class="mv-logo" viewBox="0 0 52 52" aria-hidden="true">
			<circle cx="26" cy="26" r="24" fill="#d49b55" stroke="#fbf7f0" stroke-width="2.5"/>
			<g fill="none" stroke="#8a5424" stroke-width="1.6"><ellipse cx="26" cy="26" rx="10" ry="24"/><path d="M3 20h46M3 32h46"/></g>
			<path d="M33 10.5a8.5 8.5 0 0 0-8.5 8.5c0 6.2 8.5 15.5 8.5 15.5s8.5-9.3 8.5-15.5A8.5 8.5 0 0 0 33 10.5z" fill="#8f2a1b" stroke="#fbf7f0" stroke-width="2"/>
			<circle cx="33" cy="19" r="3.2" fill="#fff"/>
		</svg>
		<div>
			<h1><?php echo $e((string) $this->album['title']); ?></h1>
			<p id="mv-stat">&nbsp;</p>
			<button class="mv-chip" id="mv-chip" type="button" hidden></button>
		</div>
	</div>
	<div class="mv-actions">
		<?php if (\count($this->albums) > 1) : ?>
			<label class="mv-sr" for="mv-albumpick"><?php echo $t('COM_MAPAVIAJES_PICK_ALBUM'); ?></label>
			<select class="mv-albumpick" id="mv-albumpick">
				<?php foreach ($this->albums as $a) : ?>
					<option value="<?php echo (int) $a['id']; ?>"<?php echo (int) $a['id'] === (int) $this->album['id'] ? ' selected' : ''; ?> data-url="<?php echo $e(Route::_('index.php?option=com_mapaviajes&album=' . (int) $a['id'], false)); ?>"><?php echo $e((string) $a['title']); ?></option>
				<?php endforeach; ?>
			</select>
		<?php endif; ?>
		<div class="mv-me" title="<?php echo $t('COM_MAPAVIAJES_SIGNED_IN'); ?>"><i><?php echo $e(mb_strtoupper(mb_substr($this->userName, 0, 1)) ?: '·'); ?></i><span><?php echo $e($this->userName); ?></span></div>
		<?php if ($this->rankOn) : ?>
		<button class="mv-btn mv-btn-soft" id="mv-btnPod" type="button" aria-controls="mv-pod" aria-label="<?php echo $t('COM_MAPAVIAJES_PODIUM'); ?>">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0zM7 6H4v1a3 3 0 0 0 3 3M17 6h3v1a3 3 0 0 1-3 3"/></svg><span><?php echo $t('COM_MAPAVIAJES_PODIUM'); ?></span>
		</button>
		<?php endif; ?>
		<button class="mv-btn mv-btn-soft" id="mv-btnDisc" type="button" aria-controls="mv-drawer" aria-label="<?php echo $t('COM_MAPAVIAJES_DISCOVER'); ?>">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h10"/></svg><span><?php echo $t('COM_MAPAVIAJES_DISCOVER'); ?></span>
		</button>
		<?php if ($this->canSubmit) : ?>
			<button class="mv-btn mv-btn-primary mv-btn-add" id="mv-btnAdd" type="button" aria-controls="mv-panel">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
				<span class="mv-long"><?php echo $t('COM_MAPAVIAJES_ADD'); ?></span><span class="mv-short"><?php echo $t('COM_MAPAVIAJES_ADD_SHORT'); ?></span>
			</button>
		<?php endif; ?>
		<a class="mv-btn mv-btn-soft mv-exit" href="<?php echo $e(Uri::root(true) ?: '/'); ?>" aria-label="<?php echo $t('COM_MAPAVIAJES_BACK_TO_SITE'); ?>">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg><span><?php echo $t('COM_MAPAVIAJES_BACK_TO_SITE'); ?></span>
		</a>
	</div>
</header>

<div class="mv-zoom" role="group" aria-label="<?php echo $t('COM_MAPAVIAJES_ZOOM'); ?>">
	<button type="button" id="mv-zin" aria-label="<?php echo $t('COM_MAPAVIAJES_ZOOM_IN'); ?>"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></button>
	<button type="button" id="mv-zout" aria-label="<?php echo $t('COM_MAPAVIAJES_ZOOM_OUT'); ?>"><svg viewBox="0 0 24 24"><path d="M5 12h14"/></svg></button>
	<button type="button" id="mv-zhome" aria-label="<?php echo $t('COM_MAPAVIAJES_ZOOM_HOME'); ?>"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17M12 3.5c3 3 3 14 0 17M12 3.5c-3 3-3 14 0 17"/></svg></button>
</div>

<svg class="mv-compass" viewBox="-60 -60 120 120" aria-hidden="true">
	<g fill="none" stroke="#6e4522" stroke-linecap="round"><circle r="52" stroke-width="1.4"/><circle r="47" stroke-width=".7"/><circle r="30" stroke-width=".7" stroke-dasharray="1.5 3"/></g>
	<g stroke="#6e4522" stroke-width=".9" stroke-linejoin="round">
		<path d="M0-46L6-6 46 0 6 6 0 46-6 6-46 0-6-6Z" fill="#ead9b0"/>
		<path d="M0-46L6-6 0 0Z M46 0L6 6 0 0Z M0 46L-6 6 0 0Z M-46 0L-6-6 0 0Z" fill="#6e4522"/>
	</g>
	<text y="-53.5" text-anchor="middle" font-family="Cinzel,serif" font-weight="700" font-size="12" fill="#4d2e14">N</text>
</svg>

<p class="mv-hint mv-on-ocean" id="mv-hint"><?php echo $t($this->canSubmit ? 'COM_MAPAVIAJES_HINT_ADD' : 'COM_MAPAVIAJES_HINT_VIEW'); ?></p>

<div id="mv-popHost"></div>

<aside class="mv-drawer" id="mv-drawer" aria-labelledby="mv-dTitle">
	<div class="mv-d-head"><h2 id="mv-dTitle" tabindex="-1"><?php echo $t('COM_MAPAVIAJES_LATEST'); ?></h2>
		<button class="mv-close" type="button" id="mv-dClose" aria-label="<?php echo $t('COM_MAPAVIAJES_CLOSE'); ?>"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>
	<div class="mv-tabs" role="tablist" aria-label="<?php echo $t('COM_MAPAVIAJES_LATEST'); ?>">
		<button type="button" role="tab" id="mv-tab-new" aria-selected="true" aria-controls="mv-dBody" data-tab="new"><?php echo $t('COM_MAPAVIAJES_TAB_NEW'); ?></button>
		<button type="button" role="tab" id="mv-tab-fav" aria-selected="false" aria-controls="mv-dBody" data-tab="fav"><?php echo $t('COM_MAPAVIAJES_TAB_FAV'); ?> <b id="mv-favN"></b></button>
	</div>
	<div class="mv-d-body" id="mv-dBody" role="tabpanel" tabindex="0"></div>
</aside>

<div class="mv-viewer" id="mv-viewer" role="dialog" aria-modal="true" aria-label="<?php echo $t('COM_MAPAVIAJES_VIEWER'); ?>" hidden>
	<div class="mv-v-stage" id="mv-vStage"><img id="mv-vImg" alt="" draggable="false"></div>
	<div class="mv-v-top">
		<button class="mv-v-btn" type="button" id="mv-vClose" aria-label="<?php echo $t('COM_MAPAVIAJES_CLOSE'); ?>"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
		<div class="mv-v-title"><strong id="mv-vT"></strong><span id="mv-vS"></span></div>
		<div class="mv-v-tools">
			<button class="mv-v-btn" type="button" id="mv-vLike" aria-pressed="false"><svg viewBox="0 0 24 24"><path d="M12 20s-7.5-4.6-9-9.5C2 7.3 4.3 5 7 5c1.9 0 3.4 1 5 3 1.6-2 3.1-3 5-3 2.7 0 5 2.3 4 5.5-1.5 4.9-9 9.5-9 9.5z"/></svg><i id="mv-vLikeN"></i></button>
			<button class="mv-v-btn mv-v-fan" type="button" id="mv-vFan" aria-pressed="false"><svg viewBox="0 0 24 24"><path d="M12 3l2.7 5.8 6.3.8-4.6 4.4 1.2 6.3L12 17.2 6.4 20.3l1.2-6.3L3 9.6l6.3-.8z"/></svg><i id="mv-vFanN"></i></button>
			<?php if ($this->commentsOn) : ?><button class="mv-v-btn" type="button" id="mv-vCom" hidden aria-controls="mv-cm" aria-label="<?php echo $t('COM_MAPAVIAJES_JS_COMMENTS_BTN'); ?>"><svg viewBox="0 0 24 24"><path d="M4 5h16v11H9l-5 4z"/></svg></button><?php endif; ?>
			<button class="mv-v-btn mv-v-fs" type="button" id="mv-vFs" aria-label="<?php echo $t('COM_MAPAVIAJES_FULLSCREEN'); ?>"><svg viewBox="0 0 24 24"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg></button>
		</div>
	</div>
	<p class="mv-v-msg" id="mv-vMsg" aria-live="polite"></p>
	<button class="mv-v-nav mv-prev" type="button" id="mv-vPrev" aria-label="<?php echo $t('COM_MAPAVIAJES_PREV'); ?>"><svg viewBox="0 0 24 24"><path d="M15 5l-7 7 7 7"/></svg></button>
	<button class="mv-v-nav mv-next" type="button" id="mv-vNext" aria-label="<?php echo $t('COM_MAPAVIAJES_NEXT'); ?>"><svg viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg></button>
	<p class="mv-v-cap" id="mv-vCap"></p>
	<p class="mv-sr" id="mv-vLive" aria-live="polite"></p>
	<?php if ($this->commentsOn) : ?>
	<section class="mv-cm" id="mv-cm" role="dialog" aria-modal="false" aria-labelledby="mv-cmT" hidden>
		<div class="mv-cm-head"><strong id="mv-cmT"><?php echo $t('COM_MAPAVIAJES_JS_COMMENTS_BTN'); ?></strong>
			<button class="mv-v-btn" type="button" id="mv-cmClose" aria-label="<?php echo $t('COM_MAPAVIAJES_CLOSE'); ?>"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>
		<iframe id="mv-cmFrame" title="<?php echo $t('COM_MAPAVIAJES_JS_COMMENTS_BTN'); ?>" referrerpolicy="same-origin" loading="lazy"></iframe>
	</section>
	<?php endif; ?>
</div>

<?php if ($this->rankOn) : ?>
<section class="mv-pod" id="mv-pod" role="dialog" aria-modal="true" aria-labelledby="mv-podT" hidden>
	<div class="mv-pod-bar">
		<button class="mv-pod-x" type="button" id="mv-podClose" aria-label="<?php echo $t('COM_MAPAVIAJES_CLOSE'); ?>"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
		<div class="mv-pod-tabs" role="tablist" aria-label="<?php echo $t('COM_MAPAVIAJES_PODIUM'); ?>">
			<button type="button" role="tab" data-v="week" aria-selected="true"><?php echo $t('COM_MAPAVIAJES_JS_POD_TAB_WEEK'); ?></button>
			<button type="button" role="tab" data-v="last" aria-selected="false"><?php echo $t('COM_MAPAVIAJES_JS_POD_TAB_LAST'); ?></button>
			<button type="button" role="tab" data-v="all" aria-selected="false"><?php echo $t('COM_MAPAVIAJES_JS_POD_TAB_ALL'); ?></button>
		</div>
	</div>
	<div class="mv-pod-page" id="mv-podPage" role="tabpanel" aria-live="polite" tabindex="-1"></div>
</section>
<?php endif; ?>

<?php if ($this->canSubmit) : ?>
<aside class="mv-panel" id="mv-panel" role="dialog" aria-labelledby="mv-panelTitle" aria-modal="false">
	<div class="mv-panel-head">
		<h2 id="mv-panelTitle" tabindex="-1"><?php echo $t('COM_MAPAVIAJES_FORM_TITLE'); ?></h2>
		<button class="mv-close" type="button" id="mv-btnClose" aria-label="<?php echo $t('COM_MAPAVIAJES_CLOSE'); ?>"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
	</div>
	<form id="mv-form" method="post" action="<?php echo $e(Route::_('index.php?option=com_mapaviajes&task=trip.submit&format=json', false)); ?>" enctype="multipart/form-data" novalidate autocomplete="off">
		<div class="mv-panel-body" id="mv-body">
			<noscript><p class="mv-err"><?php echo $t('COM_MAPAVIAJES_NEEDS_JS'); ?></p></noscript>
			<p class="mv-intro"><?php echo $t('COM_MAPAVIAJES_FORM_INTRO'); ?></p>

			<div class="mv-field">
				<label for="mv-q"><?php echo $t('COM_MAPAVIAJES_FIELD_PLACE'); ?></label>
				<div class="mv-combo">
					<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
					<input class="mv-input" id="mv-q" type="text" role="combobox" aria-expanded="false" aria-controls="mv-list" aria-autocomplete="list" aria-describedby="mv-qhelp mv-qerr" placeholder="<?php echo $t('COM_MAPAVIAJES_FIELD_PLACE_PH'); ?>" maxlength="120" enterkeyhint="search" autocapitalize="off" spellcheck="false">
					<ul class="mv-list" id="mv-list" role="listbox" hidden></ul>
				</div>
				<p class="mv-help" id="mv-qhelp"><?php echo $t('COM_MAPAVIAJES_FIELD_PLACE_HELP'); ?> <span class="mv-credit"><?php echo $t('COM_MAPAVIAJES_GEONAMES_CREDIT'); ?></span></p>
				<p class="mv-where" id="mv-where" hidden><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/></svg><span><code id="mv-coords"></code> · <?php echo $t('COM_MAPAVIAJES_FIELD_PLACE_MOVE'); ?></span></p>
				<p class="mv-err" id="mv-qerr" role="alert"></p>
			</div>

			<div class="mv-field">
				<label for="mv-title"><?php echo $t('COM_MAPAVIAJES_FIELD_TITLE'); ?></label>
				<input class="mv-input" id="mv-title" type="text" maxlength="80" placeholder="<?php echo $t('COM_MAPAVIAJES_FIELD_TITLE_PH'); ?>" aria-describedby="mv-terr">
				<p class="mv-err" id="mv-terr" role="alert"></p>
			</div>

			<fieldset class="mv-field" aria-describedby="mv-perr">
				<legend><?php echo $t('COM_MAPAVIAJES_FIELD_PHOTOS'); ?> <span class="mv-count" id="mv-pcount"></span></legend>
				<label class="mv-drop" id="mv-drop" for="mv-files">
					<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="3"/><circle cx="9" cy="11" r="2"/><path d="M21 16l-5-5-8 8"/></svg>
					<strong><?php echo $t('COM_MAPAVIAJES_DROP_TITLE'); ?></strong>
					<span><?php echo $t('COM_MAPAVIAJES_DROP_HELP'); ?></span>
				</label>
				<input class="mv-sr" id="mv-files" type="file" accept="image/jpeg,image/png,image/webp" multiple>
				<ul class="mv-photos" id="mv-photos"></ul>
				<p class="mv-help"><?php echo $t('COM_MAPAVIAJES_PHOTOS_NOTE'); ?></p>
				<p class="mv-err" id="mv-perr" role="alert"></p>
			</fieldset>

			<?php if ($this->album['allow_comments']) : ?>
			<fieldset class="mv-field">
				<legend><?php echo $t('COM_MAPAVIAJES_FIELD_COMMENTS'); ?></legend>
				<div class="mv-seg">
					<label><input type="radio" name="mv-comments" value="1" checked><span><?php echo $t('JYES'); ?></span></label>
					<label><input type="radio" name="mv-comments" value="0"><span><?php echo $t('JNO'); ?></span></label>
				</div>
				<label class="mv-check" id="mv-subLabel"><input type="checkbox" id="mv-sub" checked>
					<span><?php echo $t('COM_MAPAVIAJES_FIELD_NOTIFY'); ?><small id="mv-subHelp"><?php echo $t('COM_MAPAVIAJES_FIELD_NOTIFY_HELP'); ?></small></span>
				</label>
			</fieldset>
			<?php endif; ?>

			<div class="mv-sr" aria-hidden="true"><label for="mv-website">Website</label><input type="text" id="mv-website" tabindex="-1" autocomplete="off" value=""></div>
		</div>

		<div class="mv-panel-foot">
			<div class="mv-progress" id="mv-progress" hidden><i id="mv-progressBar"></i></div>
			<p class="mv-summary" id="mv-summary" role="alert"></p>
			<div class="mv-foot-row">
				<button class="mv-btn mv-btn-quiet" type="button" id="mv-btnCancel"><?php echo $t('JCANCEL'); ?></button>
				<button class="mv-btn mv-btn-primary" type="submit" id="mv-btnSend"><?php echo $t('COM_MAPAVIAJES_SEND'); ?></button>
			</div>
			<p class="mv-priv"><?php echo $t('COM_MAPAVIAJES_PRIVACY_NOTE'); ?></p>
		</div>
	</form>
</aside>
<?php endif; ?>

<div class="mv-toast" id="mv-toast" role="status" aria-live="polite"></div>
</div>
