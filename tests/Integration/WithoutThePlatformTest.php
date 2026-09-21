<?php
/**
 * OffsetWP Hook Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\Tests\Integration
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\HookBundle\Tests\Integration;

use OffsetWP\Bundle\HookBundle\HookRegistrar;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\HookTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * What a kernel booted before, or outside, the platform is told.
 *
 * This is the first failure anyone booting from a command-line script, a cron entry or
 * their own test suite runs into, and it is the one branch this suite cannot reach from
 * the inside: tests/bootstrap.php declares the platform functions into every process it
 * starts, so function_exists() is true for all of them, always.
 *
 * So every assertion here is made in a process of its own, started without that bootstrap.
 * They are the only tests that do, and the reason is that the thing under test is the
 * absence of something the rest of the suite depends on being present.
 */
#[CoversClass( HookRegistrar::class )]
final class WithoutThePlatformTest extends HookTestCase {

	/**
	 * A registrar with something to register, in a process where the platform was never
	 * loaded, names the handler and the function that is missing.
	 *
	 * @return void
	 */
	public function testAKernelBootedOutsideThePlatformIsToldWhatIsMissing(): void {
		$output = $this->runWithoutThePlatform( $this->registering( $this->handler( 'action', 'init' ) ) );

		$this->assertStringContainsString(
			'The hook bundle cannot register the action "init" declared by "H::handle()": add_action() does not exist',
			$output
		);
		$this->assertStringContainsString( 'Boot it from a mu-plugin, a plugin or a theme', $output );
	}

	/**
	 * A registrar with nothing to register asks the platform for nothing, so a project
	 * that carries this bundle and declares no hook boots anywhere.
	 *
	 * @return void
	 */
	public function testARegistrarWithNothingToRegisterAsksForNothing(): void {
		$this->assertSame( 'registered', $this->runWithoutThePlatform( $this->registering() ) );
	}

	/**
	 * And the case the check exists for: the platform declares shortcodes two hundred lines
	 * after actions, so a kernel booted from a drop-in can be past one and not the other.
	 * A project with no shortcode is not refused for a function it never calls.
	 *
	 * @return void
	 */
	public function testAHalfLoadedPlatformOnlyRefusesWhatItCannotTake(): void {
		$half_loaded = 'function add_action( $h, $c, $p = 10, $a = 1 ) { return true; }
			function add_filter( $h, $c, $p = 10, $a = 1 ) { return true; }';

		$this->assertSame(
			'registered',
			$this->runWithoutThePlatform( $half_loaded . $this->registering( $this->handler( 'action', 'init' ), $this->handler( 'filter', 'the_title' ) ) ),
			'An action and a filter are registered, although add_shortcode() is not there yet.'
		);

		$this->assertStringContainsString(
			'cannot register the shortcode "year" declared by "H::handle()": add_shortcode() does not exist',
			$this->runWithoutThePlatform( $half_loaded . $this->registering( $this->handler( 'shortcode', 'year' ) ) )
		);
	}

	/**
	 * One handler record, written as the PHP a subprocess can read.
	 *
	 * Static, so that nothing here needs a locator: what is under test is which platform
	 * function a handler asks for, not how its service is found.
	 *
	 * @param string $type The kind of hook.
	 * @param string $hook The hook name or shortcode tag.
	 * @return string
	 */
	private function handler( string $type, string $hook ): string {
		return sprintf(
			'array( "type" => "%s", "hook" => "%s", "service" => "app.handler", "class" => "H", "method" => "handle", "priority" => 10, "accepted_args" => 0, "is_static" => true )',
			$type,
			$hook
		);
	}

	/**
	 * A snippet that builds a registrar over those handlers and registers it.
	 *
	 * @param string ...$handlers The handler records, as PHP source.
	 * @return string
	 */
	private function registering( string ...$handlers ): string {
		return sprintf(
			'class H { private static function handle(): void {} }
			try {
				( new OffsetWP\\Bundle\\HookBundle\\HookRegistrar( array( %s ) ) )->register();
				echo "registered";
			} catch ( Throwable $failure ) {
				echo $failure->getMessage();
			}',
			implode( ', ', $handlers )
		);
	}

	/**
	 * Runs a snippet in a process that has the autoloader and nothing else.
	 *
	 * @param string $snippet The PHP to run.
	 * @return string Everything the process wrote.
	 */
	private function runWithoutThePlatform( string $snippet ): string {
		$autoload = dirname( __DIR__, 2 ) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

		$this->assertFileExists( $autoload );

		$command = sprintf(
			'%s -r %s 2>&1',
			escapeshellarg( PHP_BINARY ),
			escapeshellarg( sprintf( "require '%s';", $autoload ) . $snippet )
		);

		$lines  = array();
		$status = 0;

		exec( $command, $lines, $status );

		$this->assertSame( 0, $status, implode( PHP_EOL, $lines ) );

		return implode( PHP_EOL, $lines );
	}
}
