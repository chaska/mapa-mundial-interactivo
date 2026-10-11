<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\Model;

defined('_JEXEC') || die;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;

class AlbumsModel extends ListModel
{
	public function __construct($config = [], $factory = null)
	{
		$config['filter_fields'] = ['id', 'a.id', 'title', 'a.title', 'published', 'a.published', 'ordering', 'a.ordering'];

		parent::__construct($config, $factory);
	}

	protected function populateState($ordering = 'a.ordering', $direction = 'ASC')
	{
		parent::populateState($ordering, $direction);
	}

	protected function getStoreId($id = '')
	{
		return parent::getStoreId($id . ':' . $this->getState('filter.search') . ':' . $this->getState('filter.published'));
	}

	protected function getListQuery()
	{
		$db  = $this->getDatabase();
		$sub = $db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__mapaviajes_trips', 't'))->where($db->quoteName('t.album_id') . ' = ' . $db->quoteName('a.id'))->where($db->quoteName('t.published') . ' = 1');

		$query = $db->getQuery(true)
			->select($db->quoteName(['a.id', 'a.title', 'a.published', 'a.access', 'a.allow_comments', 'a.ordering', 'a.created']))
			->select($db->quoteName('vl.title', 'access_level') . ', (' . $sub . ') AS ' . $db->quoteName('trips'))
			->from($db->quoteName('#__mapaviajes_albums', 'a'))
			->join('LEFT', $db->quoteName('#__viewlevels', 'vl') . ' ON ' . $db->quoteName('vl.id') . ' = ' . $db->quoteName('a.access'));

		$state = (string) $this->getState('filter.published', '');

		if ($state === '')
		{
			$query->whereIn($db->quoteName('a.published'), [0, 1]);
		}
		elseif ($state !== '*' && is_numeric($state))
		{
			$value = (int) $state;
			$query->where($db->quoteName('a.published') . ' = :state')->bind(':state', $value, ParameterType::INTEGER);
		}

		$search = trim((string) $this->getState('filter.search', ''));

		if ($search !== '')
		{
			$like = '%' . addcslashes($search, '%_\\') . '%';
			$query->where($db->quoteName('a.title') . ' LIKE :s')->bind(':s', $like);
		}

		$allowed = ['a.id', 'a.title', 'a.published', 'a.ordering'];
		$col     = (string) $this->getState('list.ordering', 'a.ordering');
		$dir     = strtoupper((string) $this->getState('list.direction', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
		$query->order($db->escape(\in_array($col, $allowed, true) ? $col : 'a.ordering') . ' ' . $dir);

		return $query;
	}
}
