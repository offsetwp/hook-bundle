<?php
/**
 * OffsetWP Hook Bundle Tests
 *
 * The platform functions this bundle calls, and the ones a test needs in order to fire
 * a hook. A test process has none of them, and there is no second implementation of the
 * bundle for tests to run against: src/ calls add_action(), add_filter() and
 * add_shortcode() exactly as it does in a request, and these are what answer.
 *
 * Each one is a line of delegation to the register in Fixtures\Hooks, which is where the
 * behaviour, the types and the reset live. The analyser reads this file too, so it is
 * also where the signatures of those functions come from.
 *
 * Every declaration is guarded, so that a process which does have the platform loaded
 * keeps the platform's own.
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\Tests
 */

declare( strict_types=1 );

use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Hooks;

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Adds a callback to an action.
	 *
	 * @param string   $hook_name     The action to add the callback to.
	 * @param callable $callback      The callback to run.
	 * @param int      $priority      Lower runs first.
	 * @param int      $accepted_args How many arguments the callback receives.
	 * @return bool
	 */
	function add_action( string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		Hooks::add( $hook_name, $callback, $priority, $accepted_args, 'add_action' );

		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Adds a callback to a filter.
	 *
	 * @param string   $hook_name     The filter to add the callback to.
	 * @param callable $callback      The callback to run.
	 * @param int      $priority      Lower runs first.
	 * @param int      $accepted_args How many arguments the callback receives.
	 * @return bool
	 */
	function add_filter( string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		Hooks::add( $hook_name, $callback, $priority, $accepted_args, 'add_filter' );

		return true;
	}
}

if ( ! function_exists( 'add_shortcode' ) ) {
	/**
	 * Adds a shortcode.
	 *
	 * @param string   $tag      The shortcode tag.
	 * @param callable $callback The callback to run.
	 * @return void
	 */
	function add_shortcode( string $tag, callable $callback ): void {
		Hooks::addShortCode( $tag, $callback );
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * Runs every callback of an action.
	 *
	 * @param string $hook_name The action to run.
	 * @param mixed  ...$arg    The arguments to pass.
	 * @return void
	 */
	function do_action( string $hook_name, mixed ...$arg ): void {
		Hooks::fire( $hook_name, array_values( $arg ) );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Runs every callback of a filter, threading a value through them.
	 *
	 * @param string $hook_name The filter to run.
	 * @param mixed  $value     The value to filter.
	 * @param mixed  ...$args   The arguments that follow the value.
	 * @return mixed
	 */
	function apply_filters( string $hook_name, mixed $value, mixed ...$args ): mixed {
		return Hooks::apply( $hook_name, $value, array_values( $args ) );
	}
}

if ( ! function_exists( 'do_action_ref_array' ) ) {
	/**
	 * Runs every callback of an action, with the arguments held in an array.
	 *
	 * The array is handed over as it stands, keys and all, which is the whole reason this
	 * form is declared here: it is the one that can carry a string key into a call.
	 *
	 * @param string                  $hook_name The action to run.
	 * @param array<array-key, mixed> $args      The arguments to pass, keys and all.
	 * @return void
	 */
	function do_action_ref_array( string $hook_name, array $args ): void {
		Hooks::fire( $hook_name, $args );
	}
}

if ( ! function_exists( 'apply_filters_ref_array' ) ) {
	/**
	 * Runs every callback of a filter, with the arguments held in an array.
	 *
	 * @param string                  $hook_name The filter to run.
	 * @param array<array-key, mixed> $args      The value to filter, then what follows it.
	 * @return mixed
	 */
	function apply_filters_ref_array( string $hook_name, array $args ): mixed {
		$value = array_shift( $args );

		return Hooks::apply( $hook_name, $value, $args );
	}
}

if ( ! function_exists( 'did_action' ) ) {
	/**
	 * How many times an action has run.
	 *
	 * A filter of the same name is not counted here, and not by the platform either: the
	 * two are counted apart and asked about apart.
	 *
	 * @param string $hook_name The action to count.
	 * @return int
	 */
	function did_action( string $hook_name ): int {
		return Hooks::fired( $hook_name );
	}
}

if ( ! function_exists( 'did_filter' ) ) {
	/**
	 * How many times a filter has been applied.
	 *
	 * @param string $hook_name The filter to count.
	 * @return int
	 */
	function did_filter( string $hook_name ): int {
		return Hooks::filtered( $hook_name );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Escapes a string for an HTML context.
	 *
	 * Nothing in this bundle calls it, and no test reaches it. The hook library calls it
	 * on the failure path of the three classes a project can still extend, so a fixture
	 * that got its own hook name or callback wrong would fatal on an undefined function
	 * instead of raising what the library meant to raise. It is here so that the failure a
	 * test would see is the library's own.
	 *
	 * It escapes more than the platform's does, which escapes an entity once and leaves it
	 * alone. Nothing here compares the two, and nothing should read this as the platform's
	 * behaviour.
	 *
	 * @param string $text The text to escape.
	 * @return string
	 */
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}
