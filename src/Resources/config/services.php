<?php
/**
 * OffsetWP Hook Bundle
 *
 * The one service this bundle owns.
 *
 * It carries no "kernel.autoload" tag, and it never should: a service carrying it is
 * built by a compiler pass while the container compiles, which would hand every hook to
 * the platform before the kernel has finished booting — and would do it on every request
 * whether or not anything else in the project needed it.
 *
 * Public on purpose. The container is compiled but never dumped, so a private service is
 * reachable today and would stop being reachable the day that changes. The bundle asks
 * for this one by id when it boots.
 *
 * Both of its arguments are set by the compiler pass, which runs whenever this bundle is
 * registered — which is the only way this file is ever read.
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle
 */

declare( strict_types=1 );

use OffsetWP\Bundle\HookBundle\DependencyInjection\Compiler\HandlerPass;
use OffsetWP\Bundle\HookBundle\HookRegistrar;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function ( ContainerConfigurator $container ): void {
	$container->services()
		->set( HookRegistrar::SERVICE_ID, HookRegistrar::class )
		->tag( HandlerPass::OWNED_TAG )
		->public();
};
