<?php
/**
 * OffsetWP Hook Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\Tests\Integration
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\HookBundle\Tests\Integration;

use OffsetWP\Bundle\HookBundle\DependencyInjection\Compiler\HandlerPass;
use OffsetWP\Bundle\HookBundle\HookBundle;
use OffsetWP\Bundle\HookBundle\HookRegistrar;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Handler\CountedHandler;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Handler\Declarations;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Handler\Signatures;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Handler\StaticHandler;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Handler\Untagged;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Hooks;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\KernelTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * What a booted kernel finds, and what it hands the platform.
 *
 * Everything here goes through a real container: a fixture project, a kernel booted on
 * it, and services declared the way a project's own defaults declare them.
 */
#[CoversClass( HookBundle::class )]
#[CoversClass( HandlerPass::class )]
#[CoversClass( HookRegistrar::class )]
final class DiscoveryTest extends KernelTestCase {

	/**
	 * The counters of the fixtures used only here.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		Signatures::reset();
		Untagged::reset();
		Declarations::reset();
	}

	/**
	 * A private method on a marked class is found, registered, and called.
	 *
	 * @return void
	 */
	public function testAPrivateHandlerIsFoundAndCalled(): void {
		$this->bootWith( CountedHandler::class );

		$this->assertTrue( Hooks::has( 'counted_action' ) );

		do_action( 'counted_action', 'yes' );

		$this->assertSame( array( 'action:yes' ), CountedHandler::$calls );
	}

	/**
	 * Visibility decides nothing: a protected handler and a public one on a marked class
	 * are found and called like a private one.
	 *
	 * @return void
	 */
	public function testVisibilityDecidesNothing(): void {
		$this->bootWith( Signatures::class );

		do_action( 'protected_action', 'a' );
		do_action( 'public_action', 'b' );

		$this->assertSame( array( 'protected:a', 'public:b' ), Signatures::$calls );
	}

	/**
	 * A private static method is found too, and its class is never constructed.
	 *
	 * @return void
	 */
	public function testAPrivateStaticHandlerNeverBuildsItsClass(): void {
		$this->bootWith( StaticHandler::class );

		do_action( 'static_action', 'yes' );

		$this->assertSame( array( 'action:yes' ), StaticHandler::$calls );
		$this->assertSame( 'SHOUT', apply_filters( 'static_filter', 'shout' ) );
		$this->assertSame( 0, StaticHandler::$constructions );
	}

	/**
	 * One method answering to two actions is registered twice, each at its own priority.
	 *
	 * @return void
	 */
	public function testOneMethodCanAnswerToTwoHooks(): void {
		$this->bootWith( Declarations::class );

		$this->assertSame( array( 10 ), Hooks::priorities( 'init' ) );
		$this->assertSame( array( 5 ), Hooks::priorities( 'wp_loaded' ) );
		$this->assertSame( array( 20 ), Hooks::priorities( 'the_title' ) );
		$this->assertTrue( Hooks::hasShortCode( 'year' ) );

		// Registered is not answered: both of them have to actually reach the method.
		do_action( 'init' );
		do_action( 'wp_loaded' );

		$this->assertSame( 2, Declarations::$boots );
	}

	/**
	 * How many arguments a handler receives is read off its signature, and what the
	 * attribute writes is what wins.
	 *
	 * @return void
	 */
	public function testTheArgumentCountIsReadOffTheSignatureUnlessItIsWritten(): void {
		$this->bootWith( Signatures::class );

		$this->assertSame( array( 2 ), Hooks::acceptedArgs( 'deduced_action' ) );
		$this->assertSame( array( 0 ), Hooks::acceptedArgs( 'bare_action' ) );
		$this->assertSame( array( 3 ), Hooks::acceptedArgs( 'written_action' ) );

		do_action( 'deduced_action', 'a', 7, 'ignored' );
		do_action( 'bare_action' );
		do_action( 'written_action', 'b', 'c', 'd', 'too much' );

		$this->assertSame( array( 'deduced:a:7', 'bare', 'written:b,c,d' ), Signatures::$calls );
	}

	/**
	 * A priority below the one the platform gives by default is accepted and runs first,
	 * which is the platform's rule and not a range this bundle narrows.
	 *
	 * @return void
	 */
	public function testANegativePriorityRunsFirst(): void {
		$this->bootWith( Signatures::class );

		$this->assertSame( array( -5, 10 ), Hooks::priorities( 'ordered_action' ) );

		do_action( 'ordered_action' );

		$this->assertSame( array( 'early', 'late' ), Signatures::$calls );
	}

	/**
	 * A class the project excluded from its namespace load is not a service, so it is not
	 * a handler either — and excluding one is exactly what the recipe every project copies
	 * does to the interfaces and abstract classes it finds.
	 *
	 * @return void
	 */
	public function testAnExcludedClassIsNotAHandler(): void {
		$this->boot(
			static function ( ContainerBuilder $container ): void {
				$container->setDefinition(
					CountedHandler::class,
					self::serviceFor( CountedHandler::class )->addTag( 'container.excluded' )
				);
			}
		);

		$this->assertFalse( Hooks::has( 'counted_action' ) );
	}

	/**
	 * A hook whose name carries a percent sign is registered under the name that was
	 * written, not under what the container would have made of it.
	 *
	 * @return void
	 */
	public function testAPercentSignInAHookNameSurvivesTheContainer(): void {
		$this->bootWith( Signatures::class );

		$this->assertTrue( Hooks::has( '100%_filter' ) );
		$this->assertSame( 'value!', apply_filters( '100%_filter', 'value' ) );
	}

	/**
	 * A service the class attribute cannot be written on is reached by the tag, and
	 * behaves exactly the same.
	 *
	 * @return void
	 */
	public function testATagWrittenByHandFindsTheSameHandlers(): void {
		$this->boot(
			static function ( ContainerBuilder $container ): void {
				$container->setDefinition(
					Untagged::class,
					self::serviceFor( Untagged::class )->addTag( HandlerPass::HANDLER_TAG )
				);
			}
		);

		do_action( 'untagged_action', 'by-tag' );

		$this->assertSame( array( 'ran:by-tag' ), Untagged::$calls );
	}

	/**
	 * The mark reaching a service twice — from the attribute and from a tag written by
	 * hand — registers its hooks once, not twice.
	 *
	 * @return void
	 */
	public function testAMarkFoundTwiceRegistersOnce(): void {
		$this->boot(
			static function ( ContainerBuilder $container ): void {
				$container->setDefinition(
					CountedHandler::class,
					self::serviceFor( CountedHandler::class )->addTag( HandlerPass::HANDLER_TAG )
				);
			}
		);

		$this->assertSame( 1, Hooks::callbackCount( 'counted_action' ) );

		do_action( 'counted_action', 'once' );

		$this->assertSame( array( 'action:once' ), CountedHandler::$calls );
	}

	/**
	 * One class registered under two ids is two services, so it is two objects and its
	 * hooks are registered twice — which is one of the two ways a handler runs twice, and
	 * the one a project reaches by writing set() where it meant alias().
	 *
	 * @return void
	 */
	public function testOneClassUnderTwoIdsRegistersTwice(): void {
		$this->boot(
			static function ( ContainerBuilder $container ): void {
				$container->setDefinition( CountedHandler::class, self::serviceFor( CountedHandler::class ) );
				$container->setDefinition( 'app.the_same_again', self::serviceFor( CountedHandler::class ) );
			}
		);

		$this->assertSame( 2, Hooks::callbackCount( 'counted_action' ) );

		do_action( 'counted_action', 'twice' );

		$this->assertSame( array( 'action:twice', 'action:twice' ), CountedHandler::$calls );
		$this->assertSame( 2, CountedHandler::$constructions );
	}

	/**
	 * A service whose class is written as a container parameter is read through it.
	 *
	 * Left unresolved, the class is an expression that implements nothing and has no
	 * methods, and every hook the real class declares is lost without a word.
	 *
	 * @return void
	 */
	public function testAClassNamedByAParameterIsResolved(): void {
		$this->boot(
			static function ( ContainerBuilder $container ): void {
				$container->setParameter( 'app.handler_class', CountedHandler::class );
				$container->setDefinition( 'app.handler', self::serviceFor( '%app.handler_class%' ) );
			}
		);

		do_action( 'counted_action', 'by-parameter' );

		$this->assertSame( array( 'action:by-parameter' ), CountedHandler::$calls );
	}

	/**
	 * A service declared as the child of another carries no class of its own until long
	 * after this bundle has read it, so the parent's is what it is read from.
	 *
	 * The mark is written by hand here, and it has to be: a definition with no class of
	 * its own is one the library cannot read attributes off either, so a child that
	 * inherits everything from its parent is reached by its tag or not at all.
	 *
	 * @return void
	 */
	public function testAChildDefinitionIsReadFromItsParent(): void {
		$this->boot(
			static function ( ContainerBuilder $container ): void {
				$container->setDefinition( 'app.parent', ( new Definition( CountedHandler::class ) )->setAbstract( true ) );
				$container->setDefinition(
					'app.child',
					( new ChildDefinition( 'app.parent' ) )
						->setPublic( true )
						->addTag( HandlerPass::HANDLER_TAG )
				);
			}
		);

		do_action( 'counted_action', 'by-child' );

		$this->assertSame( array( 'action:by-child' ), CountedHandler::$calls );
	}

	/**
	 * A project with no handler at all boots, registers nothing, and does not mind.
	 *
	 * @return void
	 */
	public function testAProjectWithNoHandlerRegistersNothing(): void {
		$kernel = $this->boot();

		$this->assertTrue( $this->containerOf( $kernel )->has( HookRegistrar::SERVICE_ID ) );
		$this->assertFalse( Hooks::has( 'counted_action' ) );
	}
}
