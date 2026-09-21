<?php
/**
 * OffsetWP Hook Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\Tests\Fixtures\Invalid
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\HookBundle\Tests\Fixtures\Invalid;

use OffsetWP\Bundle\HookBundle\Attribute\AsHookHandler;
use OffsetWP\Bundle\HookBundle\Attribute\AsShortCode;

/**
 * A shortcode that cannot return what replaces it.
 */
#[AsHookHandler]
final class VoidShortCode {

	/**
	 * Declares a shortcode and returns nothing.
	 *
	 * @return void
	 */
	#[AsShortCode( 'void_code' )]
	private function handle(): void {
	}
}
