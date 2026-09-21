<?php
/**
 * A fixture project of the OffsetWP Hook Bundle test suite.
 *
 * One service, with autoconfiguration turned off, reached by the tag alone. This is what
 * a project writes when the class attribute is not an option: a class from a package it
 * does not own, or a service it has reasons to keep out of autoconfiguration.
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
		->autoconfigure( false )
		->public()
		->tag( 'hook.handler' );
};
