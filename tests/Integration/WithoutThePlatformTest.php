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
 * What a kernel booted outside WordPress is told.
 *
 * This is the first failure anyone booting from a command-line script, a cron entry or
 * their own test suite runs into, and it is the one branch this suite cannot reach from
 * the inside: tests/bootstrap.php declares the platform functions into every process it
 * starts, so function_exists() is true for all of them, always.
 *
 * So the assertion is made in a process of its own, started without that bootstrap. It is
 * the only test here that does, and the reason is that the thing under test is the absence
 * of something the rest of the suite depends on being present.
 */
#[CoversClass( HookRegistrar::class )]
final class WithoutThePlatformTest extends HookTestCase {

	/**
	 * A registrar with something to register, in a process where the platform was never
	 * loaded, names the function that is missing and where a kernel should boot from.
	 *
	 * @return void
	 */
	public function testAKernelBootedOutsideThePlatformIsToldWhichFunctionIsMissing(): void {
		$output = $this->runWithoutThePlatform(
			'$handler = array(
				"type"          => "action",
				"hook"          => "init",
				"service"       => "app.handler",
				"class"         => "stdClass",
				"method"        => "handle",
				"priority"      => 10,
				"accepted_args" => 0,
				"is_static"     => false,
			);
			try {
				( new OffsetWP\Bundle\HookBundle\HookRegistrar( array( $handler ) ) )->register();
				echo "registered";
			} catch ( Throwable $failure ) {
				echo $failure->getMessage();
			}'
		);

		$this->assertStringContainsString( 'The hook bundle cannot register anything: add_action() does not exist', $output );
		$this->assertStringContainsString( 'Boot it from a mu-plugin, a plugin or a theme', $output );
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
