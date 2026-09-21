<?php
/**
 * A fixture project of the OffsetWP Hook Bundle test suite.
 *
 * The services of the project, written the way a project writes them: one load() over
 * the namespace, autowiring and autoconfiguration on, and nothing tagged by hand.
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\Tests\Fixtures\Usage
 */

declare( strict_types=1 );

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function ( ContainerConfigurator $container ): void {
	$services = $container->services();

	$services
		->defaults()
			->autowire()
			->autoconfigure()
			->public();

	$services->load( 'App\\', '../app/' );
};
