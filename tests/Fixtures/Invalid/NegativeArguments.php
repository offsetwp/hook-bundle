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
 * An action asking for fewer arguments than none.
 */
#[AsHookHandler]
final class NegativeArguments {

	/**
	 * Declares an action with a count that is not a count.
	 *
	 * @return void
	 */
	#[AsAction( 'negative_action', accepted_args: -1 )]
	private function handle(): void {
	}
}
