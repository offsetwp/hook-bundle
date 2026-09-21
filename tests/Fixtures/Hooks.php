<?php
/**
 * OffsetWP Hook Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\Tests\Fixtures
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\HookBundle\Tests\Fixtures;

/**
 * The register the platform functions of tests/wordpress.php write to and read from.
 *
 * The functions themselves are three lines each and live in the global namespace, because
 * that is where this bundle calls them. Everything they do happens here, where it is
 * typed, resettable and readable by an assertion.
 *
 * Every rule below is the platform's own, and each one is here because a test elsewhere
 * would otherwise be passing on a simulation kinder than the thing it stands in for: a
 * lower priority runs first; callbacks of one priority run in the order they were added;
 * each one receives only as many arguments as it said it accepts; an action fired with
 * nothing hands over a single empty string; a callback added while a hook is running runs
 * in that same run when its priority has not been passed yet; and actions and filters are
 * counted apart, because the platform counts them apart and answers about them apart.
 *
 * A shortcode's attributes are an array whatever the post said, which is what the platform
 * has handed over since it stopped handing a bare string for a shortcode written with none.
 *
 * One divergence is deliberate, and is written down so that nobody restores it from memory.
 * An action whose single argument is an array holding one object is unwrapped by the
 * platform to that object — backward compatibility for a way of writing PHP that predates
 * everything this package requires. It is not reproduced, because it decides what the
 * platform hands over rather than what this bundle does with it. What the platform does
 * *not* do is clone an object before handing it over, and neither does this.
 */
final class Hooks {

	/**
	 * Every registered callback, by hook name and then by priority.
	 *
	 * @var array<string, array<int, array<int, array{callback: callable, accepted_args: int, via: string}>>>
	 */
	private static array $hooks = array();

	/**
	 * Every registered shortcode callback, by tag.
	 *
	 * @var array<string, callable>
	 */
	private static array $short_codes = array();

	/**
	 * How many times each hook has been dispatched as an action.
	 *
	 * @var array<string, int>
	 */
	private static array $actions_fired = array();

	/**
	 * How many times each hook has been dispatched as a filter.
	 *
	 * @var array<string, int>
	 */
	private static array $filters_applied = array();

	/**
	 * Forgets everything, so that a test starts from nothing.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$hooks           = array();
		self::$short_codes     = array();
		self::$actions_fired   = array();
		self::$filters_applied = array();
	}

	/**
	 * Records one callback against a hook.
	 *
	 * @param string   $hook_name     The hook the callback answers to.
	 * @param callable $callback      The callback.
	 * @param int      $priority      Lower runs first.
	 * @param int      $accepted_args How many arguments the callback receives.
	 * @param string   $via           Which platform function recorded it.
	 * @return void
	 */
	public static function add( string $hook_name, callable $callback, int $priority, int $accepted_args, string $via ): void {
		self::$hooks[ $hook_name ][ $priority ][] = array(
			'callback'      => $callback,
			'accepted_args' => $accepted_args,
			'via'           => $via,
		);
	}

	/**
	 * Records one shortcode callback against a tag.
	 *
	 * @param string   $tag      The shortcode tag.
	 * @param callable $callback The callback.
	 * @return void
	 */
	public static function addShortCode( string $tag, callable $callback ): void {
		self::$short_codes[ $tag ] = $callback;
	}

	/**
	 * Runs every callback of a hook, threading a value through them.
	 *
	 * @param string                  $hook_name The hook to run.
	 * @param mixed                   $value     The value handed to the first callback.
	 * @param array<array-key, mixed> $args      The arguments that follow the value.
	 * @return mixed The value the last callback returned.
	 */
	public static function apply( string $hook_name, mixed $value, array $args = array() ): mixed {
		self::$filters_applied[ $hook_name ] = self::filtered( $hook_name ) + 1;

		foreach ( self::walk( $hook_name ) as $registration ) {
			$value = ( $registration['callback'] )(
				...array_slice( array_merge( array( $value ), $args ), 0, $registration['accepted_args'] )
			);
		}

		return $value;
	}

	/**
	 * Runs every callback of a hook, keeping nothing they return.
	 *
	 * An empty argument list becomes a single empty string, which is what the platform hands
	 * a callback registered against an action that carries no arguments.
	 *
	 * @param string                  $hook_name The hook to run.
	 * @param array<array-key, mixed> $args      The arguments to pass.
	 * @return void
	 */
	public static function fire( string $hook_name, array $args = array() ): void {
		self::$actions_fired[ $hook_name ] = self::fired( $hook_name ) + 1;

		if ( array() === $args ) {
			$args = array( '' );
		}

		foreach ( self::walk( $hook_name ) as $registration ) {
			( $registration['callback'] )( ...array_slice( $args, 0, $registration['accepted_args'] ) );
		}
	}

	/**
	 * Runs one registered shortcode the way the platform runs it, with the three arguments
	 * it always passes.
	 *
	 * Parsing the shortcode out of a post is the platform's own work and is not reproduced
	 * here: what this bundle registers is a callback, and this calls it.
	 *
	 * @param string                $tag     The shortcode tag.
	 * @param array<string, string> $atts    The attributes written in the post.
	 * @param string|null           $content The enclosed content, or null when there is none.
	 * @throws \LogicException When no shortcode is registered under that tag.
	 * @return mixed
	 */
	public static function runShortCode( string $tag, array $atts = array(), ?string $content = null ): mixed {
		if ( ! isset( self::$short_codes[ $tag ] ) ) {
			throw new \LogicException( sprintf( 'No shortcode is registered under the tag "%s".', $tag ) );
		}

		return ( self::$short_codes[ $tag ] )( $atts, $content, $tag );
	}

	/**
	 * Whether anything is registered against a hook.
	 *
	 * @param string $hook_name The hook to look for.
	 * @return bool
	 */
	public static function has( string $hook_name ): bool {
		return isset( self::$hooks[ $hook_name ] );
	}

	/**
	 * Whether a shortcode is registered under a tag.
	 *
	 * @param string $tag The tag to look for.
	 * @return bool
	 */
	public static function hasShortCode( string $tag ): bool {
		return isset( self::$short_codes[ $tag ] );
	}

	/**
	 * How many times a hook has been dispatched as an action.
	 *
	 * @param string $hook_name The hook to count.
	 * @return int
	 */
	public static function fired( string $hook_name ): int {
		return self::$actions_fired[ $hook_name ] ?? 0;
	}

	/**
	 * How many times a hook has been dispatched as a filter.
	 *
	 * @param string $hook_name The hook to count.
	 * @return int
	 */
	public static function filtered( string $hook_name ): int {
		return self::$filters_applied[ $hook_name ] ?? 0;
	}

	/**
	 * How many callbacks answer to a hook, whatever their priority.
	 *
	 * @param string $hook_name The hook to count.
	 * @return int
	 */
	public static function callbackCount( string $hook_name ): int {
		return count( self::registrations( $hook_name ) );
	}

	/**
	 * The priority every callback of a hook was registered at, in dispatch order.
	 *
	 * @param string $hook_name The hook to read.
	 * @return list<int>
	 */
	public static function priorities( string $hook_name ): array {
		$priorities = array_keys( self::$hooks[ $hook_name ] ?? array() );

		sort( $priorities );

		return $priorities;
	}

	/**
	 * How many arguments each callback of a hook accepts, in dispatch order.
	 *
	 * @param string $hook_name The hook to read.
	 * @return list<int>
	 */
	public static function acceptedArgs( string $hook_name ): array {
		return array_map(
			static fn ( array $registration ): int => $registration['accepted_args'],
			self::registrations( $hook_name )
		);
	}

	/**
	 * Which platform function recorded each callback of a hook, in dispatch order.
	 *
	 * The two functions do the same thing, so nothing else in this register can tell an
	 * action from a filter — and without this, handing an action to add_filter() and a
	 * filter to add_action() would be invisible to every test.
	 *
	 * @param string $hook_name The hook to read.
	 * @return list<string>
	 */
	public static function addedVia( string $hook_name ): array {
		return array_map(
			static fn ( array $registration ): string => $registration['via'],
			self::registrations( $hook_name )
		);
	}

	/**
	 * Every callback of a hook, as the register stands, in the order it would run them.
	 *
	 * @param string $hook_name The hook to read.
	 * @return list<array{callback: callable, accepted_args: int, via: string}>
	 */
	private static function registrations( string $hook_name ): array {
		$by_priority = self::$hooks[ $hook_name ] ?? array();

		ksort( $by_priority );

		return array_merge( ...array_values( $by_priority ) );
	}

	/**
	 * Every callback of a hook, read one at a time as the hook runs.
	 *
	 * The register is read again before each call rather than copied once at the start,
	 * because a callback is allowed to add another while it runs — which is what happens
	 * when a kernel boots from inside a hook. One added at a priority that has not been
	 * reached yet runs in this same dispatch; one added behind the priority being served
	 * does not, and waits for the next.
	 *
	 * @param string $hook_name The hook being dispatched.
	 * @return \Generator<int, array{callback: callable, accepted_args: int, via: string}>
	 */
	private static function walk( string $hook_name ): \Generator {
		$served  = array();
		$reached = null;

		while ( true ) {
			$next = self::next( $hook_name, $served, $reached );

			if ( null === $next ) {
				return;
			}

			list( $priority, $index ) = $next;

			$reached                            = $priority;
			$served[ $priority . ':' . $index ] = true;

			yield self::$hooks[ $hook_name ][ $priority ][ $index ];
		}
	}

	/**
	 * The next callback a running hook has not served yet, at or after the priority it has
	 * reached.
	 *
	 * @param string              $hook_name The hook being dispatched.
	 * @param array<string, bool> $served    What has already run, by priority and position.
	 * @param int|null            $reached   The priority being served, or null before the first.
	 * @return array{0: int, 1: int}|null
	 */
	private static function next( string $hook_name, array $served, ?int $reached ): ?array {
		$by_priority = self::$hooks[ $hook_name ] ?? array();

		ksort( $by_priority );

		foreach ( $by_priority as $priority => $registrations ) {
			if ( null !== $reached && $priority < $reached ) {
				continue;
			}

			foreach ( array_keys( $registrations ) as $index ) {
				if ( ! isset( $served[ $priority . ':' . $index ] ) ) {
					return array( $priority, $index );
				}
			}
		}

		return null;
	}
}
