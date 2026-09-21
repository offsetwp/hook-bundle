<?php
/**
 * OffsetWP Hook Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\Tests\Unit
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\HookBundle\Tests\Unit;

use OffsetWP\Bundle\HookBundle\HookBundle;
use OffsetWP\Bundle\HookBundle\HookRegistrar;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\HookTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The two ways booting the bundle can fail, and what each of them says.
 *
 * Both are reachable from a project: the first by booting the bundle rather than the
 * kernel that carries it, the second by taking the id its one service answers to.
 */
#[CoversClass( HookBundle::class )]
final class HookBundleTest extends HookTestCase {

	/**
	 * A bundle booted on its own has no container, and is told where one comes from.
	 *
	 * @return void
	 */
	public function testABundleBootedWithoutAContainerSaysWhereOneComesFrom(): void {
		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage( 'Cannot boot the hook bundle without a container. A bundle receives one from the kernel that registered it, so boot the kernel rather than the bundle.' );

		( new HookBundle() )->boot();
	}

	/**
	 * A service of somebody else's making under the registrar's id is named for what it
	 * is, rather than failing on a method it does not have.
	 *
	 * @return void
	 */
	public function testAForeignServiceUnderTheRegistrarsIdIsNamed(): void {
		$container = new ContainerBuilder();
		$container->register( HookRegistrar::SERVICE_ID, \stdClass::class )->setPublic( true );
		$container->compile();

		$bundle = new HookBundle();
		$bundle->setContainer( $container );

		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage( 'The service "hook.registrar" is a "stdClass", not a "' . HookRegistrar::class . '".' );

		$bundle->boot();
	}
}
