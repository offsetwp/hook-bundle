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
 * A filter that is never handed the value it filters.
 */
#[AsHookHandler]
final class ValuelessFilter {

	/**
	 * Declares a filter and takes no parameter.
	 *
	 * @return string
	 */
	#[AsFilter( 'valueless_filter' )]
	private function handle(): string {
		return '';
	}
}
