<?php
/**
 * OffsetWP Hook Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\Tests\Fixtures\Legacy
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\HookBundle\Tests\Fixtures\Legacy;

use OffsetWP\Hook\Support\Filter;

/**
 * A filter written the way the hook library has always taken one.
 */
final class LegacyFilter extends Filter {

	/**
	 * The filter this class answers to.
	 *
	 * @var string
	 */
	public string $hook_name = 'legacy_filter';

	/**
	 * Answers to the filter.
	 *
	 * @param string $value The value being filtered.
	 * @return string
	 */
	protected function handle( string $value ): string {
		return $value . ' | legacy';
	}
}
