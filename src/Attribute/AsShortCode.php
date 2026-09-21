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
 * Declares that a method is the body of a shortcode.
 *
 * There is no priority and no argument count here, because a shortcode has neither: the
 * platform holds one callback per tag and always hands it three arguments — the
 * attributes, the enclosed content or null, and the tag itself. A method that declares
 * fewer parameters than that simply ignores the rest, which is what PHP does with the
 * extra arguments of any call to a method of its own.
 *
 * A shortcode returns what replaces it in the post, so the method does not return void.
 * That is checked while the container is built, and the failure names the tag, the class
 * and the method.
 *
 * The method may be private, and it may be static. A private one is reached through a
 * closure bound to the class once, when the shortcode is registered; a static one never
 * causes the class to be constructed at all.
 *
 * Repeatable, so one method can be the body of several tags.
 */
#[\Attribute( \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE )]
final class AsShortCode {

	/**
	 * Declares the shortcode.
	 *
	 * @param string $name The shortcode tag, as it is written in a post.
	 * @return void
	 */
	public function __construct(
		public readonly string $name,
	) {
	}
}
