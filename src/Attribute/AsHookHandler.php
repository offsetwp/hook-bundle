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
 * Marks a class as one whose methods declare hooks.
 *
 * It carries nothing and is never read for its own sake: what it does is make the class
 * findable. The container scans public methods only when it looks for attributes, so a
 * hook declared on a private method is invisible to it — and a private method is the
 * point, since a hook handler has no reason to be callable from the rest of a project.
 * This attribute is on the class, where the container does look, and the bundle reads
 * every method of the classes that carry it, whatever their visibility.
 *
 * A service that cannot carry it — autoconfiguration turned off, a class from a package
 * you do not own — is reached with the "hook.handler" tag instead, which does the same
 * thing and is what this attribute puts on the service.
 */
#[\Attribute( \Attribute::TARGET_CLASS )]
final class AsHookHandler {
}
