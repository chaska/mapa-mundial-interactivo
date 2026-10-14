<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\Field;

defined('_JEXEC') || die;

use Chaska\Component\Mapaviajes\Administrator\Service\MapStyles;
use Joomla\CMS\Form\Field\ListField;

/** The list of map looks found in media/com_mapaviajes/css/maps (plus the built-in one). */
class MapstyleField extends ListField
{
	protected $type = 'Mapstyle';

	protected function getOptions()
	{
		$options = parent::getOptions();

		foreach (MapStyles::all() as $id => $style)
		{
			$options[] = (object) ['value' => $id, 'text' => $style['label']];
		}

		return $options;
	}
}
