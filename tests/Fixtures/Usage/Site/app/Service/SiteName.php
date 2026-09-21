<?php
/**
 * A fixture project of the OffsetWP Hook Bundle test suite.
 *
 * @author Jérôme Wohlschlegel
 * @package App\Service
 */

declare( strict_types=1 );

namespace App\Service;

/**
 * An ordinary service of the project, which a handler depends on.
 */
final class SiteName {

	/**
	 * How many times this class has been constructed.
	 *
	 * @var int
	 */
	public static int $constructions = 0;

	/**
	 * Counts one construction.
	 *
	 * @return void
	 */
	public function __construct() {
		++self::$constructions;
	}

	/**
	 * Puts the counter back to nothing.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$constructions = 0;
	}

	/**
	 * The name of the site.
	 *
	 * @return string
	 */
	public function value(): string {
		return 'Étoile Malraux';
	}
}
