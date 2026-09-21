<?php
/**
 * A fixture project of the OffsetWP Hook Bundle test suite.
 *
 * A class whose handlers are all private, registered as a service, with neither the mark
 * on the class nor the tag on the service. Nothing can see it, so nothing happens — which
 * is the one silence this package has and the reason the README names it.
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\Tests\Fixtures\Usage
 */

declare( strict_types=1 );

use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Handler\Untagged;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function ( ContainerConfigurator $container ): void {
	$container->services()
		->set( Untagged::class )
		->autoconfigure()
		->public();
};
