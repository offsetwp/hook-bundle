<?php
/**
 * OffsetWP Hook Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\Tests\Fixtures\Legacy
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\HookBundle\Tests\Fixtures\Legacy;

use OffsetWP\Hook\Support\ShortCode;

/**
 * A shortcode written the way the hook library has always taken one.
 */
final class LegacyShortCode extends ShortCode {

	/**
	 * The tag this class answers to.
	 *
	 * @var string
	 */
	public string $hook_name = 'legacy_code';

	/**
	 * Answers to the shortcode.
	 *
	 * @param array<string, string> $atts    The attributes written in the post.
	 * @param string|null           $content The enclosed content.
	 * @param string                $tag     The tag itself.
	 * @return string
	 */
	protected function handle( array $atts, ?string $content, string $tag ): string {
		return implode( '|', array( implode( ',', $atts ), $content ?? '', $tag ) );
	}
}
