<?php
/**
 * OffsetWP Hook Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\Tests\Fixtures
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\HookBundle\Tests\Fixtures;

use OffsetWP\Framework\Kernel;
use OffsetWP\Support\Env;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * A kernel an integration test can boot.
 *
 * It is built by hand rather than through the fluent builder, because the builder only
 * ever hands back a kernel that has already booted, and several tests need to look at the
 * container before that happens.
 *
 * The environment is the production literal and never comes from the helper that guesses
 * it, which reaches for a function only a real request of the platform has.
 */
final class TestKernel extends Kernel {

	/**
	 * Build a kernel rooted at a fixture project.
	 *
	 * @param string        $root_path The fixture project directory.
	 * @param \Closure|null $services  Extra service definitions, registered on the container the way a project's config/services.php would.
	 * @return void
	 */
	public function __construct( string $root_path, private ?\Closure $services = null ) {
		parent::__construct( $root_path );

		$this->setEnvironment( Env::PRODUCTION );
		$this->setConfigPath( $root_path . DIRECTORY_SEPARATOR . 'config' );
	}

	/**
	 * Adds the injected definitions on top of whatever the fixture project declares.
	 *
	 * @return self
	 */
	protected function registerContainerConfiguration(): self {
		parent::registerContainerConfiguration();

		if ( null !== $this->services && $this->container instanceof ContainerBuilder ) {
			( $this->services )( $this->container );
		}

		return $this;
	}
}
