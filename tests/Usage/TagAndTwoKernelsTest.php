<?php
/**
 * OffsetWP Hook Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\Tests\Usage
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\HookBundle\Tests\Usage;

use App\Hook\Seo;
use OffsetWP\Bundle\HookBundle\HookBundle;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Handler\Untagged;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Hooks;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The two arrangements a project reaches for when the plain recipe is not enough: a tag
 * written by hand, and more than one kernel in one request.
 */
#[CoversClass( HookBundle::class )]
final class TagAndTwoKernelsTest extends UsageTestCase {

	/**
	 * A service with autoconfiguration turned off, reached by the tag alone, and the
	 * hook on its private method fires like any other.
	 *
	 * @return void
	 */
	public function testATagReachesAServiceAutoconfigurationDoesNot(): void {
		$this->boot( 'SiteWithTag' );

		do_action( 'untagged_action', 'yes' );

		$this->assertSame( array( 'ran:yes' ), Untagged::$calls );
	}

	/**
	 * A mu-plugin and a theme each booting a kernel is the ordinary arrangement on this
	 * platform. Each kernel registers its own handlers, and neither loses any.
	 *
	 * @return void
	 */
	public function testTwoKernelsInOneRequestEachRegisterTheirOwn(): void {
		$this->boot();
		$this->boot( 'SiteWithTag' );

		do_action( 'init' );
		do_action( 'untagged_action', 'yes' );

		$this->assertSame( array( 'boot' ), Seo::$calls );
		$this->assertSame( array( 'ran:yes' ), Untagged::$calls );
		$this->assertSame( 1, Hooks::callbackCount( 'init' ) );
		$this->assertSame( 1, Hooks::callbackCount( 'untagged_action' ) );
	}
}
