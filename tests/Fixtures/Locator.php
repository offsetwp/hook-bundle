<?php
/**
 * OffsetWP Hook Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\Tests\Fixtures
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\HookBundle\Tests\Fixtures;

use Psr\Container\ContainerInterface;

/**
 * A locator that builds a service the first time it is asked for it, and counts how
 * often it was asked.
 *
 * It stands in for the one the container builds, and it has the one property the
 * registrar depends on: asking is what builds, and asking twice builds once.
 */
final class Locator implements ContainerInterface {

	/**
	 * What has been built, by id.
	 *
	 * @var array<string, object>
	 */
	private array $built = array();

	/**
	 * How many times each id has been asked for.
	 *
	 * @var array<string, int>
	 */
	private array $lookups = array();

	/**
	 * The factories, by id.
	 *
	 * @param array<string, callable(): object> $factories One factory per service id.
	 * @return void
	 */
	public function __construct( private array $factories = array() ) {
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $id The service id.
	 * @throws \LogicException When nothing is registered under that id.
	 * @return mixed
	 */
	public function get( string $id ): mixed {
		$this->lookups[ $id ] = $this->lookups( $id ) + 1;

		if ( ! isset( $this->factories[ $id ] ) ) {
			throw new \LogicException( sprintf( 'Nothing is registered under the id "%s".', $id ) );
		}

		return $this->built[ $id ] ??= ( $this->factories[ $id ] )();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $id The service id.
	 * @return bool
	 */
	public function has( string $id ): bool {
		return isset( $this->factories[ $id ] );
	}

	/**
	 * How many times an id has been asked for.
	 *
	 * @param string $id The service id.
	 * @return int
	 */
	public function lookups( string $id ): int {
		return $this->lookups[ $id ] ?? 0;
	}
}
