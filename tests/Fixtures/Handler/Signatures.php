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
 * The signatures an argument count is read off, and the ones that override it.
 */
#[AsHookHandler]
final class Signatures {

	/**
	 * What each handler was called with, in order.
	 *
	 * @var array<int, string>
	 */
	public static array $calls = array();

	/**
	 * Puts the record back to nothing.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$calls = array();
	}

	/**
	 * Two parameters, and no argument count written, so two is what is read.
	 *
	 * @param string $first  The first argument.
	 * @param int    $second The second.
	 * @return void
	 */
	#[AsAction( 'deduced_action' )]
	private function twoParameters( string $first, int $second ): void {
		self::$calls[] = sprintf( 'deduced:%s:%d', $first, $second );
	}

	/**
	 * No parameter at all, so the platform hands it nothing.
	 *
	 * @return void
	 */
	#[AsAction( 'bare_action' )]
	private function noParameters(): void {
		self::$calls[] = 'bare';
	}

	/**
	 * A handler that takes whatever comes and is told how much of it to expect, which is
	 * the one way the written count is observable: a method with a fixed arity would see
	 * the same thing whatever the platform was told.
	 *
	 * @param string ...$all Everything the action carried.
	 * @return void
	 */
	#[AsAction( 'written_action', accepted_args: 3 )]
	private function written( string ...$all ): void {
		self::$calls[] = 'written:' . implode( ',', $all );
	}

	/**
	 * A hook whose name carries a percent sign, which the container reads as one of its
	 * own parameters unless it is written twice on the way in.
	 *
	 * @param string $value The value being filtered.
	 * @return string
	 */
	#[AsFilter( '100%_filter' )]
	private function percent( string $value ): string {
		return $value . '!';
	}

	/**
	 * A handler at a priority below the one the platform gives by default, which the
	 * platform accepts and runs first.
	 *
	 * @return void
	 */
	#[AsAction( 'ordered_action', priority: -5 )]
	private function early(): void {
		self::$calls[] = 'early';
	}

	/**
	 * And one at the default, to have something for the one above to come before.
	 *
	 * @return void
	 */
	#[AsAction( 'ordered_action' )]
	private function late(): void {
		self::$calls[] = 'late';
	}

	/**
	 * A protected handler, which is neither of the two visibilities anyone thinks about.
	 *
	 * @param string $what What the action carried.
	 * @return void
	 */
	#[AsAction( 'protected_action' )]
	protected function protectedHandler( string $what ): void {
		self::$calls[] = 'protected:' . $what;
	}

	/**
	 * A public handler, on a class that carries the mark, which is the only thing that
	 * makes it different from one that does not.
	 *
	 * @param string $what What the action carried.
	 * @return void
	 */
	#[AsAction( 'public_action' )]
	public function publicHandler( string $what ): void {
		self::$calls[] = 'public:' . $what;
	}
}
