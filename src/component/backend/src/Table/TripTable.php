<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\Table;

defined('_JEXEC') || die;

use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

class TripTable extends Table
{
	public function __construct(DatabaseDriver $db)
	{
		parent::__construct('#__mapaviajes_trips', 'id', $db);
	}
}
