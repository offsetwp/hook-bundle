<?php
/**
 * OffsetWP Hook Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\Tests\Fixtures
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\HookBundle\Tests\Fixtures;

use PHPUnit\Framework\TestCase;

/**
 * What every test in this repository starts from: an empty hook register.
 *
 * The register is static and the suite runs in one process, in a random order. It is
 * cleared on the way in as well as on the way out: clearing on the way out depends on
 * every other test case doing the same, and clearing on the way in depends on nothing.
 */
abstract class HookTestCase extends TestCase {

	/**
	 * Nothing is carried in from the last test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		Hooks::reset();
	}

	/**
	 * And nothing is left behind for the next one.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Hooks::reset();

		parent::tearDown();
	}
}
