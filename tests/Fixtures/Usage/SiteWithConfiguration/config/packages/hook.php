<?php
/**
 * A fixture project of the OffsetWP Hook Bundle test suite.
 *
 * A project trying to configure a bundle that has nothing to configure.
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\Tests\Fixtures\Usage
 */

declare( strict_types=1 );

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function ( ContainerConfigurator $container ): void {
	$container->extension( 'hook', array( 'enabled' => true ) );
};
