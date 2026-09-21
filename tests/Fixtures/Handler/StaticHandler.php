<?php
/**
 * OffsetWP Hook Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\Tests\Fixtures\Handler
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\HookBundle\Tests\Fixtures\Handler;

use OffsetWP\Bundle\HookBundle\Attribute\AsAction;
use OffsetWP\Bundle\HookBundle\Attribute\AsFilter;
use OffsetWP\Bundle\HookBundle\Attribute\AsHookHandler;

/**
 * A handler whose methods are private and static.
 *
 * Its constructor counts, and the count is expected to stay at zero for ever: a static
 * handler is called on the class, so nothing is ever built for it — not on boot, not on
 * the first hook that fires, not ever.
 */
#[AsHookHandler]
final class StaticHandler {

	/**
	 * How many times this class has been constructed.
	 *
	 * @var int
	 */
	public static int $constructions = 0;

	/**
	 * What each handler was called with, in order.
	 *
	 * @var array<int, string>
	 */
	public static array $calls = array();

	/**
	 * Counts one construction.
	 *
	 * @return void
	 */
	public function __construct() {
		++self::$constructions;
	}

	/**
	 * Puts both counters back to nothing.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$constructions = 0;
		self::$calls         = array();
	}

	/**
	 * Answers to an action, without an instance.
	 *
	 * @param string $what What the action carried.
	 * @return void
	 */
	#[AsAction( 'static_action' )]
	private static function onAction( string $what ): void {
		self::$calls[] = 'action:' . $what;
	}

	/**
	 * Answers to a filter, without an instance.
	 *
	 * @param string $value The value being filtered.
	 * @return string
	 */
	#[AsFilter( 'static_filter' )]
	private static function onFilter( string $value ): string {
		return strtoupper( $value );
	}
}
