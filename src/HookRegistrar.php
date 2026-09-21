<?php
/**
 * OffsetWP Hook Bundle
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\HookBundle;

use Psr\Container\ContainerInterface;

/**
 * Hands every declared handler to the platform, and builds none of them.
 *
 * This is where the laziness of the package comes from. What the platform receives is a
 * closure, and what the closure holds is a service id: the service is referenced, so the
 * container never removes it, and it is constructed by the first call that actually
 * reaches it. A handler whose hook never fires costs its entry in this list and nothing
 * else — no constructor, no dependencies of that constructor, no autoloading of either.
 *
 * A static handler does not even cost that: it is called on the class and never touches
 * the locator, so its service is never constructed at all.
 *
 * Reaching a private method is what the bound closures do. Binding is what costs, so it
 * happens once per class, here, at registration — not once per call, and not on every
 * hook that fires.
 *
 * @phpstan-type Handler array{type: string, hook: string, service: string, class: class-string, method: string, priority: int, accepted_args: int, is_static: bool}
 */
final class HookRegistrar {

	/**
	 * The service id this bundle registers itself under.
	 *
	 * Prefixed rather than named after the class, and that is not cosmetic: the library
	 * captures a project's own definitions before it merges a bundle's and restores them
	 * afterwards, so a definition registered under an id the project already uses is
	 * discarded without a word.
	 *
	 * @var string
	 */
	public const SERVICE_ID = 'hook.registrar';

	/**
	 * What a handler declared with the action attribute is recorded as.
	 *
	 * @var string
	 */
	public const TYPE_ACTION = 'action';

	/**
	 * What a handler declared with the filter attribute is recorded as.
	 *
	 * @var string
	 */
	public const TYPE_FILTER = 'filter';

	/**
	 * What a handler declared with the shortcode attribute is recorded as.
	 *
	 * @var string
	 */
	public const TYPE_SHORT_CODE = 'shortcode';

	/**
	 * Whether the handlers have already been handed over.
	 *
	 * @var bool
	 */
	private bool $is_registered = false;

	/**
	 * One closure per handler class, bound to that class once.
	 *
	 * @var array<string, \Closure>
	 */
	private array $invokers = array();

	/**
	 * The handlers, and the locator the non-static ones are resolved through.
	 *
	 * Both arguments carry a default, and neither default is ever the one used: the
	 * compiler pass of this bundle sets both, and it runs whenever the bundle is
	 * registered — which is the only way this service exists at all.
	 *
	 * @param array<int, mixed>       $handlers        Every declared handler, in the order they were found.
	 * @param ContainerInterface|null $handler_locator The locator holding the service of each non-static handler.
	 * @phpstan-param list<Handler> $handlers
	 * @return void
	 */
	public function __construct(
		private array $handlers = array(),
		private ?ContainerInterface $handler_locator = null,
	) {
	}

	/**
	 * Hands every handler to the platform.
	 *
	 * Called once, by the bundle, when the kernel boots. Calling it again does nothing,
	 * so a project that boots two kernels carrying this bundle registers each kernel's
	 * handlers once rather than one kernel's twice. Two kernels carrying the *same*
	 * handlers do register them twice, and the platform runs both: it keys a callback on
	 * the closure it was handed, and each kernel builds its own.
	 *
	 * The flag is set once the work is done and not before, so a registration that failed
	 * half way can be tried again rather than being remembered as one that worked.
	 *
	 * Both functions the platform has to provide are checked, and not only the first of
	 * them: shortcodes are declared two hundred lines later than actions in the platform's
	 * own boot, so a kernel booted from a drop-in can be past one and not the other.
	 *
	 * @throws \LogicException When the platform is not loaded, or a handler cannot be reached.
	 * @return void
	 */
	public function register(): void {
		if ( $this->is_registered ) {
			return;
		}

		foreach ( array( 'add_action', 'add_filter', 'add_shortcode' ) as $platform_function ) {
			if ( function_exists( $platform_function ) ) {
				continue;
			}

			throw new \LogicException(
				sprintf(
					'The hook bundle cannot register anything: %s() does not exist, which means this kernel booted before WordPress finished loading, or outside it altogether. Boot it from a mu-plugin, a plugin or a theme, where the platform is already loaded.',
					$platform_function
				)
			);
		}

		foreach ( $this->handlers as $handler ) {
			$this->hand( $handler, $this->callback( $handler ) );
		}

		$this->is_registered = true;
	}

	/**
	 * Hands one handler to the platform function that takes it.
	 *
	 * @param array<string, mixed> $handler  The handler to register.
	 * @param \Closure             $callback The closure the platform will call.
	 * @phpstan-param Handler $handler
	 * @throws \LogicException When the handler carries a type nothing produces.
	 * @return void
	 */
	private function hand( array $handler, \Closure $callback ): void {
		switch ( $handler['type'] ) {
			case self::TYPE_ACTION:
				\add_action( $handler['hook'], $callback, $handler['priority'], $handler['accepted_args'] );
				break;

			case self::TYPE_FILTER:
				\add_filter( $handler['hook'], $callback, $handler['priority'], $handler['accepted_args'] );
				break;

			case self::TYPE_SHORT_CODE:
				\add_shortcode( $handler['hook'], $callback );
				break;

			default:
				throw new \LogicException(
					sprintf(
						'"%s::%s()" was recorded as a "%s", which is not one of the three kinds of hook this bundle registers.',
						$handler['class'],
						$handler['method'],
						$handler['type']
					)
				);
		}
	}

	/**
	 * The closure the platform calls for one handler.
	 *
	 * Nothing in it resolves anything until the platform calls it. For a static handler
	 * there is nothing to resolve at all.
	 *
	 * The arguments are re-keyed before they are spread. A hook fired through the
	 * platform's by-reference form hands its array over untouched, string keys and all,
	 * and a string key in a spread is a named argument: the handler's parameters would be
	 * bound by name rather than in order, or the call would fail on a parameter name
	 * nobody wrote.
	 *
	 * @param array<string, mixed> $handler The handler to build a closure for.
	 * @phpstan-param Handler $handler
	 * @throws \LogicException When the handler class cannot be bound to, or has no locator.
	 * @return \Closure
	 */
	private function callback( array $handler ): \Closure {
		$handler_class = $handler['class'];
		$method        = $handler['method'];

		if ( $handler['is_static'] ) {
			$invoke = $this->staticInvoker( $handler_class );

			return static fn ( mixed ...$args ): mixed => $invoke( $handler_class, $method, array_values( $args ) );
		}

		$invoke  = $this->instanceInvoker( $handler_class );
		$locator = $this->locator( $handler );
		$service = $handler['service'];

		return static fn ( mixed ...$args ): mixed => $invoke( $locator->get( $service ), $method, array_values( $args ) );
	}

	/**
	 * The closure that calls an instance method of one class.
	 *
	 * Bound to the class rather than to an object, so one closure serves every handler of
	 * that class and every instance of it.
	 *
	 * Visibility stops mattering for anything the bound scope can reach, which is every
	 * method the class declares and every protected one it inherits. A private method of
	 * a parent is not among them — PHP does not let a child inherit one, so it never
	 * reaches this point either.
	 *
	 * @param string $handler_class The class to bind to.
	 * @phpstan-param class-string $handler_class
	 * @throws \LogicException When the class cannot be bound to.
	 * @return \Closure
	 */
	private function instanceInvoker( string $handler_class ): \Closure {
		return $this->invokers[ 'instance:' . $handler_class ] ??= $this->bind(
			static fn ( object $handler, string $method, array $args ): mixed => $handler->{$method}( ...$args ),
			$handler_class
		);
	}

	/**
	 * The closure that calls a static method of one class, under the same rule.
	 *
	 * @param string $handler_class The class to bind to.
	 * @phpstan-param class-string $handler_class
	 * @throws \LogicException When the class cannot be bound to.
	 * @return \Closure
	 */
	private function staticInvoker( string $handler_class ): \Closure {
		return $this->invokers[ 'static:' . $handler_class ] ??= $this->bind(
			static fn ( string $called_class, string $method, array $args ): mixed => $called_class::{$method}( ...$args ),
			$handler_class
		);
	}

	/**
	 * Binds a closure to the scope of a class, so that a private method is in reach.
	 *
	 * Binding to a scope is what a closure reaching a private method needs, and taking a
	 * callable from a private method — rather than binding a closure to its class — is
	 * what does not work from outside that class.
	 *
	 * A class PHP ships cannot be a scope, and it is refused here rather than at the bind
	 * itself: binding to one emits a warning before it returns false, and the warning
	 * reaches the output first — or is promoted to an exception by whatever error handler
	 * the project installed, in place of the sentence below. From PHP 9 it stops being a
	 * warning at all.
	 *
	 * @param \Closure $closure       The closure to bind.
	 * @param string   $handler_class The class whose scope it needs.
	 * @phpstan-param class-string $handler_class
	 * @throws \LogicException When the class cannot be bound to.
	 * @return \Closure
	 */
	private function bind( \Closure $closure, string $handler_class ): \Closure {
		$reflection = class_exists( $handler_class ) ? new \ReflectionClass( $handler_class ) : null;

		if ( null === $reflection || $reflection->isInternal() ) {
			throw new \LogicException(
				sprintf(
					'The handler class "%s" cannot be bound to, so its methods cannot be reached: %s',
					$handler_class,
					null === $reflection ? 'it cannot be loaded.' : 'it is one of PHP\'s own classes, and a closure cannot take the scope of one.'
				)
			);
		}

		$bound = \Closure::bind( $closure, null, $handler_class );

		if ( ! $bound instanceof \Closure ) {
			throw new \LogicException(
				sprintf( 'The handler class "%s" cannot be bound to, so its methods cannot be reached.', $handler_class )
			);
		}

		return $bound;
	}

	/**
	 * The locator a non-static handler is resolved through.
	 *
	 * @param array<string, mixed> $handler The handler being registered.
	 * @phpstan-param Handler $handler
	 * @throws \LogicException When there is no locator to resolve it through.
	 * @return ContainerInterface
	 */
	private function locator( array $handler ): ContainerInterface {
		if ( null === $this->handler_locator ) {
			throw new \LogicException(
				sprintf(
					'"%s::%s()" needs its service, and this registrar was built without a locator to find it in. The service "%s" has been replaced by something this bundle did not build.',
					$handler['class'],
					$handler['method'],
					self::SERVICE_ID
				)
			);
		}

		return $this->handler_locator;
	}
}
