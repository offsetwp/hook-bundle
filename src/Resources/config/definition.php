<?php
/**
 * OffsetWP Hook Bundle
 *
 * The configuration tree of this bundle, rooted at "hook", and empty.
 *
 * Nothing this bundle does is decided anywhere but on the classes of the project, so the
 * tree has no keys and is not going to grow any. What it has instead is a sentence: left
 * to itself, a project writing a key under "hook" is told its option is unrecognised and
 * that the available options are "", which says what happened and nothing about why.
 *
 * The check is a normalisation rather than a validation, and that is deliberate: the
 * kernel loads every registered extension with an empty configuration whether or not a
 * project wrote one, so the empty array has to pass through untouched.
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle
 */

declare( strict_types=1 );

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;

return static function ( DefinitionConfigurator $definition ): void {
	$definition->rootNode()
		->beforeNormalization()
			->ifTrue( static fn ( mixed $config ): bool => is_array( $config ) && array() !== $config )
			->then(
				static fn (): never => throw new \InvalidArgumentException(
					'The "hook" key takes no options. This bundle is configured by the attributes on your own classes and by nothing else, so there is nothing to write here. Delete the file, or the key.'
				)
			)
		->end();
};
