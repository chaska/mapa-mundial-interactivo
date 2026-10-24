<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Site\Controller;

defined('_JEXEC') || die;

trait JsonTrait
{
	/** The gates of every write: POST only, AJAX header, session token, logged-in user. */
	private function writeGate(): \Joomla\CMS\User\User
	{
		$user = $this->app->getIdentity();

		if (strtoupper((string) $this->app->getInput()->getMethod()) !== 'POST')
		{
			header('Allow: POST');
			$this->json(['ok' => false, 'error' => \Joomla\CMS\Language\Text::_('COM_MAPAVIAJES_ERR_GENERIC')], 405);
		}

		if (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) !== 'xmlhttprequest' || !\Joomla\CMS\Session\Session::checkToken('post'))
		{
			$this->json(['ok' => false, 'error' => \Joomla\CMS\Language\Text::_('COM_MAPAVIAJES_ERR_SESSION'), 'expired' => true], 403);
		}

		if ($user->guest)
		{
			$this->json(['ok' => false, 'error' => \Joomla\CMS\Language\Text::_('COM_MAPAVIAJES_ERR_LOGIN'), 'expired' => true], 403);
		}

		return $user;
	}

	/** Answers a refused request with the message of its code (a fixed, generic one when the code has none). */
	private function refuse(\Chaska\Component\Mapaviajes\Administrator\Service\SubmissionException $e): never
	{
		$key = 'COM_MAPAVIAJES_ERR_' . strtoupper($e->key());
		$msg = \Joomla\CMS\Language\Text::_($key);
		$ctx = $e->context();

		if ($msg !== $key && isset($ctx['max']))
		{
			$msg = sprintf($msg, (int) $ctx['max']);
		}

		if ($msg !== $key && isset($ctx['photo']))
		{
			$msg = \Joomla\CMS\Language\Text::sprintf('COM_MAPAVIAJES_ERR_PHOTO_N', (int) $ctx['photo']) . ' ' . $msg;
		}

		$this->json(['ok' => false, 'error' => $msg === $key ? \Joomla\CMS\Language\Text::_('COM_MAPAVIAJES_ERR_GENERIC') : $msg], $e->status());
	}

	/** @param array<string, mixed> $payload */
	private function json(array $payload, int $status = 200): never
	{
		while (ob_get_level() > 0)
		{
			@ob_end_clean();
		}

		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');
		header('X-Content-Type-Options: nosniff');
		header('Cache-Control: no-store, private');
		header('Referrer-Policy: no-referrer');

		echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

		$this->app->close();
		exit;
	}
}
