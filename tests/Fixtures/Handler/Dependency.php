<?php
/**
 * OffsetWP Hook Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\Tests\Fixtures\Handler
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\HookBundle\Tests\Fixtures\Handler;

/**
 * A service a handler needs, and that a handler nobody calls must not cost.
 */
final class Dependency {

	/**
	 * How many times this class has been constructed.
	 *
	 * @var int
	 */
	public static int $constructions = 0;

	/**
	 * Counts one construction.
	 *
	 * @return void
	 */
	public function __construct() {
		++self::$constructions;
	}

	/**
	 * Puts the counter back to nothing.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$constructions = 0;
	}

	/**
	 * Something for the handler to call, so that the dependency is real.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'dependency';
	}
}
