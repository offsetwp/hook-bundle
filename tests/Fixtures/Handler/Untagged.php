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

/**
 * A handler with no mark on its class, reached by the tag instead.
 *
 * This is the shape of a service a project cannot put the class attribute on: one whose
 * autoconfiguration is turned off, or one from a package it does not own.
 */
final class Untagged {

	/**
	 * What the handler was called with, in order.
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
	 * Answers to an action, privately, with nothing on the class saying so.
	 *
	 * @param string $what What the action carried.
	 * @return void
	 */
	#[AsAction( 'untagged_action' )]
	private function onAction( string $what ): void {
		self::$calls[] = 'ran:' . $what;
	}
}
