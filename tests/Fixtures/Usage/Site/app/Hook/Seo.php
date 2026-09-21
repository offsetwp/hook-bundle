<?php
/**
 * A fixture project of the OffsetWP Hook Bundle test suite.
 *
 * @author Jérôme Wohlschlegel
 * @package App\Hook
 */

declare( strict_types=1 );

namespace App\Hook;

use App\Service\SiteName;
use OffsetWP\Bundle\HookBundle\Attribute\AsAction;
use OffsetWP\Bundle\HookBundle\Attribute\AsFilter;
use OffsetWP\Bundle\HookBundle\Attribute\AsHookHandler;
use OffsetWP\Bundle\HookBundle\Attribute\AsShortCode;

/**
 * The class the README opens on, written as a project writes it.
 */
#[AsHookHandler]
final class Seo {

	/**
	 * How many times this class has been constructed.
	 *
	 * @var int
	 */
	public static int $constructions = 0;

	/**
	 * What each handler was called with, in order.
	 *
	 * @var array<int, string>
	 */
	public static array $calls = array();

	/**
	 * Takes the service it needs, and counts one construction.
	 *
	 * @param SiteName $site_name The name of the site.
	 * @return void
	 */
	public function __construct( private SiteName $site_name ) {
		++self::$constructions;
	}

	/**
	 * Puts both counters back to nothing.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$constructions = 0;
		self::$calls         = array();
	}

	/**
	 * Runs once the platform is ready.
	 *
	 * @return void
	 */
	#[AsAction( 'init' )]
	private function boot(): void {
		self::$calls[] = 'boot';
	}

	/**
	 * Appends the site name to every title.
	 *
	 * @param string $title The title being filtered.
	 * @param int    $id    The post it belongs to.
	 * @return string
	 */
	#[AsFilter( 'the_title', priority: 20, accepted_args: 2 )]
	private function title( string $title, int $id ): string {
		return sprintf( '%s — %s (#%d)', $title, $this->site_name->value(), $id );
	}

	/**
	 * The current year, without anything being built for it.
	 *
	 * @return string
	 */
	#[AsShortCode( 'year' )]
	private static function year(): string {
		return '2026';
	}
}
