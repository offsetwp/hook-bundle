<?php
/**
 * A fixture project of the OffsetWP Hook Bundle test suite.
 *
 * @author Jérôme Wohlschlegel
 * @package App\Hook
 */

declare( strict_types=1 );

namespace App\Hook;

use OffsetWP\Hook\Support\Action;

/**
 * A hook written the older way, in the same project as the newer one.
 */
final class Announcements extends Action {

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
	public string $hook_name = 'wp_footer';

	/**
	 * How many arguments the handler receives.
	 *
	 * @var int
	 */
	public int $hook_accepted_args = 0;

	/**
	 * Puts the record back to nothing.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$calls = array();
	}

	/**
	 * Answers to the action.
	 *
	 * @return void
	 */
	protected function handle(): void {
		self::$calls[] = 'footer';
	}
}
