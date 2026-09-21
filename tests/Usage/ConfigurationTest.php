<?php
/**
 * OffsetWP Hook Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\Tests\Usage
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\HookBundle\Tests\Usage;

use OffsetWP\Bundle\HookBundle\HookBundle;
use OffsetWP\Bundle\HookBundle\HookRegistrar;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Hooks;
use OffsetWP\Support\Env;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * There is nothing to configure, and a project that tries is told so.
 *
 * The bundle still declares an extension, because the kernel loads one for every bundle
 * it registers. What it does not declare is a single key, and the failure a project gets
 * for writing one says why rather than listing the options it does not have.
 */
#[CoversClass( HookBundle::class )]
final class ConfigurationTest extends UsageTestCase {

	/**
	 * A project that writes a key under "hook" is told the key takes no options, and
	 * what decides this bundle's behaviour instead.
	 *
	 * @return void
	 */
	public function testAConfigurationKeyIsRefusedWithAReason(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'The "hook" key takes no options. This bundle is configured by the attributes on your own classes and by nothing else, so there is nothing to write here. Delete the file, or the key.' );

		$this->boot( 'SiteWithConfiguration' );
	}

	/**
	 * And a project that writes nothing boots, because the empty configuration the
	 * kernel loads for every registered bundle has to pass through untouched.
	 *
	 * @return void
	 */
	public function testAProjectThatConfiguresNothingBoots(): void {
		$kernel = $this->boot();

		$this->assertTrue( $kernel->hasService( HookRegistrar::SERVICE_ID ) );
	}

	/**
	 * The file that registers a bundle is a map of environment to whether it is carried,
	 * and a project carrying it in one environment does not carry it in the others.
	 *
	 * @return void
	 */
	public function testTheBundleIsCarriedOnlyInTheEnvironmentsItIsEnabledFor(): void {
		$in_production = $this->boot( 'SiteForProductionOnly' );

		$this->assertTrue( $in_production->hasService( HookRegistrar::SERVICE_ID ) );
		$this->assertTrue( Hooks::has( 'counted_action' ) );

		Hooks::reset();

		$in_development = $this->boot( 'SiteForProductionOnly', Env::DEVELOPMENT );

		$this->assertFalse( $in_development->hasService( HookRegistrar::SERVICE_ID ) );
		$this->assertFalse( Hooks::has( 'counted_action' ) );
	}
}
