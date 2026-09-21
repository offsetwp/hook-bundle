<?php
/**
 * OffsetWP Hook Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\Tests\Fixtures\Legacy
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\HookBundle\Tests\Fixtures\Legacy;

use OffsetWP\Hook\Support\Action;

/**
 * An action written the way the hook library has always taken one: a class whose
 * constructor is the registration.
 */
final class LegacyAction extends Action {

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
	 * The action this class answers to.
	 *
	 * @var string
	 */
	public string $hook_name = 'legacy_action';

	/**
	 * Counts one construction, then registers.
	 *
	 * @return void
	 */
	public function __construct() {
		++self::$constructions;

		parent::__construct();
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
	 * Answers to the action.
	 *
	 * @param string $what What the action carried.
	 * @return void
	 */
	protected function handle( string $what ): void {
		self::$calls[] = 'action:' . $what;
	}
}
