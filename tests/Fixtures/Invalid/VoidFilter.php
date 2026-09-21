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
 * A filter that cannot return what it was asked to filter.
 */
#[AsHookHandler]
final class VoidFilter {

	/**
	 * Declares a filter and returns nothing.
	 *
	 * @param string $value The value being filtered.
	 * @return void
	 */
	#[AsFilter( 'void_filter' )]
	private function handle( string $value ): void {
	}
}
