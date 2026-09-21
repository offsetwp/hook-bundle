<?php
/**
 * OffsetWP Hook Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\Tests\Fixtures
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\HookBundle\Tests\Fixtures;

use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Handler\CountedHandler;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Handler\StaticHandler;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * What every integration test needs: a fixture project, a kernel, and the services the
 * test itself declares on it.
 */
abstract class KernelTestCase extends HookTestCase {

	/**
	 * The counters are static and the suite runs in one process, in a random order.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		CountedHandler::reset();
		StaticHandler::reset();
	}

	/**
	 * A fixture project directory.
	 *
	 * @param string $name The fixture project name.
	 * @return string
	 */
	protected function project( string $name = 'Project' ): string {
		return __DIR__ . DIRECTORY_SEPARATOR . $name;
	}

	/**
	 * A kernel on a fixture project, not booted yet.
	 *
	 * @param \Closure|null $services Extra service definitions for this test.
	 * @param string        $name     The fixture project name.
	 * @return TestKernel
	 */
	protected function kernel( ?\Closure $services = null, string $name = 'Project' ): TestKernel {
		return new TestKernel( $this->project( $name ), $services );
	}

	/**
	 * Boot a kernel on a fixture project.
	 *
	 * @param \Closure|null $services Extra service definitions for this test.
	 * @param string        $name     The fixture project name.
	 * @return TestKernel
	 */
	protected function boot( ?\Closure $services = null, string $name = 'Project' ): TestKernel {
		$kernel = $this->kernel( $services, $name );
		$kernel->boot();

		return $kernel;
	}

	/**
	 * Boot a kernel carrying one autoconfigured service per class, which is what a
	 * project's own load() produces.
	 *
	 * @param class-string ...$classes The classes to register, each under its own name.
	 * @return TestKernel
	 */
	protected function bootWith( string ...$classes ): TestKernel {
		return $this->boot(
			static function ( ContainerBuilder $container ) use ( $classes ): void {
				foreach ( $classes as $class ) {
					$container->setDefinition( $class, self::serviceFor( $class ) );
				}
			}
		);
	}

	/**
	 * One service definition, written the way a project's defaults write one.
	 *
	 * @param string $handler_class The class to register, or a container parameter expression naming one.
	 * @return Definition
	 */
	protected static function serviceFor( string $handler_class ): Definition {
		return ( new Definition( $handler_class ) )
			->setAutowired( true )
			->setAutoconfigured( true )
			->setPublic( true );
	}

	/**
	 * The compiled container of a booted kernel.
	 *
	 * @param TestKernel $kernel The booted kernel.
	 * @return ContainerBuilder
	 */
	protected function containerOf( TestKernel $kernel ): ContainerBuilder {
		$container = $kernel->container();

		$this->assertInstanceOf( ContainerBuilder::class, $container );

		return $container;
	}
}
