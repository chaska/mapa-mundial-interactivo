<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\Table;

defined('_JEXEC') || die;

use Chaska\Component\Mapaviajes\Administrator\Security\SanitizeException;
use Chaska\Component\Mapaviajes\Administrator\Security\TextSanitizer;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

class AlbumTable extends Table
{
	public function __construct(DatabaseDriver $db)
	{
		parent::__construct('#__mapaviajes_albums', 'id', $db);
	}

	public function check(): bool
	{
		try
		{
			$this->title       = TextSanitizer::clean((string) $this->title, 150, 0, false);
			$this->description = TextSanitizer::clean((string) ($this->description ?? ''), 2000, 0, true);
		}
		catch (SanitizeException $e)
		{
			$this->setError(Text::_('COM_MAPAVIAJES_ERR_' . strtoupper($e->getReason())));

			return false;
		}

		if ($this->title === '')
		{
			$this->setError(Text::_('COM_MAPAVIAJES_ERR_TITLE_REQUIRED'));

			return false;
		}

		if (empty($this->created))
		{
			$this->created    = Factory::getDate()->toSql();
			$this->created_by = (int) Factory::getApplication()->getIdentity()->id;
		}

		$this->allow_comments = (int) !empty($this->allow_comments);

		return true;
	}
}
