<?php
/**
 * OffsetWP Hook Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\Tests\Integration
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\HookBundle\Tests\Integration;

use OffsetWP\Bundle\HookBundle\HookBundle;
use OffsetWP\Bundle\HookBundle\HookRegistrar;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Handler\CountedHandler;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Handler\Dependency;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Handler\DependentHandler;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Handler\StaticHandler;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Hooks;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\KernelTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Nothing unused is ever built.
 *
 * This is the guarantee the rest of the package is arranged around, so it is a counter
 * and a set of assertions rather than a claim in a docblock.
 */
#[CoversClass( HookBundle::class )]
#[CoversClass( HookRegistrar::class )]
final class LazinessTest extends KernelTestCase {

	/**
	 * The counters are static and the suite runs in one process, in a random order.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		Dependency::reset();
		DependentHandler::reset();
	}

	/**
	 * Booting registers every hook and builds no handler. What a request that fires
	 * nothing costs is the list, and the list is all it costs.
	 *
	 * @return void
	 */
	public function testBootingRegistersEverythingAndBuildsNothing(): void {
		$kernel    = $this->bootWith( CountedHandler::class, StaticHandler::class, DependentHandler::class, Dependency::class );
		$container = $this->containerOf( $kernel );

		$this->assertTrue( Hooks::has( 'counted_action' ) );
		$this->assertTrue( Hooks::has( 'static_action' ) );
		$this->assertTrue( Hooks::has( 'dependent_action' ) );

		$this->assertFalse( $container->initialized( CountedHandler::class ) );
		$this->assertFalse( $container->initialized( StaticHandler::class ) );
		$this->assertFalse( $container->initialized( DependentHandler::class ) );

		$this->assertSame( 0, CountedHandler::$constructions );
		$this->assertSame( 0, StaticHandler::$constructions );
		$this->assertSame( 0, Dependency::$constructions );
	}

	/**
	 * A hook that fires builds its handler, once, however many times it fires
	 * afterwards — and a hook that never fires beside it still builds nothing.
	 *
	 * @return void
	 */
	public function testAHookThatFiresBuildsItsHandlerOnceAndOnlyItsOwn(): void {
		$this->bootWith( CountedHandler::class, StaticHandler::class );

		do_action( 'counted_action', 'first' );
		do_action( 'counted_action', 'second' );

		$this->assertSame( 1, CountedHandler::$constructions );
		$this->assertSame( array( 'action:first', 'action:second' ), CountedHandler::$calls );
		$this->assertSame( 0, StaticHandler::$constructions );
	}

	/**
	 * What a handler nobody calls costs stops at the handler: the services it would
	 * have needed are not built either.
	 *
	 * @return void
	 */
	public function testTheDependenciesOfAnUnusedHandlerAreNotBuiltEither(): void {
		$this->bootWith( DependentHandler::class, Dependency::class );

		$this->assertSame( 0, Dependency::$constructions );

		do_action( 'dependent_action' );

		$this->assertSame( array( 'dependency' ), DependentHandler::$calls );
		$this->assertSame( 1, Dependency::$constructions );
	}

	/**
	 * A static handler answers without anything being built, ever.
	 *
	 * @return void
	 */
	public function testAStaticHandlerAnswersWithoutBeingBuilt(): void {
		$kernel = $this->bootWith( StaticHandler::class );

		do_action( 'static_action', 'yes' );

		$this->assertSame( array( 'action:yes' ), StaticHandler::$calls );
		$this->assertSame( 'SHOUT', apply_filters( 'static_filter', 'shout' ) );
		$this->assertSame( 0, StaticHandler::$constructions );
		$this->assertFalse( $this->containerOf( $kernel )->initialized( StaticHandler::class ) );
	}

	/**
	 * The one service booting does build is the registrar, which holds a list of strings
	 * and a locator — and it is the only one, which is the half of that sentence worth
	 * asserting.
	 *
	 * @return void
	 */
	public function testTheOnlyThingBootingBuildsIsTheRegistrar(): void {
		$kernel    = $this->bootWith( CountedHandler::class, StaticHandler::class, DependentHandler::class, Dependency::class );
		$container = $this->containerOf( $kernel );

		$built = array_filter(
			array_keys( $container->getDefinitions() ),
			static fn ( string $id ): bool => $container->initialized( $id )
		);

		$this->assertSame( array( HookRegistrar::SERVICE_ID ), array_values( $built ) );
	}

	/**
	 * The one way a project loses all of this, and the reason the README has a paragraph
	 * about it: a handler that also carries the framework's autoload tag is built while
	 * the container compiles, like anything else carrying it. The hooks still work; what
	 * is gone is the part where nothing is built.
	 *
	 * @return void
	 */
	public function testAHandlerThatAlsoCarriesTheAutoloadTagIsBuiltAtCompileTime(): void {
		$this->boot(
			static function ( ContainerBuilder $container ): void {
				$container->setDefinition(
					CountedHandler::class,
					self::serviceFor( CountedHandler::class )->addTag( 'kernel.autoload' )
				);
			}
		);

		$this->assertSame( 1, CountedHandler::$constructions );
		$this->assertTrue( Hooks::has( 'counted_action' ) );
	}
}
