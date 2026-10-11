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

class TripsModel extends ListModel
{
	public function __construct($config = [], $factory = null)
	{
		$config['filter_fields'] = ['id', 'a.id', 'title', 'a.title', 'created', 'a.created', 'published', 'a.published', 'album_id', 'a.album_id', 'author_name'];

		parent::__construct($config, $factory);
	}

	protected function populateState($ordering = 'a.created', $direction = 'DESC')
	{
		parent::populateState($ordering, $direction);
	}

	protected function getStoreId($id = '')
	{
		return parent::getStoreId($id . ':' . $this->getState('filter.search') . ':' . $this->getState('filter.published') . ':' . $this->getState('filter.album_id'));
	}

	protected function getListQuery()
	{
		$db  = $this->getDatabase();
		$sub = $db->getQuery(true)->select('MIN(' . $db->quoteName('p.id') . ')')->from($db->quoteName('#__mapaviajes_photos', 'p'))->where($db->quoteName('p.trip_id') . ' = ' . $db->quoteName('a.id'));

		$query = $db->getQuery(true)
			->select($db->quoteName(['a.id', 'a.title', 'a.place', 'a.published', 'a.created', 'a.photo_count', 'a.user_id', 'a.album_id']))
			->select($db->quoteName('u.name', 'author_name') . ', ' . $db->quoteName('u.username', 'author_username') . ', ' . $db->quoteName('al.title', 'album_title') . ', (' . $sub . ') AS ' . $db->quoteName('first_photo'))
			->from($db->quoteName('#__mapaviajes_trips', 'a'))
			->join('LEFT', $db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('a.user_id'))
			->join('LEFT', $db->quoteName('#__mapaviajes_albums', 'al') . ' ON ' . $db->quoteName('al.id') . ' = ' . $db->quoteName('a.album_id'));

		$state = (string) $this->getState('filter.published', '');

		if ($state === '')
		{
			$query->whereIn($db->quoteName('a.published'), [0, 1, 2]);
		}
		elseif ($state !== '*' && is_numeric($state))
		{
			$value = (int) $state;
			$query->where($db->quoteName('a.published') . ' = :state')->bind(':state', $value, ParameterType::INTEGER);
		}

		$album = (int) $this->getState('filter.album_id', 0);

		if ($album > 0)
		{
			$query->where($db->quoteName('a.album_id') . ' = :album')->bind(':album', $album, ParameterType::INTEGER);
		}

		$search = trim((string) $this->getState('filter.search', ''));

		if ($search !== '')
		{
			$like = '%' . addcslashes($search, '%_\\') . '%';
			$query->where('(' . $db->quoteName('a.title') . ' LIKE :s1 OR ' . $db->quoteName('a.place') . ' LIKE :s2 OR ' . $db->quoteName('u.name') . ' LIKE :s3 OR ' . $db->quoteName('u.username') . ' LIKE :s4)')
				->bind([':s1', ':s2', ':s3', ':s4'], $like);
		}

		$allowed = ['a.id', 'a.title', 'a.created', 'a.published', 'a.album_id', 'author_name'];
		$col     = (string) $this->getState('list.ordering', 'a.created');
		$dir     = strtoupper((string) $this->getState('list.direction', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
		$query->order($db->escape(\in_array($col, $allowed, true) ? $col : 'a.created') . ' ' . $dir);

		return $query;
	}
}
