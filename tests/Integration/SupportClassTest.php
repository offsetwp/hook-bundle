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
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Handler\CountedHandler;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Hooks;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\KernelTestCase;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Legacy\LegacyAction;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Legacy\LegacyFilter;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Legacy\LegacyShortCode;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * The form the hook library has always taken keeps working, and costs what it costs.
 *
 * A class extending one of the three support classes registers itself in its own
 * constructor, so it has to be built for its hook to exist at all. The bundle says so on
 * the project's behalf, and the contrast with the attribute form is asserted here rather
 * than claimed: one is built whether or not its hook ever fires, the other is not.
 */
#[CoversClass( HookBundle::class )]
final class SupportClassTest extends KernelTestCase {

	/**
	 * The counter is static and the suite runs in one process, in a random order.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		LegacyAction::reset();
	}

	/**
	 * An action class registers itself, with nothing written in the project's own
	 * configuration to make it happen.
	 *
	 * @return void
	 */
	public function testAnActionClassRegistersItself(): void {
		$this->bootWith( LegacyAction::class );

		$this->assertTrue( Hooks::has( 'legacy_action' ) );

		do_action( 'legacy_action', 'yes' );

		$this->assertSame( array( 'action:yes' ), LegacyAction::$calls );
	}

	/**
	 * A filter class, the same way.
	 *
	 * @return void
	 */
	public function testAFilterClassRegistersItself(): void {
		$this->bootWith( LegacyFilter::class );

		$this->assertSame( 'value | legacy', apply_filters( 'legacy_filter', 'value' ) );
	}

	/**
	 * A shortcode class, the same way.
	 *
	 * @return void
	 */
	public function testAShortCodeClassRegistersItself(): void {
		$this->bootWith( LegacyShortCode::class );

		$this->assertTrue( Hooks::hasShortCode( 'legacy_code' ) );
		$this->assertSame( 'a|body|legacy_code', Hooks::runShortCode( 'legacy_code', array( 'x' => 'a' ), 'body' ) );
	}

	/**
	 * The one condition on it, which the section next to it in the README is about: the
	 * bundle says the tag on the project's behalf through autoconfiguration, so a service
	 * that has autoconfiguration turned off is not registered by anything.
	 *
	 * @return void
	 */
	public function testAClassOfTheOlderFormNeedsAutoconfiguration(): void {
		$this->boot(
			static function ( ContainerBuilder $container ): void {
				$container->setDefinition(
					LegacyAction::class,
					( new Definition( LegacyAction::class ) )->setPublic( true )
				);
			}
		);

		$this->assertSame( 0, LegacyAction::$constructions );
		$this->assertFalse( Hooks::has( 'legacy_action' ) );
	}

	/**
	 * And the difference the attributes exist for, in one assertion: a class of the old
	 * form is built while the container compiles, whether or not its hook ever fires; a
	 * class of the new form is not built at all.
	 *
	 * @return void
	 */
	public function testTheOldFormIsBuiltOnEveryRequestAndTheNewOneIsNot(): void {
		$this->bootWith( LegacyAction::class, CountedHandler::class );

		$this->assertSame( 1, LegacyAction::$constructions );
		$this->assertSame( 0, CountedHandler::$constructions );
		$this->assertTrue( Hooks::has( 'legacy_action' ) );
		$this->assertTrue( Hooks::has( 'counted_action' ) );
	}
}
