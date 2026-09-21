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
 * A handler that counts how often it is constructed, with every handler private.
 *
 * The counter is what the laziness of the package is asserted against: it stays at zero
 * until a hook this class answers to actually fires.
 */
#[AsHookHandler]
final class CountedHandler {

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
	 * Answers to an action.
	 *
	 * @param string $what What the action carried.
	 * @return void
	 */
	#[AsAction( 'counted_action' )]
	private function onAction( string $what ): void {
		self::$calls[] = 'action:' . $what;
	}

	/**
	 * Answers to a filter, and returns what replaces the value.
	 *
	 * @param string $value The value being filtered.
	 * @param int    $id    What follows it.
	 * @return string
	 */
	#[AsFilter( 'counted_filter', priority: 20, accepted_args: 2 )]
	private function onFilter( string $value, int $id ): string {
		self::$calls[] = 'filter:' . $value;

		return $value . '#' . $id;
	}

	/**
	 * The body of a shortcode.
	 *
	 * @param array<string, string> $atts    The attributes written in the post.
	 * @param string|null           $content The enclosed content.
	 * @param string                $tag     The tag itself.
	 * @return string
	 */
	#[AsShortCode( 'counted' )]
	private function onShortCode( array $atts, ?string $content, string $tag ): string {
		self::$calls[] = 'shortcode:' . $tag;

		return implode( '|', array( implode( ',', $atts ), $content ?? '', $tag ) );
	}
}
