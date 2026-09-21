<?php
/**
 * A fixture project of the OffsetWP Hook Bundle test suite.
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\Tests\Fixtures\Usage
 */

declare( strict_types=1 );

use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Handler\CountedHandler;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function ( ContainerConfigurator $container ): void {
	$container->services()
		->set( CountedHandler::class )
		->autoconfigure()
		->public();
};
