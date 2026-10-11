<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\Security;

defined('_JEXEC') || die;

/**
 * Where photos live: a folder the web server must never serve directly. Photos are only handed out by the
 * component, after checking who is asking. File names are server-generated (128 random bits) and can never
 * come from a user.
 */
final class PrivateStorage
{
	public const NAME_PATTERN = '/^[a-f0-9]{32}\.webp$/D';

	private string $root;

	public function __construct(string $root)
	{
		$this->root = rtrim($root, '/\\');
	}

	public function root(): string
	{
		return $this->root;
	}

	/** Creates the folders and the files that tell Apache, LiteSpeed and IIS to refuse any direct request. */
	public function prepare(): void
	{
		foreach ([$this->root, $this->root . '/photos', $this->root . '/tmp'] as $dir)
		{
			if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir))
			{
				throw new \RuntimeException('storage');
			}
		}

		$guards = [
			'.htaccess'  => "# Photos are served only by the component.\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder deny,allow\n\tDeny from all\n</IfModule>\nOptions -Indexes -ExecCGI\n<IfModule mod_php.c>\n\tphp_flag engine off\n</IfModule>\nRemoveHandler .php .phtml .php5 .php7 .php8\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
			'index.html' => '<!DOCTYPE html><title></title>',
		];

		foreach ($guards as $name => $body)
		{
			$file = $this->root . '/' . $name;

			if (!is_file($file))
			{
				@file_put_contents($file, $body, LOCK_EX);
			}

			$sub = $this->root . '/photos/' . $name;

			if (!is_file($sub))
			{
				@file_put_contents($sub, $body, LOCK_EX);
			}
		}
	}

	/** A fresh, unused temporary path for the processor to write to. */
	public function newTemporaryPath(): string
	{
		return $this->root . '/tmp/' . bin2hex(random_bytes(16)) . '.part';
	}

	/** Moves a verified temporary file to its final, random name. Returns that name. */
	public function commit(string $temporary): string
	{
		$real = realpath($temporary);
		$tmpRoot = realpath($this->root . '/tmp');

		if ($real === false || $tmpRoot === false || !str_starts_with($real, $tmpRoot . DIRECTORY_SEPARATOR))
		{
			throw new \RuntimeException('storage');
		}

		for ($i = 0; $i < 5; $i++)
		{
			$name = bin2hex(random_bytes(16)) . '.webp';
			$dest = $this->root . '/photos/' . $name;

			if (!file_exists($dest) && @rename($real, $dest))
			{
				@chmod($dest, 0640);

				return $name;
			}
		}

		throw new \RuntimeException('storage');
	}

	/** Absolute path of a stored photo, or null when the name is not one of ours or the file is not there. */
	public function pathFor(string $name): ?string
	{
		if (preg_match(self::NAME_PATTERN, $name) !== 1)
		{
			return null;
		}

		$path = realpath($this->root . '/photos/' . $name);
		$base = realpath($this->root . '/photos');

		if ($path === false || $base === false || !str_starts_with($path, $base . DIRECTORY_SEPARATOR) || !is_file($path) || is_link($path))
		{
			return null;
		}

		return $path;
	}

	public function delete(string $name): void
	{
		$path = $this->pathFor($name);

		if ($path !== null)
		{
			@unlink($path);
		}
	}

	public function discard(string $temporary): void
	{
		if (is_file($temporary) && str_starts_with(realpath($temporary) ?: '', (realpath($this->root . '/tmp') ?: "\0") . DIRECTORY_SEPARATOR))
		{
			@unlink($temporary);
		}
	}

	/** Removes abandoned temporary files (a request that died half way). */
	public function sweep(int $olderThanSeconds = 3600): void
	{
		$dir = $this->root . '/tmp';

		foreach (glob($dir . '/*.part') ?: [] as $file)
		{
			if (is_file($file) && !is_link($file) && (time() - (int) filemtime($file)) > $olderThanSeconds)
			{
				@unlink($file);
			}
		}
	}
}
