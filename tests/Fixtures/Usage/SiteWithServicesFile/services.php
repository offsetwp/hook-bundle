<?php
/**
 * A fixture project of the OffsetWP Hook Bundle test suite.
 *
 * A project built in the kernel's standalone mode: one services file, and no config
 * directory. The kernel registers no bundle at all in that mode, which is one of the two
 * ways a hook declared with an attribute never fires.
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
