<?php
/**
 * A fixture project of the OffsetWP Hook Bundle test suite.
 *
 * @author Jérôme Wohlschlegel
 * @package App\Hook
 */

declare( strict_types=1 );

namespace App\Hook;

use OffsetWP\Bundle\HookBundle\Attribute\AsAction;
use OffsetWP\Bundle\HookBundle\Attribute\AsHookHandler;

/**
 * A handler nobody in the usage suite ever fires, so that what it costs can be counted.
 */
#[AsHookHandler]
final class Counter {

	/**
	 * How many times this class has been constructed.
	 *
	 * @var int
	 */
	public static int $constructions = 0;

	/**
	 * What the handler was called with, in order.
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
	 * Answers to an action the suite only fires on purpose.
	 *
	 * @return void
	 */
	#[AsAction( 'counter_action' )]
	private function onAction(): void {
		self::$calls[] = 'ran';
	}
}
