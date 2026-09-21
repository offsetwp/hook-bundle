<?php
/**
 * OffsetWP Hook Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\Tests\Fixtures\Handler
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\HookBundle\Tests\Fixtures\Handler;

use OffsetWP\Bundle\HookBundle\Attribute\AsAction;
use OffsetWP\Bundle\HookBundle\Attribute\AsHookHandler;

/**
 * A handler with a dependency of its own, so that what a hook nobody fires costs can be
 * measured past the handler itself.
 */
#[AsHookHandler]
final class DependentHandler {

	/**
	 * What the handler was called with, in order.
	 *
	 * @var array<int, string>
	 */
	public static array $calls = array();

	/**
	 * Takes the dependency the container has to build for it.
	 *
	 * @param Dependency $dependency The service this handler needs.
	 * @return void
	 */
	public function __construct( private Dependency $dependency ) {
	}

	/**
	 * Puts the record back to nothing.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$calls = array();
	}

	/**
	 * Answers to an action, using what it was given.
	 *
	 * @return void
	 */
	#[AsAction( 'dependent_action' )]
	private function onAction(): void {
		self::$calls[] = $this->dependency->name();
	}
}
