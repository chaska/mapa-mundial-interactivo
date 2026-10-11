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
	/** Either a bare random name (logos, and photos from before 1.5.0) or <user id>/viaje_<year>_<n>_<8 random hex>.webp */
	public const NAME_PATTERN = '/^(?:[0-9]{1,10}\/)?[a-z0-9_]{1,90}\.webp$/D';

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

	/**
	 * Moves a verified temporary file to its final place. Without a user it gets a random name straight under photos/ (map logos).
	 * With a user it goes to photos/<user id>/viaje_<year>_<n>_<random>.webp so a person's photos stay together and easy to find years
	 * later; the random tail keeps the name unguessable should the server ever serve the folder by mistake. Returns the name relative to photos/.
	 */
	public function commit(string $temporary, int $userId = 0, int $year = 0, int $number = 1): string
	{
		$real = realpath($temporary);
		$tmpRoot = realpath($this->root . '/tmp');

		if ($real === false || $tmpRoot === false || !str_starts_with($real, $tmpRoot . DIRECTORY_SEPARATOR))
		{
			throw new \RuntimeException('storage');
		}

		$dir = $this->root . '/photos';

		if ($userId > 0)
		{
			$dir .= '/' . $userId;

			if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir))
			{
				throw new \RuntimeException('storage');
			}
		}

		for ($i = 0; $i < 5; $i++)
		{
			$name = $userId > 0
				? $userId . '/viaje_' . max(0, min(9999, $year)) . '_' . max(1, min(99, $number)) . '_' . bin2hex(random_bytes(4)) . '.webp'
				: bin2hex(random_bytes(16)) . '.webp';
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

			// A person's folder goes when their last photo does (rmdir refuses a folder that still has files).
			if (str_contains($name, '/'))
			{
				@rmdir(\dirname($path));
			}
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
