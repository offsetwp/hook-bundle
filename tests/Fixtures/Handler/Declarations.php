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
use OffsetWP\Bundle\HookBundle\Attribute\AsShortCode;

/**
 * One class carrying every shape the attributes allow.
 *
 * Every handler is private, which is the shape the package is built for and the one the
 * container cannot see by itself.
 */
#[AsHookHandler]
final class Declarations {

	/**
	 * How many times the two-action handler has been called.
	 *
	 * @var int
	 */
	public static int $boots = 0;

	/**
	 * Puts the counter back to nothing.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$boots = 0;
	}

	/**
	 * Answers to two actions, and takes nothing.
	 *
	 * @return void
	 */
	#[AsAction( 'init' )]
	#[AsAction( 'wp_loaded', priority: 5 )]
	private function boot(): void {
		++self::$boots;
	}

	/**
	 * Answers to a filter, with an argument count written rather than read.
	 *
	 * @param string $title The title being filtered.
	 * @param int    $id    The post it belongs to.
	 * @return string
	 */
	#[AsFilter( 'the_title', priority: 20, accepted_args: 2 )]
	private function title( string $title, int $id ): string {
		return $title . '#' . $id;
	}

	/**
	 * The body of a shortcode, and static, so nothing is ever constructed for it.
	 *
	 * @return string
	 */
	#[AsShortCode( 'year' )]
	private static function year(): string {
		return '2026';
	}
}
