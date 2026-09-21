<?php
/**
 * OffsetWP Hook Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\Tests\Unit
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\HookBundle\Tests\Unit;

use OffsetWP\Bundle\HookBundle\HookRegistrar;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Handler\CountedHandler;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Handler\StaticHandler;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Hooks;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\HookTestCase;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Locator;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * What the registrar hands the platform, and what it refuses to build until it has to.
 *
 * The container is not involved here: the handlers are written out by hand, which is
 * what the compiler pass produces, and the locator is one that counts.
 *
 * @phpstan-import-type Handler from HookRegistrar
 */
#[CoversClass( HookRegistrar::class )]
final class HookRegistrarTest extends HookTestCase {

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
	 * One handler record, of the shape the compiler pass produces.
	 *
	 * @param string      $type          The kind of hook.
	 * @param string      $hook          The hook name or shortcode tag.
	 * @param string      $method        The method that answers to it.
	 * @param int         $priority      Lower runs first.
	 * @param int         $accepted_args How many arguments the method receives.
	 * @param bool        $is_static     Whether the method is static.
	 * @param string|null $handler_class The class it belongs to, which is also its service id.
	 * @phpstan-param class-string|null $handler_class
	 * @phpstan-return Handler
	 * @return array<string, mixed>
	 */
	private function handler(
		string $type = HookRegistrar::TYPE_ACTION,
		string $hook = 'counted_action',
		string $method = 'onAction',
		int $priority = 10,
		int $accepted_args = 1,
		bool $is_static = false,
		?string $handler_class = null,
	): array {
		$handler_class ??= CountedHandler::class;

		return array(
			'type'          => $type,
			'hook'          => $hook,
			'service'       => $handler_class,
			'class'         => $handler_class,
			'method'        => $method,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
			'is_static'     => $is_static,
		);
	}

	/**
	 * A locator that builds the counted handler and counts how often it is asked.
	 *
	 * @return Locator
	 */
	private function locator(): Locator {
		return new Locator( array( CountedHandler::class => static fn (): CountedHandler => new CountedHandler() ) );
	}

	/**
	 * Registering hands the platform the hook, the priority and the argument count, and
	 * builds nothing at all.
	 *
	 * @return void
	 */
	public function testRegisteringHandsOverTheHookAndBuildsNothing(): void {
		$locator = $this->locator();

		( new HookRegistrar( array( $this->handler( priority: 20, accepted_args: 2 ) ), $locator ) )->register();

		$this->assertTrue( Hooks::has( 'counted_action' ) );
		$this->assertSame( array( 20 ), Hooks::priorities( 'counted_action' ) );
		$this->assertSame( array( 2 ), Hooks::acceptedArgs( 'counted_action' ) );
		$this->assertSame( 0, CountedHandler::$constructions );
		$this->assertSame( 0, $locator->lookups( CountedHandler::class ) );
	}

	/**
	 * The hook firing is what builds the class, and it builds it once however many times
	 * the hook fires afterwards.
	 *
	 * @return void
	 */
	public function testTheFirstCallBuildsTheClassAndTheSecondDoesNot(): void {
		$locator = $this->locator();

		( new HookRegistrar( array( $this->handler() ), $locator ) )->register();

		$this->assertSame( 0, CountedHandler::$constructions );

		do_action( 'counted_action', 'first' );

		$this->assertSame( 1, CountedHandler::$constructions );
		$this->assertSame( array( 'action:first' ), CountedHandler::$calls );

		do_action( 'counted_action', 'second' );

		$this->assertSame( 1, CountedHandler::$constructions );
		$this->assertSame( array( 'action:first', 'action:second' ), CountedHandler::$calls );
		$this->assertSame( 2, $locator->lookups( CountedHandler::class ) );
	}

	/**
	 * A private method is reached, which is the whole point of the closure being bound
	 * to the class rather than taken from the object.
	 *
	 * @return void
	 */
	public function testAPrivateMethodIsReached(): void {
		$this->assertFalse( ( new \ReflectionMethod( CountedHandler::class, 'onFilter' ) )->isPublic() );

		$handler = $this->handler(
			type: HookRegistrar::TYPE_FILTER,
			hook: 'counted_filter',
			method: 'onFilter',
			accepted_args: 2,
		);

		( new HookRegistrar( array( $handler ), $this->locator() ) )->register();

		$this->assertSame( 'hello#7', apply_filters( 'counted_filter', 'hello', 7 ) );
	}

	/**
	 * A static handler is called on the class: nothing is built, and the locator is
	 * never even consulted.
	 *
	 * @return void
	 */
	public function testAStaticHandlerNeverBuildsAnything(): void {
		$locator = new Locator();

		$handlers = array(
			$this->handler(
				hook: 'static_action',
				is_static: true,
				handler_class: StaticHandler::class,
			),
			$this->handler(
				type: HookRegistrar::TYPE_FILTER,
				hook: 'static_filter',
				method: 'onFilter',
				is_static: true,
				handler_class: StaticHandler::class,
			),
		);

		( new HookRegistrar( $handlers, $locator ) )->register();

		do_action( 'static_action', 'once' );

		$this->assertSame( 'SHOUT', apply_filters( 'static_filter', 'shout' ) );
		$this->assertSame( array( 'action:once' ), StaticHandler::$calls );
		$this->assertSame( 0, StaticHandler::$constructions );
		$this->assertSame( 0, $locator->lookups( StaticHandler::class ) );
	}

	/**
	 * A shortcode is registered under its tag, and is handed the three arguments the
	 * platform always passes.
	 *
	 * @return void
	 */
	public function testAShortCodeIsRegisteredUnderItsTag(): void {
		$handler = $this->handler(
			type: HookRegistrar::TYPE_SHORT_CODE,
			hook: 'counted',
			method: 'onShortCode',
		);

		( new HookRegistrar( array( $handler ), $this->locator() ) )->register();

		$this->assertTrue( Hooks::hasShortCode( 'counted' ) );
		$this->assertSame( 0, CountedHandler::$constructions );

		$atts = array(
			'x' => 'a',
			'y' => 'b',
		);

		$this->assertSame( 'a,b|body|counted', Hooks::runShortCode( 'counted', $atts, 'body' ) );
		$this->assertSame( 1, CountedHandler::$constructions );
	}

	/**
	 * Registering twice registers once, so two kernels carrying this bundle register
	 * their own handlers rather than one kernel's twice.
	 *
	 * @return void
	 */
	public function testRegisteringTwiceRegistersOnce(): void {
		$registrar = new HookRegistrar( array( $this->handler() ), $this->locator() );

		$registrar->register();
		$registrar->register();

		$this->assertSame( 1, Hooks::callbackCount( 'counted_action' ) );
	}

	/**
	 * A handler of a kind nothing produces names the class and the method it came from.
	 *
	 * @return void
	 */
	public function testAnUnknownKindNamesTheMethodItCameFrom(): void {
		$registrar = new HookRegistrar( array( $this->handler( type: 'whatever' ) ), $this->locator() );

		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage( '"' . CountedHandler::class . '::onAction()" was recorded as a "whatever"' );

		$registrar->register();
	}

	/**
	 * A registrar built without a locator says so when a handler needs one, and names
	 * the service that has been taken from it.
	 *
	 * @return void
	 */
	public function testAMissingLocatorNamesTheServiceThatWasReplaced(): void {
		$registrar = new HookRegistrar( array( $this->handler() ) );

		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage( 'The service "hook.registrar" has been replaced' );

		$registrar->register();
	}

	/**
	 * A registrar with nothing to register registers nothing, and does not mind.
	 *
	 * @return void
	 */
	public function testAnEmptyRegistrarIsHarmless(): void {
		( new HookRegistrar() )->register();

		$this->assertFalse( Hooks::has( 'counted_action' ) );
	}

	/**
	 * A registration that failed half way is not remembered as one that worked: asked
	 * again, the registrar says the same thing rather than returning in silence.
	 *
	 * @return void
	 */
	public function testAFailedRegistrationIsNotRememberedAsASuccess(): void {
		$registrar = new HookRegistrar(
			array( $this->handler(), $this->handler( type: 'whatever' ) ),
			$this->locator()
		);

		foreach ( array( 'first', 'second' ) as $attempt ) {
			try {
				$registrar->register();

				$this->fail( sprintf( 'The %s attempt registered without complaining.', $attempt ) );
			} catch ( \LogicException $failure ) {
				$this->assertStringContainsString( 'was recorded as a "whatever"', $failure->getMessage() );
			}
		}
	}

	/**
	 * A hook fired through the by-reference form hands its array over as it stands, and a
	 * string key in it would be read as a named argument on the way into the handler.
	 *
	 * @return void
	 */
	public function testAnArgumentArrayCarryingAStringKeyStillArrivesInOrder(): void {
		$handler = $this->handler(
			type: HookRegistrar::TYPE_FILTER,
			hook: 'counted_filter',
			method: 'onFilter',
			accepted_args: 2,
		);

		( new HookRegistrar( array( $handler ), $this->locator() ) )->register();

		$this->assertSame(
			'hello#7',
			apply_filters_ref_array(
				'counted_filter',
				array(
					'hello',
					'not_a_parameter' => 7,
				)
			)
		);
	}

	/**
	 * A class PHP ships cannot be a scope, and saying so beats the warning PHP emits on
	 * the way to returning false.
	 *
	 * @return void
	 */
	public function testAClassPhpShipsIsRefusedByName(): void {
		$registrar = new HookRegistrar(
			array( $this->handler( handler_class: \ArrayObject::class ) ),
			new Locator( array( \ArrayObject::class => static fn (): \ArrayObject => new \ArrayObject() ) )
		);

		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage( 'The handler class "ArrayObject" cannot be bound to, so its methods cannot be reached: it is one of PHP\'s own classes' );

		$registrar->register();
	}

	/**
	 * An action reaches the platform through the function that takes an action, and a
	 * filter through the one that takes a filter. The two do the same thing, so nothing
	 * else would notice them being swapped.
	 *
	 * @return void
	 */
	public function testEachKindReachesThePlatformThroughItsOwnFunction(): void {
		$handlers = array(
			$this->handler( hook: 'mixed_hook' ),
			$this->handler( type: HookRegistrar::TYPE_FILTER, hook: 'mixed_hook', method: 'onFilter', priority: 20, accepted_args: 2 ),
		);

		( new HookRegistrar( $handlers, $this->locator() ) )->register();

		$this->assertSame( array( 'add_action', 'add_filter' ), Hooks::addedVia( 'mixed_hook' ) );
	}
}
