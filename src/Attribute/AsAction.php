<?php
/**
 * OffsetWP Hook Bundle
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\Attribute
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\HookBundle\Attribute;

/**
 * Declares that a method answers to an action.
 *
 * The method may be private, and it may be static. A private one is reached through a
 * closure bound to the class once, when the hook is registered; a static one never
 * causes the class to be constructed at all.
 *
 * Repeatable, so one method can answer to several actions.
 */
#[\Attribute( \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE )]
final class AsAction {

	/**
	 * Declares the action.
	 *
	 * @param string   $name          The name of the action.
	 * @param int      $priority      Lower runs first. The platform runs two handlers of one action at one priority in the order it received them, and the order this bundle hands them over in is not one it guarantees.
	 * @param int|null $accepted_args How many arguments the method receives. Null, the default, reads it off the signature.
	 * @return void
	 */
	public function __construct(
		public readonly string $name,
		public readonly int $priority = 10,
		public readonly ?int $accepted_args = null,
	) {
	}
}
