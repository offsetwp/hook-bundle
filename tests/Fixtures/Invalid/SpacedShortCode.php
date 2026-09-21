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
 * A shortcode whose tag the platform reserves characters in.
 */
#[AsHookHandler]
final class SpacedShortCode {

	/**
	 * Declares a shortcode under a tag carrying a space.
	 *
	 * @return string
	 */
	#[AsShortCode( 'my tag' )]
	private function handle(): string {
		return '';
	}
}
