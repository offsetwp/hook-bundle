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

/**
 * A class declaring a hook on a public method, with no mark on the class.
 *
 * This is the one shape the silence can be taken away from: the container sees a public
 * attributed method by itself, so the class that forgot its mark is told rather than
 * ignored.
 */
final class UnmarkedPublicHandler {

	/**
	 * Declares an action, publicly, on a class nothing marks.
	 *
	 * @return void
	 */
	#[AsAction( 'unmarked_action' )]
	public function handle(): void {
	}
}
