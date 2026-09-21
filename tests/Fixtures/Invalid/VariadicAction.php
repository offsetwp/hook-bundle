<?php
/**
 * OffsetWP Hook Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\Tests\Fixtures\Invalid
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\HookBundle\Tests\Fixtures\Invalid;

use OffsetWP\Bundle\HookBundle\Attribute\AsAction;
use OffsetWP\Bundle\HookBundle\Attribute\AsHookHandler;

/**
 * An action whose argument count cannot be read off its signature.
 */
#[AsHookHandler]
final class VariadicAction {

	/**
	 * Declares an action and takes whatever comes.
	 *
	 * @param mixed ...$args Whatever the action carried.
	 * @return void
	 */
	#[AsAction( 'variadic_action' )]
	private function handle( mixed ...$args ): void {
	}
}
