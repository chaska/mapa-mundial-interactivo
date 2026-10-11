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
 * Takes ONE uploaded file and produces ONE brand-new WebP, or refuses it.
 *
 * Nothing of the original survives: the file is identified by its real bytes (never by the name, the
 * extension or the Content-Type the browser claims), decoded to pixels, rotated by its EXIF orientation,
 * scaled so the long side is at most N pixels without changing the aspect ratio, and re-encoded. Metadata
 * (EXIF, GPS, XMP, comments, trailing data, embedded scripts) is therefore gone by construction.
 */
final class ImageProcessor
{
	private const MAGIC_JPEG = "\xFF\xD8\xFF";
	private const MAGIC_PNG  = "\x89PNG\r\n\x1A\n";

	/** Strings that must never appear inside an image: a legitimate photo does not contain them. */
	private const TRIPWIRES = ['<?php', '<script', '<iframe', '<html', '<svg ', '<!doctype', 'eval(', 'base64_decode(', '#!/bin/', '#!/usr/'];

	/** @var callable(string): bool */
	private $isUploaded;

	private int $maxBytes;
	private int $longSide;
	private int $quality;
	private int $maxPixels;
	private int $maxDimension;
	private bool $upscale;

	/**
	 * @param   callable(string): bool|null  $isUploaded  Defaults to is_uploaded_file(); tests inject their own.
	 */
	public function __construct(
		int $maxBytes = 10 * 1048576,
		int $longSide = 1000,
		int $quality = 80,
		bool $upscale = false,
		int $maxPixels = 40000000,
		int $maxDimension = 12000,
		?callable $isUploaded = null
	)
	{
		$this->maxBytes     = max(1024, $maxBytes);
		$this->longSide     = min(4000, max(100, $longSide));
		$this->quality      = min(95, max(40, $quality));
		$this->upscale      = $upscale;
		$this->maxPixels    = $maxPixels;
		$this->maxDimension = $maxDimension;
		$this->isUploaded   = $isUploaded ?? 'is_uploaded_file';
	}

	public static function isSupported(): bool
	{
		if (!\function_exists('gd_info') || !\function_exists('imagewebp') || !\function_exists('imagecreatefromjpeg'))
		{
			return false;
		}

		$info = gd_info();

		return !empty($info['WebP Support']) && !empty($info['JPEG Support']) && !empty($info['PNG Support']);
	}

	/**
	 * @param   array<string, mixed>  $file         One entry of $_FILES, already flattened (name, tmp_name, error, size).
	 * @param   string                $destination  Where to write the new WebP (a private temporary path chosen by us).
	 *
	 * @return  array{width: int, height: int, bytes: int}
	 * @throws  SanitizeException
	 */
	public function process(array $file, string $destination): array
	{
		try
		{
			return $this->doProcess($file, $destination);
		}
		catch (\Throwable $e)
		{
			if (is_file($destination))
			{
				@unlink($destination);
			}

			if ($e instanceof SanitizeException)
			{
				throw $e;
			}

			// Anything unexpected (GD failure, memory) is reported generically; details never reach the user.
			throw new SanitizeException('image_unreadable');
		}
	}

	/**
	 * @param   array<string, mixed>  $file
	 *
	 * @return  array{width: int, height: int, bytes: int}
	 */
	private function doProcess(array $file, string $destination): array
	{
		if (!self::isSupported())
		{
			throw new SanitizeException('image_server');
		}

		$error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

		if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE)
		{
			throw new SanitizeException('image_too_big');
		}

		if ($error !== UPLOAD_ERR_OK)
		{
			throw new SanitizeException('image_upload');
		}

		$path = (string) ($file['tmp_name'] ?? '');

		// Only a file PHP itself received in THIS request. Blocks "tmp_name=/etc/passwd" style tricks.
		if ($path === '' || str_contains($path, "\0") || !($this->isUploaded)($path) || !is_file($path) || is_link($path))
		{
			throw new SanitizeException('image_upload');
		}

		$size = filesize($path);

		if ($size === false || $size < 100)
		{
			throw new SanitizeException('image_unreadable');
		}

		if ($size > $this->maxBytes)
		{
			throw new SanitizeException('image_too_big');
		}

		// What the browser said (name, extension, Content-Type) is ignored. The first bytes decide.
		$handle = fopen($path, 'rb');

		if ($handle === false)
		{
			throw new SanitizeException('image_unreadable');
		}

		$head = (string) fread($handle, 16);
		fclose($handle);

		if (str_starts_with($head, self::MAGIC_JPEG))
		{
			$kind = 'jpeg';
			$mime = 'image/jpeg';
			$type = IMAGETYPE_JPEG;
		}
		elseif (str_starts_with($head, self::MAGIC_PNG))
		{
			$kind = 'png';
			$mime = 'image/png';
			$type = IMAGETYPE_PNG;
		}
		elseif (substr($head, 0, 4) === 'RIFF' && substr($head, 8, 4) === 'WEBP')
		{
			$kind = 'webp';
			$mime = 'image/webp';
			$type = IMAGETYPE_WEBP;
		}
		else
		{
			throw new SanitizeException('image_type');
		}

		$finfo = new \finfo(FILEINFO_MIME_TYPE);

		if ($finfo->file($path) !== $mime)
		{
			throw new SanitizeException('image_type');
		}

		$bytes = file_get_contents($path, false, null, 0, $this->maxBytes + 1);

		if ($bytes === false || \strlen($bytes) !== $size)
		{
			throw new SanitizeException('image_unreadable');
		}

		$lower = strtolower($bytes);

		foreach (self::TRIPWIRES as $needle)
		{
			if (str_contains($lower, $needle))
			{
				throw new SanitizeException('image_polyglot');
			}
		}

		unset($lower);

		// Dimensions are read from the header BEFORE any decoding: this is what stops decompression bombs.
		$info = @getimagesize($path);

		if ($info === false || (int) $info[2] !== $type)
		{
			throw new SanitizeException('image_type');
		}

		$w = (int) $info[0];
		$h = (int) $info[1];

		if ($w < 16 || $h < 16 || $w > $this->maxDimension || $h > $this->maxDimension || $w * $h > $this->maxPixels)
		{
			throw new SanitizeException('image_dimensions');
		}

		$this->assertMemoryFor($w, $h);

		$orientation = $kind === 'jpeg' ? self::jpegOrientation($bytes) : 1;
		unset($bytes);

		$source = $this->decode($kind, $path);

		try
		{
			if ($orientation > 1)
			{
				$source = $this->orient($source, $orientation);
				$w      = imagesx($source);
				$h      = imagesy($source);
			}

			$scale = $this->longSide / max($w, $h);

			if ($scale > 1 && !$this->upscale)
			{
				$scale = 1.0;
			}

			$nw = max(1, (int) round($w * $scale));
			$nh = max(1, (int) round($h * $scale));

			$canvas = imagecreatetruecolor($nw, $nh);

			if ($canvas === false)
			{
				throw new SanitizeException('image_unreadable');
			}

			// Transparency is kept (PNG / WebP with alpha); photos from a camera have none.
			imagealphablending($canvas, false);
			imagesavealpha($canvas, true);
			imagefill($canvas, 0, 0, (int) imagecolorallocatealpha($canvas, 255, 255, 255, 127));

			if (!imagecopyresampled($canvas, $source, 0, 0, 0, 0, $nw, $nh, $w, $h))
			{
				throw new SanitizeException('image_unreadable');
			}

			if (!imagewebp($canvas, $destination, $this->quality))
			{
				throw new SanitizeException('image_unreadable');
			}
		}
		finally
		{
			unset($source, $canvas);
		}

		@chmod($destination, 0640);
		clearstatcache(true, $destination);

		// Verify what WE produced, with the same distrust.
		$outSize = filesize($destination);

		if ($outSize === false || $outSize < 100 || $outSize > $this->maxBytes)
		{
			throw new SanitizeException('image_unreadable');
		}

		$check = @getimagesize($destination);

		if ($check === false || (int) $check[2] !== IMAGETYPE_WEBP || (int) $check[0] !== $nw || (int) $check[1] !== $nh
			|| (new \finfo(FILEINFO_MIME_TYPE))->file($destination) !== 'image/webp')
		{
			throw new SanitizeException('image_unreadable');
		}

		return ['width' => $nw, 'height' => $nh, 'bytes' => (int) $outSize];
	}

	/**
	 * @return  \GdImage
	 */
	private function decode(string $kind, string $path)
	{
		$previous = set_error_handler(static function (int $no, string $str): bool {
			throw new \ErrorException($str, 0, $no);
		});

		// A truncated or damaged JPEG is refused instead of being "repaired" with grey pixels.
		$oldIgnore = ini_get('gd.jpeg_ignore_warning');
		@ini_set('gd.jpeg_ignore_warning', '0');

		try
		{
			$image = match ($kind)
			{
				'jpeg' => imagecreatefromjpeg($path),
				'png'  => imagecreatefrompng($path),
				default => imagecreatefromwebp($path),
			};
		}
		catch (\Throwable $e)
		{
			throw new SanitizeException('image_unreadable');
		}
		finally
		{
			if ($oldIgnore !== false)
			{
				@ini_set('gd.jpeg_ignore_warning', (string) $oldIgnore);
			}

			restore_error_handler();
		}

		if ($image === false)
		{
			throw new SanitizeException('image_unreadable');
		}

		if (!imageistruecolor($image))
		{
			imagepalettetotruecolor($image);
		}

		return $image;
	}

	/**
	 * @param   \GdImage  $image
	 *
	 * @return  \GdImage
	 */
	private function orient($image, int $orientation)
	{
		switch ($orientation)
		{
			case 2:
				imageflip($image, IMG_FLIP_HORIZONTAL);
				return $image;
			case 3:
				return imagerotate($image, 180, 0) ?: $image;
			case 4:
				imageflip($image, IMG_FLIP_VERTICAL);
				return $image;
			case 5:
				imageflip($image, IMG_FLIP_HORIZONTAL);
				return imagerotate($image, 90, 0) ?: $image;
			case 6:
				return imagerotate($image, -90, 0) ?: $image;
			case 7:
				imageflip($image, IMG_FLIP_HORIZONTAL);
				return imagerotate($image, -90, 0) ?: $image;
			case 8:
				return imagerotate($image, 90, 0) ?: $image;
		}

		return $image;
	}

	/**
	 * Reads the EXIF orientation (1-8) straight from the JPEG bytes with strict bounds. 1 when absent or odd.
	 * Not using exif_read_data() on purpose: a tiny parser that only looks for one tag has a tiny attack surface.
	 */
	public static function jpegOrientation(string $jpeg): int
	{
		$len = \strlen($jpeg);
		$pos = 2;

		for ($guard = 0; $guard < 64 && $pos + 4 <= $len; $guard++)
		{
			if ($jpeg[$pos] !== "\xFF")
			{
				return 1;
			}

			$marker = \ord($jpeg[$pos + 1]);

			if ($marker === 0xDA || $marker === 0xD9)
			{
				return 1; // start of scan / end of image: no more metadata
			}

			$segLen = (\ord($jpeg[$pos + 2]) << 8) | \ord($jpeg[$pos + 3]);

			if ($segLen < 2 || $pos + 2 + $segLen > $len)
			{
				return 1;
			}

			if ($marker === 0xE1 && substr($jpeg, $pos + 4, 6) === "Exif\0\0")
			{
				$tiff = substr($jpeg, $pos + 10, $segLen - 8);
				$tl   = \strlen($tiff);

				if ($tl < 14)
				{
					return 1;
				}

				$le = substr($tiff, 0, 2) === 'II';

				if (!$le && substr($tiff, 0, 2) !== 'MM')
				{
					return 1;
				}

				$u16 = static fn (int $o): int => $o + 2 <= $tl ? \unpack($le ? 'v' : 'n', $tiff, $o)[1] : 0;
				$u32 = static fn (int $o): int => $o + 4 <= $tl ? \unpack($le ? 'V' : 'N', $tiff, $o)[1] : 0;

				if ($u16(2) !== 42)
				{
					return 1;
				}

				$ifd = $u32(4);

				if ($ifd < 8 || $ifd + 2 > $tl)
				{
					return 1;
				}

				$count = min($u16($ifd), 64);

				for ($i = 0; $i < $count; $i++)
				{
					$entry = $ifd + 2 + $i * 12;

					if ($entry + 12 > $tl)
					{
						return 1;
					}

					if ($u16($entry) === 0x0112)
					{
						$value = $u16($entry + 8);

						return ($value >= 1 && $value <= 8) ? $value : 1;
					}
				}

				return 1;
			}

			$pos += 2 + $segLen;
		}

		return 1;
	}

	/** Refuses an image whose decoding would not fit in the memory PHP still has. */
	private function assertMemoryFor(int $w, int $h): void
	{
		$limit = ini_get('memory_limit');

		if ($limit === false || $limit === '' || $limit === '-1')
		{
			return;
		}

		$bytes = (int) $limit;
		$unit  = strtolower(substr(trim($limit), -1));
		$bytes *= match ($unit)
		{
			'g'     => 1073741824,
			'm'     => 1048576,
			'k'     => 1024,
			default => 1,
		};

		// source + canvas + a safety margin
		$needed = (int) ($w * $h * 4 * 2.2) + 8 * 1048576;

		if ($needed + memory_get_usage(true) > $bytes)
		{
			throw new SanitizeException('image_dimensions');
		}
	}
}
