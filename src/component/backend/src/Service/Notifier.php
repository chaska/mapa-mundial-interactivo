<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\Service;

defined('_JEXEC') || die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;

/**
 * The few e-mails to people (not to the administration): the author of a trip is told when a moderator publishes it or
 * does not publish it, and, if the site switched it on, the three people of a podium. Plain text, in the language of the
 * recipient, with ONE link to our own map. Never the text of a trip, never anybody else's data, never an address in the
 * body. A mail that cannot be sent is skipped silently: moderation must never fail because of the mail server.
 */
final class Notifier
{
	public function __construct(private DatabaseInterface $db, private Settings $settings)
	{
	}

	/** The author hears about a moderator's decision: published (1) or not published (2 hidden, -3 spam, -2 trash). */
	public function tripDecision(int $tripId, int $newState, int $byUserId): void
	{
		if (!$this->settings->notifyAuthor() || !\in_array($newState, [1, 2, -3, -2], true))
		{
			return;
		}

		$trip = (new TripRepository($this->db))->forComments($tripId);

		if ($trip === null || (int) $trip['user_id'] === $byUserId)
		{
			return; // nobody needs an e-mail about what they did themselves
		}

		$user = $this->recipient((int) $trip['user_id']);

		if ($user === null)
		{
			return;
		}

		$lang = $this->language($user);
		$title = $this->oneLine((string) $trip['title']);

		if ($newState === 1)
		{
			$subject = sprintf($lang->_('COM_MAPAVIAJES_MAIL_PUBLISHED_SUBJECT'), $title);
			$body    = sprintf($lang->_('COM_MAPAVIAJES_MAIL_PUBLISHED_BODY'), $this->oneLine((string) $user->name), $title);
		}
		else
		{
			$subject = sprintf($lang->_('COM_MAPAVIAJES_MAIL_REJECTED_SUBJECT'), $title);
			$body    = sprintf($lang->_('COM_MAPAVIAJES_MAIL_REJECTED_BODY'), $this->oneLine((string) $user->name), $title);
		}

		$this->send((string) $user->email, $subject, $body, $lang);
	}

	/**
	 * The podium of a week that just closed. $rows are the stored rows of that week (best first).
	 *
	 * @param  list<array<string, mixed>>  $rows
	 */
	public function podium(string $weekStart, array $rows): void
	{
		if (!$this->settings->notifyPodium())
		{
			return;
		}

		foreach (\array_slice($rows, 0, 3) as $r)
		{
			$user = $this->recipient((int) $r['user_id']);

			if ($user === null)
			{
				continue;
			}

			$lang  = $this->language($user);
			$place = (int) $r['position'];
			$this->send(
				(string) $user->email,
				sprintf($lang->_('COM_MAPAVIAJES_MAIL_PODIUM_SUBJECT'), $place),
				sprintf($lang->_('COM_MAPAVIAJES_MAIL_PODIUM_BODY'), $this->oneLine((string) $user->name), $place, (int) $r['score'], $weekStart),
				$lang
			);
		}
	}

	/**
	 * The weekly summary of new trips by the people someone follows. Only people who switched it on, one at most per week, plain
	 * names and counts (no trip text, no link: they just open the map). Any request may call it: a site-wide marker lets one
	 * request every few hours do the work.
	 */
	public function followDigest(): void
	{
		if (!$this->settings->notifyFollows())
		{
			return;
		}

		$follows = new FollowService($this->db);

		if (!$follows->claimRun(6))
		{
			return;
		}

		foreach ($follows->digestJobs($this->settings->showRealNames()) as $job)
		{
			$user = $this->recipient((int) $job['user_id']);

			if ($user === null)
			{
				continue;
			}

			$lang  = $this->language($user);
			$total = array_sum(array_column($job['authors'], 'n'));
			$list  = implode(', ', array_map(fn (array $a): string => $this->oneLine($a['name']) . ' (' . $a['n'] . ')', \array_slice($job['authors'], 0, 10)));
			$this->send(
				(string) $user->email,
				sprintf($lang->_($total === 1 ? 'COM_MAPAVIAJES_MAIL_DIGEST_SUBJECT_1' : 'COM_MAPAVIAJES_MAIL_DIGEST_SUBJECT'), $total),
				sprintf($lang->_('COM_MAPAVIAJES_MAIL_DIGEST_BODY'), $this->oneLine((string) $user->name), $total, $list) . "\n\n" . $lang->_('COM_MAPAVIAJES_MAIL_DIGEST_HOWTO'),
				$lang
			);
			$follows->markDigestSent((int) $job['user_id']);
		}
	}

	private function recipient(int $userId): ?User
	{
		if ($userId <= 0)
		{
			return null;
		}

		$user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userId);

		if (!$user->id || (int) $user->block === 1 || !filter_var((string) $user->email, FILTER_VALIDATE_EMAIL))
		{
			return null;
		}

		return $user;
	}

	/** The component's strings in the language the person uses on the site (their profile, then the site default). */
	private function language(User $user): Language
	{
		$tag  = (string) $user->getParam('language', '') ?: (string) Factory::getApplication()->get('language', 'en-GB');
		$lang = Language::getInstance(preg_match('/^[a-z]{2,3}-[A-Z]{2}$/', $tag) === 1 ? $tag : 'en-GB');
		$lang->load('com_mapaviajes', JPATH_ADMINISTRATOR, null, true);

		return $lang;
	}

	/** A value that goes into a subject or a greeting: one line, no control characters, bounded. */
	private function oneLine(string $text): string
	{
		return mb_substr(trim((string) preg_replace('/[\p{C}\s]+/u', ' ', $text)), 0, 100, 'UTF-8');
	}

	/**
	 * Every mail of the component: HTML with the map's logo embedded in the message (no picture is fetched from the internet and
	 * there is NO link in it), a plain-text twin, and a confidentiality notice at the bottom. A mail that cannot be sent is skipped.
	 */
	/** The mail to the people who run the site (a new trip arrived): same look, in the site's language, no link. @param list<string> $to */
	public function administrators(array $to, string $subject, string $body): void
	{
		$tag  = (string) Factory::getApplication()->get('language', 'en-GB');
		$lang = Language::getInstance(preg_match('/^[a-z]{2,3}-[A-Z]{2}$/', $tag) === 1 ? $tag : 'en-GB');
		$lang->load('com_mapaviajes', JPATH_ADMINISTRATOR, null, true);
		$this->send($to, $subject, $body, $lang);
	}

	/** A trip was reported: the moderators hear about it, in the site's language, without a link. @param list<string> $to */
	public function reported(array $to, int $tripId, string $title, string $reason): void
	{
		$tag  = (string) Factory::getApplication()->get('language', 'en-GB');
		$lang = Language::getInstance(preg_match('/^[a-z]{2,3}-[A-Z]{2}$/', $tag) === 1 ? $tag : 'en-GB');
		$lang->load('com_mapaviajes', JPATH_ADMINISTRATOR, null, true);
		$lang->load('com_mapaviajes', JPATH_SITE, null, true);
		$site = $this->oneLine((string) Factory::getApplication()->get('sitename', 'Site'));
		$this->send(
			$to,
			'[' . $site . '] ' . $lang->_('COM_MAPAVIAJES_MAIL_REPORT_SUBJECT'),
			sprintf($lang->_('COM_MAPAVIAJES_MAIL_REPORT_BODY'), $tripId, $this->oneLine($title), $lang->_('COM_MAPAVIAJES_REPORT_REASON_' . strtoupper($reason))),
			$lang
		);
	}

	/** @param string|list<string> $to */
	private function send(string|array $to, string $subject, string $body, Language $lang): void
	{
		try
		{
			$site   = $this->oneLine((string) Factory::getApplication()->get('sitename', ''));
			$footer = $this->footerLines($lang, $site);
			$mailer = Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer();
			foreach ((array) $to as $address)
			{
				$mailer->addRecipient((string) $address);
			}

			$mailer->setSubject($subject);
			$logo = $this->logoFile();
			$cid  = null;

			if ($logo !== null)
			{
				$cid = 'mvlogo';
				$mailer->addEmbeddedImage($logo, $cid, 'logo.png', 'base64', 'image/png');
			}

			$mailer->isHtml(true);
			$mailer->setBody($this->html($subject, $body, $footer, $site, $cid, $lang));
			$mailer->AltBody = $body . "\n\n--\n" . implode("\n", $footer) . "\n";
			$mailer->Send();
		}
		catch (\Throwable $e)
		{
			// never stops a moderator
		}
	}

	/** @return list<string> why the person gets the mail, and the confidentiality notice */
	private function footerLines(Language $lang, string $site): array
	{
		$lines  = [sprintf($lang->_('COM_MAPAVIAJES_MAIL_WHY'), $site !== '' ? $site : '—')];
		$sender = $this->settings->senderLine();

		if ($sender !== '')
		{
			$lines[] = sprintf($lang->_('COM_MAPAVIAJES_MAIL_SENDER'), $sender);
		}

		$lines[] = $lang->_('COM_MAPAVIAJES_MAIL_CONFIDENTIAL');

		return $lines;
	}

	/** The logo of the first published album that has one (a stored PNG), as a path; null when there is none. */
	private function logoFile(): ?string
	{
		try
		{
			$name = (string) $this->db->setQuery($this->db->getQuery(true)->select($this->db->quoteName('logo'))->from($this->db->quoteName('#__mapaviajes_albums'))->where($this->db->quoteName('published') . ' = 1')->where($this->db->quoteName('logo') . " <> ''")->order($this->db->quoteName('ordering'))->setLimit(1))->loadResult();
			$path = $name !== '' ? $this->settings->storage()->pathFor($name) : null;

			return $path !== null && str_ends_with($path, '.png') && is_file($path) ? $path : null;
		}
		catch (\Throwable $e)
		{
			return null;
		}
	}

	/** One responsive table, inline styles only (mail programs ignore the rest). Every value is escaped; no links, no remote images. */
	private function html(string $heading, string $text, array $footer, string $site, ?string $cid, Language $lang): string
	{
		$e     = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		$paras = '';

		foreach (preg_split('/\n{2,}/', trim($text)) ?: [] as $p)
		{
			$paras .= '<p style="margin:0 0 16px;font-size:16px;line-height:1.55;color:#2d1b10">' . nl2br($e(trim($p)), false) . '</p>';
		}

		$img = $cid !== null ? '<img src="cid:' . $e($cid) . '" width="120" alt="' . $e($site) . '" style="display:block;margin:0 auto 8px;border:0;max-width:120px;height:auto">' : '';
		$small = '';

		foreach ($footer as $line)
		{
			$small .= '<p style="margin:0 0 8px;font-size:12px;line-height:1.5;color:#7a6a5c">' . $e($line) . '</p>';
		}

		return '<!doctype html><html lang="' . $e(substr((string) $lang->getTag(), 0, 2)) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
			. '<body style="margin:0;padding:0;background:#f3efe7;font-family:Arial,Helvetica,sans-serif">'
			. '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3efe7"><tr><td align="center" style="padding:24px 12px">'
			. '<table role="presentation" width="560" cellspacing="0" cellpadding="0" style="width:100%;max-width:560px;background:#ffffff;border:1px solid #e8dfd2;border-radius:16px">'
			. '<tr><td align="center" style="padding:28px 28px 8px">' . $img . ($site !== '' ? '<div style="font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:#a8321f;font-weight:bold">' . $e($site) . '</div>' : '') . '</td></tr>'
			. '<tr><td style="padding:12px 32px 4px"><h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;color:#241a14">' . $e($heading) . '</h1>' . $paras . '</td></tr>'
			. '<tr><td style="padding:16px 32px 26px;border-top:1px solid #e8dfd2">' . $small . '</td></tr>'
			. '</table></td></tr></table></body></html>';
	}
}
