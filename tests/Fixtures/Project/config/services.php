<?php
/**
 * A fixture project of the OffsetWP Hook Bundle test suite.
 *
 * The defaults a project writes, and nothing else: each integration test registers the
 * services it needs itself, so that what it is looking at is only ever what it declared.
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\Tests\Fixtures
 */

declare( strict_types=1 );

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function ( ContainerConfigurator $container ): void {
	$container->services()
		->defaults()
			->autowire()
			->autoconfigure()
			->public();
};
