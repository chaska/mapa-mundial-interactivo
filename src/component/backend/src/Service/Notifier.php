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
use Joomla\CMS\Router\Route;
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
		$link = $newState === 1 ? Route::link('site', 'index.php?option=com_mapaviajes&album=' . (int) $trip['album_id'] . '&trip=' . (int) $trip['id'], false, Route::TLS_IGNORE, true) : '';
		$title = $this->oneLine((string) $trip['title']);

		if ($newState === 1)
		{
			$subject = sprintf($lang->_('COM_MAPAVIAJES_MAIL_PUBLISHED_SUBJECT'), $title);
			$body    = sprintf($lang->_('COM_MAPAVIAJES_MAIL_PUBLISHED_BODY'), $this->oneLine((string) $user->name), $title) . "\n\n" . $link;
		}
		else
		{
			$subject = sprintf($lang->_('COM_MAPAVIAJES_MAIL_REJECTED_SUBJECT'), $title);
			$body    = sprintf($lang->_('COM_MAPAVIAJES_MAIL_REJECTED_BODY'), $this->oneLine((string) $user->name), $title);
		}

		$this->send((string) $user->email, $subject, $body . "\n");
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
			$link  = Route::link('site', 'index.php?option=com_mapaviajes', false, Route::TLS_IGNORE, true);
			$this->send(
				(string) $user->email,
				sprintf($lang->_('COM_MAPAVIAJES_MAIL_PODIUM_SUBJECT'), $place),
				sprintf($lang->_('COM_MAPAVIAJES_MAIL_PODIUM_BODY'), $this->oneLine((string) $user->name), $place, (int) $r['score'], $weekStart) . "\n\n" . $link . "\n"
			);
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

	private function send(string $to, string $subject, string $body): void
	{
		try
		{
			$mailer = Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer();
			$mailer->addRecipient($to);
			$mailer->setSubject($subject);
			$mailer->setBody($body);
			$mailer->isHtml(false);
			$mailer->Send();
		}
		catch (\Throwable $e)
		{
			// never stops a moderator
		}
	}
}
