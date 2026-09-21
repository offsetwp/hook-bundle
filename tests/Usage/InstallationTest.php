<?php
/**
 * OffsetWP Hook Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\Tests\Usage
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\HookBundle\Tests\Usage;

use App\Hook\Announcements;
use App\Hook\Counter;
use App\Hook\Seo;
use App\Service\SiteName;
use OffsetWP\Bundle\HookBundle\HookBundle;
use OffsetWP\Bundle\HookBundle\HookRegistrar;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Handler\CountedHandler;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Hooks;
use OffsetWP\Framework\Kernel;
use OffsetWP\Support\Env;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * A project that does what the README says, and gets what the README promises.
 *
 * Two files: a config/bundles.php that lists the bundle, and a config/services.php with
 * one load() over the project's own namespace. Nothing else, no key under "hook", no tag.
 */
#[CoversClass( HookBundle::class )]
#[CoversClass( HookRegistrar::class )]
final class InstallationTest extends UsageTestCase {

	/**
	 * An action declared on a private method runs, and the service it needed was built
	 * for it.
	 *
	 * @return void
	 */
	public function testAPrivateActionRuns(): void {
		$this->boot();

		do_action( 'init' );

		$this->assertSame( array( 'boot' ), Seo::$calls );
	}

	/**
	 * A filter declared on a private method returns, with its priority and its argument
	 * count as they were written.
	 *
	 * @return void
	 */
	public function testAPrivateFilterReturns(): void {
		$this->boot();

		$this->assertSame( array( 20 ), Hooks::priorities( 'the_title' ) );
		$this->assertSame( array( 2 ), Hooks::acceptedArgs( 'the_title' ) );
		$this->assertSame( 'Hello — Étoile Malraux (#7)', apply_filters( 'the_title', 'Hello', 7 ) );
	}

	/**
	 * A shortcode declared on a private static method answers, and nothing at all is
	 * built for it — not the class, not the service it would have needed.
	 *
	 * @return void
	 */
	public function testAPrivateStaticShortCodeAnswersWithoutBuildingAnything(): void {
		$this->boot();

		$this->assertTrue( Hooks::hasShortCode( 'year' ) );
		$this->assertSame( '2026', Hooks::runShortCode( 'year' ) );
		$this->assertSame( 0, SiteName::$constructions );
	}

	/**
	 * A class of the older form, in the same project, registers itself too.
	 *
	 * @return void
	 */
	public function testAClassOfTheOlderFormRegistersItself(): void {
		$this->boot();

		do_action( 'wp_footer' );

		$this->assertSame( array( 'footer' ), Announcements::$calls );
	}

	/**
	 * And the promise the package exists for, at the level a project sees it: booting
	 * registers every hook and builds nothing behind them.
	 *
	 * @return void
	 */
	public function testBootingRegistersEveryHookAndBuildsNothing(): void {
		$this->boot();

		$this->assertTrue( Hooks::has( 'init' ) );
		$this->assertTrue( Hooks::has( 'the_title' ) );
		$this->assertTrue( Hooks::has( 'counter_action' ) );
		$this->assertTrue( Hooks::hasShortCode( 'year' ) );

		$this->assertSame( 0, Counter::$constructions );
		$this->assertSame( 0, SiteName::$constructions );

		do_action( 'counter_action' );

		$this->assertSame( 1, Counter::$constructions );
		$this->assertSame( array( 'ran' ), Counter::$calls );
	}

	/**
	 * A project that has not registered the bundle gets nothing, which is one of the two
	 * arrangements in which a hook declared with an attribute does not fire.
	 *
	 * @return void
	 */
	public function testAProjectWithoutTheBundleRegistersNothing(): void {
		$kernel = $this->boot( 'SiteWithoutBundle' );

		$this->assertFalse( $kernel->hasService( HookRegistrar::SERVICE_ID ) );
		$this->assertFalse( Hooks::has( 'counted_action' ) );
	}

	/**
	 * And the other one, which costs an evening because the kernel does boot: built with
	 * a services file rather than a config directory, it registers no bundle at all.
	 *
	 * @return void
	 */
	public function testAKernelBuiltWithAServicesFileRegistersNoBundle(): void {
		$root = $this->site( 'SiteWithServicesFile' );

		$kernel = Kernel::configure( $root )
			->environment( Env::PRODUCTION )
			->services( $root . DIRECTORY_SEPARATOR . 'services.php' )
			->boot();

		$this->assertTrue( $kernel->hasService( CountedHandler::class ) );
		$this->assertFalse( $kernel->hasService( HookRegistrar::SERVICE_ID ) );
		$this->assertFalse( Hooks::has( 'counted_action' ) );
	}

	/**
	 * The documented silence, pinned so that it stays the only one: a class whose
	 * handlers are all private and which carries no mark is not found, the build says
	 * nothing, and nothing is registered.
	 *
	 * @return void
	 */
	public function testAnUnmarkedPrivateHandlerIsSilentlyNotFound(): void {
		$this->boot( 'SiteWithUnmarkedHandler' );

		$this->assertFalse( Hooks::has( 'untagged_action' ) );
	}
}
