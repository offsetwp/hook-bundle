<?php
/**
 * A fixture project of the OffsetWP Hook Bundle test suite.
 *
 * A project that carries the bundle in one environment and not in the others, which is
 * what the per-environment map in this file is for.
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\Tests\Fixtures\Usage
 */

declare( strict_types=1 );

use OffsetWP\Bundle\HookBundle\HookBundle;
use OffsetWP\Support\Env;

return array(
	HookBundle::class => array( Env::PRODUCTION => true ),
);
