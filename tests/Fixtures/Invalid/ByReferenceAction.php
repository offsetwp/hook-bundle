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
 * A handler asking for a reference the platform's callback cannot give it back.
 */
#[AsHookHandler]
final class ByReferenceAction {

	/**
	 * Declares an action and takes its argument by reference.
	 *
	 * @param array<int, string> $items What the action carried.
	 * @return void
	 */
	#[AsAction( 'by_reference_action' )]
	private function handle( array &$items ): void {
		$items[] = 'never seen';
	}
}
