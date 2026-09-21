<?php
/**
 * OffsetWP Hook Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\Tests\Usage
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\HookBundle\Tests\Usage;

use App\Hook\Announcements;
use App\Hook\Counter;
use App\Hook\Seo;
use App\Service\SiteName;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Handler\CountedHandler;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Handler\Untagged;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\HookTestCase;
use OffsetWP\Framework\Kernel;
use OffsetWP\Support\Env;

/**
 * What every usage test starts from: a whole project, booted the way a project boots.
 *
 * Nothing here reaches into the container or registers a service by hand. A project is a
 * directory with a config/ directory in it, and the kernel is built by the same fluent
 * call the README shows at installation. If a test in this suite passes, a project doing
 * the same thing gets the same result.
 */
abstract class UsageTestCase extends HookTestCase {

	/**
	 * Every counter the fixture projects keep, put back to nothing.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		Seo::reset();
		Counter::reset();
		Announcements::reset();
		SiteName::reset();
		Untagged::reset();
		CountedHandler::reset();
	}

	/**
	 * The root directory of a fixture project.
	 *
	 * @param string $name The project directory name.
	 * @return string
	 */
	protected function site( string $name = 'Site' ): string {
		return __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Fixtures'
			. DIRECTORY_SEPARATOR . 'Usage' . DIRECTORY_SEPARATOR . $name;
	}

	/**
	 * Boot a fixture project, the way the README tells a project to boot one.
	 *
	 * The environment is passed as a literal rather than guessed, because the helper that
	 * guesses it reaches for a function of the platform that no test process has.
	 *
	 * @param string $name        The project directory name.
	 * @param string $environment The environment to boot in.
	 * @return Kernel
	 */
	protected function boot( string $name = 'Site', string $environment = Env::PRODUCTION ): Kernel {
		$root = $this->site( $name );

		return Kernel::configure( $root )
			->environment( $environment )
			->config( $root . DIRECTORY_SEPARATOR . 'config' )
			->boot();
	}
}
