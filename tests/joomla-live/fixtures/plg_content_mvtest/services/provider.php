<?php
defined('_JEXEC') || die;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Mvtest\Plugin\Content\Mvtest\Extension\Mvtest;
return new class implements ServiceProviderInterface {
	public function register(Container $container): void
	{
		$container->set(PluginInterface::class, function (Container $c) {
			$p = new Mvtest($c->get(DispatcherInterface::class), (array) PluginHelper::getPlugin('content', 'mvtest'));
			$p->setApplication(Factory::getApplication());
			return $p;
		});
	}
};
