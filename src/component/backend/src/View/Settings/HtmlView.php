<?php
/**
 * @package   MapaViajes
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mapaviajes\Administrator\View\Settings;

defined('_JEXEC') || die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/** The component options on a screen of our own (the permissions table stays in Joomla's own screen). */
class HtmlView extends BaseHtmlView
{
	protected Form $form;
	/** @var array<string, array{label: string, description: string}> */
	protected array $sections = [];

	public function display($tpl = null): void
	{
		$user = $this->getCurrentUser();

		if (!$user->authorise('core.options', 'com_mapaviajes') && !$user->authorise('core.admin', 'com_mapaviajes'))
		{
			throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$this->form = Form::getInstance('com_mapaviajes.options', JPATH_ADMINISTRATOR . '/components/com_mapaviajes/config.xml', ['control' => 'jform'], false, '/config');
		$this->form->bind(ComponentHelper::getParams('com_mapaviajes')->toArray());

		foreach ($this->form->getFieldsets() as $name => $set)
		{
			if ($name !== 'permissions')
			{
				$this->sections[$name] = ['label' => Text::_((string) $set->label), 'description' => Text::_((string) ($set->description ?? ''))];
			}
		}

		parent::display($tpl);
	}
}
