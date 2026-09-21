<?php
/**
 * OffsetWP Hook Bundle
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\PHPStan
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\HookBundle\PHPStan;

use OffsetWP\Bundle\HookBundle\DependencyInjection\Compiler\HandlerPass;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Rules\Methods\AlwaysUsedMethodExtension;

/**
 * Tells the static analyser that a method carrying one of the three attributes is called.
 *
 * Nothing in PHP calls a hook handler: the platform does, through a closure this bundle
 * built. A private handler therefore reads as dead code, and the analyser reports it as
 * unused from its fourth level up — on every handler, in every project. The report is
 * correct about what it can see and wrong about the program, and the answer to that is an
 * extension rather than an ignore.
 *
 * It ships with the bundle because the problem does. A project that analyses its own code
 * includes extension.neon, at the root of this package, and needs nothing else.
 *
 * The list it answers against is the compiler pass's own, and there is no test below it:
 * building one of the analyser's reflections is outside the promise the analyser makes
 * about its own classes, and the only way around that is the kind of suppression this
 * repository refuses. What it would have asserted is held two other ways instead. That it
 * says yes to a handler is exercised on every run of `composer ci`, which analyses this
 * package's own fixtures — every one of them a class of private handlers — at the level
 * that reports them. That it says no to everything else is true by construction: the one
 * thing it looks at is whether a name is in that list.
 */
final class HandlerMethodExtension implements AlwaysUsedMethodExtension {

	/**
	 * {@inheritDoc}
	 *
	 * @param ExtendedMethodReflection $method_reflection The method being judged.
	 * @return bool
	 */
	public function isAlwaysUsed( ExtendedMethodReflection $method_reflection ): bool {
		foreach ( $method_reflection->getAttributes() as $attribute ) {
			if ( in_array( $attribute->getName(), HandlerPass::ATTRIBUTES, true ) ) {
				return true;
			}
		}

		return false;
	}
}
