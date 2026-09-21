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
 * An action with no name, which nothing would ever fire.
 */
#[AsHookHandler]
final class NamelessAction {

	/**
	 * Declares an action and does not name it.
	 *
	 * @return void
	 */
	#[AsAction( '  ' )]
	private function handle(): void {
	}
}
