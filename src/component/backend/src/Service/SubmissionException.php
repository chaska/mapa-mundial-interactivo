<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\Service;

defined('_JEXEC') || die;

/** A refusal that is safe to show: the language key says what to fix, never what was sent. */
final class SubmissionException extends \RuntimeException
{
	/** @var array<string, string|int> */
	private array $context;

	/**
	 * @param   string                  $key      Language key suffix: COM_MAPAVIAJES_ERR_<KEY>
	 * @param   int                     $status   HTTP status for the JSON answer
	 * @param   array<string, string|int>  $context  Values for sprintf-style placeholders (a photo number, a limit)
	 */
	public function __construct(string $key, int $status = 400, array $context = [])
	{
		parent::__construct($key, $status);
		$this->context = $context;
	}

	public function key(): string
	{
		return $this->getMessage();
	}

	public function status(): int
	{
		return (int) $this->getCode();
	}

	/** @return array<string, string|int> */
	public function context(): array
	{
		return $this->context;
	}
}
