<?php
/**
 * OffsetWP Hook Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\Tests\Fixtures\Invalid
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\HookBundle\Tests\Fixtures\Invalid;

use OffsetWP\Bundle\HookBundle\Attribute\AsFilter;
use OffsetWP\Bundle\HookBundle\Attribute\AsHookHandler;

/**
 * A filter told it accepts nothing, which is a call it cannot survive.
 */
#[AsHookHandler]
final class ArgumentlessFilter {

	/**
	 * Declares a filter and says it takes no argument.
	 *
	 * @param string $value The value being filtered.
	 * @return string
	 */
	#[AsFilter( 'argumentless_filter', accepted_args: 0 )]
	private function handle( string $value ): string {
		return $value;
	}
}
