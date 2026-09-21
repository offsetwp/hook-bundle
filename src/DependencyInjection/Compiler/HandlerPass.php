<?php
/**
 * OffsetWP Hook Bundle
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\DependencyInjection\Compiler
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\HookBundle\DependencyInjection\Compiler;

use OffsetWP\Bundle\HookBundle\Attribute\AsAction;
use OffsetWP\Bundle\HookBundle\Attribute\AsFilter;
use OffsetWP\Bundle\HookBundle\Attribute\AsHookHandler;
use OffsetWP\Bundle\HookBundle\Attribute\AsShortCode;
use OffsetWP\Bundle\HookBundle\HookRegistrar;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Reads every handler out of the marked classes, and hands the registrar a list.
 *
 * Only the marked classes are read. The container scans public methods when it looks for
 * attributes, so a hook declared on a private method is invisible to it — and this pass
 * exists because a private handler is the shape the package is built for. The class
 * attribute is what the container can see, and the tag it leaves is what this pass
 * collects: a handful of classes rather than every definition in the container, which
 * is recompiled on every request.
 *
 * What a class carrying no mark costs is a silence, so half of that silence is taken
 * away: the three method attributes are autoconfigured as well, purely so that a class
 * with a public handler and no mark on it fails here, by name, rather than doing nothing.
 *
 * The pass runs in the before-optimization phase at the default priority, which puts it
 * after the library's own tag resolution: by the time it reads a tag, the classes are
 * resolved and everything autoconfiguration had to add has been added. It also puts it
 * before child definitions are resolved, which is why reading the class of a definition
 * is more than a call to getClass().
 *
 * @phpstan-import-type Handler from HookRegistrar
 */
final class HandlerPass implements CompilerPassInterface {

	/**
	 * The tag a class whose methods declare hooks carries.
	 *
	 * Applied by autoconfiguration from the class attribute, and writable by hand for a
	 * service that cannot carry the attribute.
	 *
	 * @var string
	 */
	public const HANDLER_TAG = 'hook.handler';

	/**
	 * The tag a class carrying a hook attribute on a public method receives.
	 *
	 * Applied by autoconfiguration and never written by a project: the only thing it is
	 * read for is the failure that names a class which forgot the class attribute.
	 *
	 * @var string
	 */
	public const ATTRIBUTED_METHOD_TAG = 'hook.attributed_method';

	/**
	 * The tag the definition this bundle owns carries.
	 *
	 * @var string
	 */
	public const OWNED_TAG = 'hook.owned';

	/**
	 * The three attributes a method can declare a hook with.
	 *
	 * @var array<int, class-string>
	 */
	public const ATTRIBUTES = array( AsAction::class, AsFilter::class, AsShortCode::class );

	/**
	 * {@inheritDoc}
	 *
	 * Every other refusal this pass can produce is raised by one of the methods below,
	 * each of which documents its own.
	 *
	 * @param ContainerBuilder $container The service container.
	 * @throws \InvalidArgumentException When a marked class declares no hook at all.
	 * @return void
	 */
	public function process( ContainerBuilder $container ): void {
		$registrar = $this->registrar( $container );

		$this->assertEveryAttributedClassIsMarked( $container );

		$handlers = array();
		$services = array();

		foreach ( $this->markedServices( $container ) as $id => $reflection ) {
			$found = $this->handlersOf( $reflection, $id );

			if ( array() === $found ) {
				throw new \InvalidArgumentException(
					sprintf(
						'The class "%s" is marked as a hook handler but declares no hook. Add #[%s], #[%s] or #[%s] to one of its methods, or take the mark off the class.',
						$reflection->getName(),
						$this->shortName( AsAction::class ),
						$this->shortName( AsFilter::class ),
						$this->shortName( AsShortCode::class )
					)
				);
			}

			foreach ( $found as $handler ) {
				$handlers[] = $handler;

				if ( ! $handler['is_static'] ) {
					$services[ $id ] = new Reference( $id );
				}
			}
		}

		$registrar->setArgument( 0, $handlers );
		$registrar->setArgument( 1, ServiceLocatorTagPass::register( $container, $services ) );
	}

	/**
	 * The definition this bundle owns, or a refusal naming what took its place.
	 *
	 * Both ways of taking the id are refused here, and the second one is why this is not
	 * an early return. A project defining a service under the id leaves a definition
	 * without this bundle's tag on it. A project aliasing the id removes the definition
	 * altogether, and every hook in the project would then be collected into nothing —
	 * silently, because the service the bundle boots is still a registrar and still
	 * answers. The pass is only ever added by this bundle, whose extension the kernel
	 * always loads, so there is no arrangement in which the definition is legitimately
	 * absent.
	 *
	 * @param ContainerBuilder $container The service container.
	 * @throws \LogicException When the definition has been replaced or aliased.
	 * @return Definition
	 */
	private function registrar( ContainerBuilder $container ): Definition {
		if ( $container->hasDefinition( HookRegistrar::SERVICE_ID ) ) {
			$registrar = $container->getDefinition( HookRegistrar::SERVICE_ID );

			if ( $registrar->hasTag( self::OWNED_TAG ) ) {
				return $registrar;
			}
		}

		throw new \LogicException(
			sprintf(
				'The service "%s" is defined or aliased by this project as well as by the hook bundle, and the project\'s own is the one that survives — with every hook the bundle had collected for it. Remove it from your config/services.php, or register your own service under an id of your own.',
				HookRegistrar::SERVICE_ID
			)
		);
	}

	/**
	 * Every marked service, with the reflection of the class it will be built from.
	 *
	 * One entry per service, never one per tag occurrence: a class can end up carrying
	 * the mark twice through no fault of its own, once from its attribute and once from
	 * a tag written by hand, and counted twice every hook it declares would be
	 * registered twice.
	 *
	 * An abstract definition is refused by the library as it collects, by name: a
	 * definition that cannot be instantiated cannot be referenced either, and left alone
	 * the failure would name the registrar rather than the service that carries the tag.
	 * A definition the library excluded from a namespace load is dropped by the same
	 * call, which is what excluding one means, so nothing here has to check for it.
	 *
	 * @param ContainerBuilder $container The service container.
	 * @throws \InvalidArgumentException When a marked service has no class, a class that cannot be read, or is decorated.
	 * @return array<string, \ReflectionClass<object>>
	 */
	private function markedServices( ContainerBuilder $container ): array {
		$marked = array();
		$tagged = array_keys( $container->findTaggedServiceIds( self::HANDLER_TAG, true ) );

		if ( array() === $tagged ) {
			return $marked;
		}

		$decorated = $this->decoratedServices( $container );

		foreach ( $tagged as $id ) {
			if ( isset( $decorated[ $id ] ) ) {
				throw new \InvalidArgumentException(
					sprintf(
						'The service "%s" is marked as a hook handler and is decorated by "%s". A decorated id resolves to its decorator, so the hooks of "%s" would be looked for on an object that is not one. Mark the decorator instead, or decorate something else.',
						$id,
						$decorated[ $id ],
						$id
					)
				);
			}

			$class      = $this->classOf( $container, $container->getDefinition( $id ) );
			$reflection = '' === $class ? null : $container->getReflectionClass( $class, false );

			if ( null === $reflection ) {
				throw new \InvalidArgumentException(
					sprintf(
						'The service "%s" is marked as a hook handler, and its class %s. A hook is declared on a method, so there has to be a class to read the methods of.',
						$id,
						'' === $class ? 'cannot be worked out: the service is built by a factory, is synthetic, or inherits from a parent that is not a definition' : sprintf( '"%s" cannot be loaded', $class )
					)
				);
			}

			$marked[ $id ] = $reflection;
		}

		return $marked;
	}

	/**
	 * Which service ids are decorated, and by what.
	 *
	 * Read here rather than later because later is too late: the pass that resolves a
	 * decoration turns the decorated id into an alias of its decorator, and it runs after
	 * this one. From that point on, asking the container for the handler's id hands back
	 * the decorator — which is what the closure bound to the handler's class would then
	 * be given, at the first hook that fires.
	 *
	 * @param ContainerBuilder $container The service container.
	 * @return array<string, string>
	 */
	private function decoratedServices( ContainerBuilder $container ): array {
		$decorated = array();

		foreach ( $container->getDefinitions() as $id => $definition ) {
			$decorates = $definition->getDecoratedService();
			$inner     = is_array( $decorates ) ? ( $decorates[0] ?? null ) : null;

			if ( is_string( $inner ) ) {
				$decorated[ $inner ] = $id;
			}
		}

		return $decorated;
	}

	/**
	 * Refuses a class that declares a hook on a public method and is not marked.
	 *
	 * This is the half of the silence that can be taken away. A class whose handlers are
	 * all private cannot be found without its mark, and nothing here can know it exists;
	 * one with a public handler is found by autoconfiguration, and is told.
	 *
	 * @param ContainerBuilder $container The service container.
	 * @throws \InvalidArgumentException When an attributed class carries no mark.
	 * @return void
	 */
	private function assertEveryAttributedClassIsMarked( ContainerBuilder $container ): void {
		foreach ( $container->findTaggedServiceIds( self::ATTRIBUTED_METHOD_TAG, true ) as $id => $tags ) {
			$definition = $container->getDefinition( $id );

			if ( $definition->hasTag( self::HANDLER_TAG ) ) {
				continue;
			}

			$attributes = is_array( $tags[0] ?? null ) ? $tags[0] : array();
			$method     = is_string( $attributes['method'] ?? null ) ? $attributes['method'] : 'a method';
			$attribute  = is_string( $attributes['attribute'] ?? null ) ? $attributes['attribute'] : 'a hook attribute';

			throw new \InvalidArgumentException(
				sprintf(
					'"%s::%s()" carries #[%s], but its class is not marked as a hook handler and nothing will register it. Add #[%s] to the class, or tag the service "%s" with "%s".',
					$this->classOf( $container, $definition ),
					$method,
					$attribute,
					$this->shortName( AsHookHandler::class ),
					$id,
					self::HANDLER_TAG
				)
			);
		}
	}

	/**
	 * Every hook one marked class declares, in the order they are written.
	 *
	 * Every method is read, whatever its visibility, which is the whole reason this pass
	 * exists. The methods PHP does not let a class inherit are not among them, and a
	 * method that cannot be called — abstract, or a constructor — is skipped.
	 *
	 * @param \ReflectionClass $reflection The class to read.
	 * @phpstan-param \ReflectionClass<object> $reflection
	 * @param string           $id         The service it is registered under.
	 * @throws \InvalidArgumentException When a declaration cannot work.
	 * @return list<array<string, mixed>>
	 * @phpstan-return list<Handler>
	 */
	private function handlersOf( \ReflectionClass $reflection, string $id ): array {
		$handlers = array();

		foreach ( $reflection->getMethods() as $method ) {
			if ( $method->isConstructor() || $method->isDestructor() || $method->isAbstract() ) {
				continue;
			}

			$declared = array_values(
				array_filter(
					$method->getAttributes(),
					static fn ( \ReflectionAttribute $attribute ): bool => in_array( $attribute->getName(), self::ATTRIBUTES, true )
				)
			);

			if ( array() === $declared ) {
				continue;
			}

			$this->assertNoParameterIsByReference( $method );

			foreach ( $declared as $attribute ) {
				$handlers[] = $this->handlerOf( $attribute->newInstance(), $method, $reflection, $id );
			}
		}

		return $handlers;
	}

	/**
	 * One declaration turned into the record the registrar reads.
	 *
	 * @param object            $attribute  The attribute instance that was found.
	 * @param \ReflectionMethod $method     The method carrying it.
	 * @param \ReflectionClass  $reflection The class the method belongs to.
	 * @phpstan-param \ReflectionClass<object> $reflection
	 * @param string            $id         The service it is registered under.
	 * Everything a declaration is checked against is checked by the methods this calls,
	 * and each of them documents what it refuses.
	 *
	 * @throws \LogicException When the attribute is none of the three, which nothing produces.
	 * @return array<string, mixed>
	 * @phpstan-return Handler
	 */
	private function handlerOf( object $attribute, \ReflectionMethod $method, \ReflectionClass $reflection, string $id ): array {
		if ( $attribute instanceof AsShortCode ) {
			$this->assertHookIsNamed( $attribute->name, $method, AsShortCode::class );
			$this->assertShortCodeTagIsUsable( $attribute->name, $method );
			$this->assertMethodReturnsSomething( $attribute->name, $method, AsShortCode::class );

			return $this->record( HookRegistrar::TYPE_SHORT_CODE, $attribute->name, 10, $method->getNumberOfParameters(), $method, $reflection, $id );
		}

		if ( $attribute instanceof AsFilter ) {
			$this->assertHookIsNamed( $attribute->name, $method, AsFilter::class );
			$this->assertMethodReturnsSomething( $attribute->name, $method, AsFilter::class );
			$this->assertFilterTakesTheValue( $attribute->name, $method );

			return $this->record(
				HookRegistrar::TYPE_FILTER,
				$attribute->name,
				$attribute->priority,
				$this->acceptedArgs( $attribute->accepted_args, $attribute->name, $method, AsFilter::class ),
				$method,
				$reflection,
				$id
			);
		}

		if ( $attribute instanceof AsAction ) {
			$this->assertHookIsNamed( $attribute->name, $method, AsAction::class );

			return $this->record(
				HookRegistrar::TYPE_ACTION,
				$attribute->name,
				$attribute->priority,
				$this->acceptedArgs( $attribute->accepted_args, $attribute->name, $method, AsAction::class ),
				$method,
				$reflection,
				$id
			);
		}

		/*
		 * Unreachable: the caller filtered the attributes down to the three above. It is
		 * written all the same, because a fourth attribute added to the list and
		 * forgotten here would otherwise drop every declaration made with it without a
		 * word — which is the one thing this pass exists to make impossible.
		 */
		throw new \LogicException(
			sprintf( '"%s" carries #[%s], which this bundle knows the name of and does not know what to do with.', $this->describe( $method ), $this->shortName( $attribute::class ) )
		);
	}

	/**
	 * The record itself.
	 *
	 * The hook name has its percent signs doubled. The container walks every argument of
	 * every definition and reads "%name%" as one of its own parameters, and a lone
	 * percent sign is not something a hook name should have to avoid. What is written
	 * here is what the container hands back once it has resolved it.
	 *
	 * @param string            $type          The kind of hook.
	 * @param string            $hook          The hook name or shortcode tag.
	 * @param int               $priority      Lower runs first.
	 * @param int               $accepted_args How many arguments the method receives.
	 * @param \ReflectionMethod $method        The method that answers to it.
	 * @param \ReflectionClass  $reflection    The class the method belongs to.
	 * @phpstan-param \ReflectionClass<object> $reflection
	 * @param string            $id            The service it is registered under.
	 * @return array<string, mixed>
	 * @phpstan-return Handler
	 */
	private function record( string $type, string $hook, int $priority, int $accepted_args, \ReflectionMethod $method, \ReflectionClass $reflection, string $id ): array {
		return array(
			'type'          => $type,
			'hook'          => str_replace( '%', '%%', $hook ),
			'service'       => $id,
			'class'         => $reflection->getName(),
			'method'        => $method->getName(),
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
			'is_static'     => $method->isStatic(),
		);
	}

	/**
	 * How many arguments a handler receives: what it was told, or what its signature says.
	 *
	 * A variadic method is refused rather than measured. Counting its parameters answers
	 * a different question from the one the platform asks, and the answer it gives — one,
	 * for a method written to take whatever comes — is wrong in the quiet direction.
	 *
	 * @param int|null          $declared  What the attribute said, when it said anything.
	 * @param string            $hook      The hook being declared.
	 * @param \ReflectionMethod $method    The method carrying the attribute.
	 * @param class-string      $attribute The attribute it was declared with.
	 * @throws \InvalidArgumentException When the count is negative, or cannot be worked out.
	 * @return int
	 */
	private function acceptedArgs( ?int $declared, string $hook, \ReflectionMethod $method, string $attribute ): int {
		if ( null !== $declared ) {
			if ( $declared < 0 ) {
				throw new \InvalidArgumentException(
					sprintf(
						'"%s" declares "%s" with %d accepted arguments. A count of arguments is not negative.',
						$this->describe( $method ),
						$hook,
						$declared
					)
				);
			}

			/*
			 * Nought is a real count for an action and an impossible one for a filter: the
			 * platform reads it as "call this with nothing at all", and a filter always has
			 * the value it filters to be handed. Left alone, the container builds and the
			 * first apply_filters() dies on an argument count.
			 */
			if ( AsFilter::class === $attribute && 0 === $declared ) {
				throw new \InvalidArgumentException(
					sprintf(
						'"%s" declares the filter "%s" with 0 accepted arguments, and the platform reads that as calling it with none. A filter is handed the value it filters, so it accepts at least one.',
						$this->describe( $method ),
						$hook
					)
				);
			}

			return $declared;
		}

		if ( $method->isVariadic() ) {
			throw new \InvalidArgumentException(
				sprintf(
					'"%s" declares "%s" and is variadic, so how many arguments it wants cannot be read off its signature. Write it: #[%s( \'%s\', accepted_args: 2 )].',
					$this->describe( $method ),
					$hook,
					$this->shortName( $attribute ),
					$hook
				)
			);
		}

		return $method->getNumberOfParameters();
	}

	/**
	 * Refuses a handler taking a parameter by reference, which cannot be honoured.
	 *
	 * The platform's by-reference forms hand an array of references to the callback, and
	 * what this bundle hands the platform is a closure that collects its arguments in a
	 * variadic. A variadic is by value, so the chain is broken there: the handler runs,
	 * changes a copy, and whatever fired the hook sees none of it. A wrong answer with
	 * nothing said is worse than a build that stops, so the build stops.
	 *
	 * @param \ReflectionMethod $method The method carrying the attribute.
	 * @throws \InvalidArgumentException When a parameter is declared by reference.
	 * @return void
	 */
	private function assertNoParameterIsByReference( \ReflectionMethod $method ): void {
		foreach ( $method->getParameters() as $parameter ) {
			if ( ! $parameter->isPassedByReference() ) {
				continue;
			}

			throw new \InvalidArgumentException(
				sprintf(
					'"%s" declares a hook and takes $%s by reference. A handler receives its arguments by value, so the reference would be dropped in silence and whatever fired the hook would see none of the changes. Take the value, and return it from a filter.',
					$this->describe( $method ),
					$parameter->getName()
				)
			);
		}
	}

	/**
	 * Refuses a shortcode tag the platform would drop.
	 *
	 * The platform refuses a tag carrying a space or one of its reserved characters, and
	 * refuses it by doing nothing: a notice goes to whatever is listening and the
	 * shortcode is simply not registered. The same tag is refused here instead, where
	 * there is something to name.
	 *
	 * @param string            $tag    The declared tag.
	 * @param \ReflectionMethod $method The method carrying the attribute.
	 * @throws \InvalidArgumentException When the tag carries a character the platform reserves.
	 * @return void
	 */
	private function assertShortCodeTagIsUsable( string $tag, \ReflectionMethod $method ): void {
		if ( 1 !== preg_match( '@[<>&/\[\]\x00-\x20=]@', $tag ) ) {
			return;
		}

		throw new \InvalidArgumentException(
			sprintf(
				'"%s" declares the shortcode "%s", whose tag carries a space or one of the characters the platform reserves: & / < > [ ] =. The platform would drop it without registering it.',
				$this->describe( $method ),
				$tag
			)
		);
	}

	/**
	 * Refuses a hook with no name, which would be registered and never fire.
	 *
	 * @param string            $hook      The declared name.
	 * @param \ReflectionMethod $method    The method carrying the attribute.
	 * @param class-string      $attribute The attribute it was declared with.
	 * @throws \InvalidArgumentException When the name is empty.
	 * @return void
	 */
	private function assertHookIsNamed( string $hook, \ReflectionMethod $method, string $attribute ): void {
		if ( '' !== trim( $hook ) ) {
			return;
		}

		throw new \InvalidArgumentException(
			sprintf( '"%s" carries #[%s] with no name. A hook is found by its name, so it has to have one.', $this->describe( $method ), $this->shortName( $attribute ) )
		);
	}

	/**
	 * Refuses a filter or a shortcode that cannot return anything.
	 *
	 * Both are asked for a value and both replace one, so a method declared void is a
	 * filter that empties what it filters and a shortcode that empties the post it is in.
	 * A method that declares no return type at all is left alone: there is nothing to
	 * read, and the question has no answer here.
	 *
	 * @param string            $hook      The hook being declared.
	 * @param \ReflectionMethod $method    The method carrying the attribute.
	 * @param class-string      $attribute The attribute it was declared with.
	 * @throws \InvalidArgumentException When the method returns void or never.
	 * @return void
	 */
	private function assertMethodReturnsSomething( string $hook, \ReflectionMethod $method, string $attribute ): void {
		$return = $method->getReturnType();

		if ( ! $return instanceof \ReflectionNamedType || ! in_array( $return->getName(), array( 'void', 'never' ), true ) ) {
			return;
		}

		throw new \InvalidArgumentException(
			sprintf(
				'"%s" declares "%s" with #[%s] and returns %s. A %s replaces a value, so it has to return one.',
				$this->describe( $method ),
				$hook,
				$this->shortName( $attribute ),
				$return->getName(),
				AsShortCode::class === $attribute ? 'shortcode' : 'filter'
			)
		);
	}

	/**
	 * Refuses a filter that takes nothing, since a filter is handed a value.
	 *
	 * @param string            $hook   The filter being declared.
	 * @param \ReflectionMethod $method The method carrying the attribute.
	 * @throws \InvalidArgumentException When the method takes no parameter.
	 * @return void
	 */
	private function assertFilterTakesTheValue( string $hook, \ReflectionMethod $method ): void {
		if ( 0 !== $method->getNumberOfParameters() ) {
			return;
		}

		throw new \InvalidArgumentException(
			sprintf(
				'"%s" declares the filter "%s" and takes no parameter. A filter is handed the value it filters, so it has to take at least one.',
				$this->describe( $method ),
				$hook
			)
		);
	}

	/**
	 * The class a tagged definition will actually be built from.
	 *
	 * Two shapes reach this point looking classless when they are not, because both are
	 * settled by passes that run after this one.
	 *
	 * A definition declared with its class in a container parameter still holds the
	 * expression, which names a class that does not exist. A definition declared as the
	 * child of another carries no class at all: the parent's is copied down later.
	 *
	 * An empty string is returned for what is genuinely classless — a factory, a
	 * synthetic service — which is the one case the caller is written for.
	 *
	 * @param ContainerBuilder $container  The service container.
	 * @param Definition       $definition The tagged definition.
	 * @return string
	 */
	private function classOf( ContainerBuilder $container, Definition $definition ): string {
		$seen = array();

		while ( null === $definition->getClass() && $definition instanceof ChildDefinition ) {
			$parent = $definition->getParent();

			if ( isset( $seen[ $parent ] ) || ! $container->hasDefinition( $parent ) ) {
				return '';
			}

			$seen[ $parent ] = true;
			$definition      = $container->getDefinition( $parent );
		}

		$class = $definition->getClass();

		if ( null === $class ) {
			return '';
		}

		$resolved = $container->getParameterBag()->resolveValue( $class );

		return is_string( $resolved ) ? $resolved : '';
	}

	/**
	 * A method written the way a failure should name it.
	 *
	 * @param \ReflectionMethod $method The method to name.
	 * @return string
	 */
	private function describe( \ReflectionMethod $method ): string {
		return sprintf( '%s::%s()', $method->getDeclaringClass()->getName(), $method->getName() );
	}

	/**
	 * An attribute named the way it is written.
	 *
	 * @param class-string $attribute The attribute class.
	 * @return string
	 */
	private function shortName( string $attribute ): string {
		return ( new \ReflectionClass( $attribute ) )->getShortName();
	}
}
