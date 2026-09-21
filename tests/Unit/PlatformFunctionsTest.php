<?php
/**
 * OffsetWP Hook Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\Tests\Unit
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\HookBundle\Tests\Unit;

use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Hooks;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\HookTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The platform functions the rest of the suite runs against.
 *
 * Every other test in this repository believes what these do, so what they do is
 * asserted here first: a callback is recorded, a lower priority runs first, a callback
 * receives only as many arguments as it accepts, and a filter threads its value through.
 *
 * Nothing under src/ is exercised here. This protects the ground the suite stands on.
 */
#[CoversClass( Hooks::class )]
final class PlatformFunctionsTest extends HookTestCase {

	/**
	 * A callback added to an action is recorded, and runs when the action does.
	 *
	 * @return void
	 */
	public function testAnActionRunsItsCallback(): void {
		$seen = array();

		add_action(
			'init',
			static function () use ( &$seen ): void {
				$seen[] = 'ran';
			}
		);

		$this->assertTrue( Hooks::has( 'init' ) );
		$this->assertSame( array(), $seen );

		do_action( 'init' );

		$this->assertSame( array( 'ran' ), $seen );
		$this->assertSame( 1, did_action( 'init' ) );
	}

	/**
	 * A lower priority runs first, and callbacks of one priority run in the order they
	 * were added.
	 *
	 * @return void
	 */
	public function testALowerPriorityRunsFirst(): void {
		$order = array();

		foreach ( array(
			'c' => 20,
			'a' => 5,
			'b' => 20,
		) as $label => $priority ) {
			add_action(
				'init',
				static function () use ( &$order, $label ): void {
					$order[] = $label;
				},
				$priority
			);
		}

		do_action( 'init' );

		$this->assertSame( array( 'a', 'c', 'b' ), $order );
		$this->assertSame( array( 5, 20 ), Hooks::priorities( 'init' ) );
	}

	/**
	 * A callback receives as many arguments as it said it accepts, and no more.
	 *
	 * @return void
	 */
	public function testACallbackReceivesOnlyTheArgumentsItAccepts(): void {
		$received = array();

		add_action(
			'save_post',
			static function ( mixed ...$args ) use ( &$received ): void {
				$received[] = $args;
			},
			10,
			2
		);

		do_action( 'save_post', 7, 'post', true );

		$this->assertSame( array( array( 7, 'post' ) ), $received );
	}

	/**
	 * An action fired with no arguments hands a single empty string to whoever accepts
	 * one, which is what the platform does and what a handler of one parameter sees.
	 *
	 * @return void
	 */
	public function testAnActionWithNoArgumentsHandsAnEmptyString(): void {
		$received = array();

		add_action(
			'init',
			static function ( mixed ...$args ) use ( &$received ): void {
				$received[] = $args;
			},
			10,
			1
		);

		do_action( 'init' );

		$this->assertSame( array( array( '' ) ), $received );
	}

	/**
	 * A filter threads its value through every callback, in priority order.
	 *
	 * @return void
	 */
	public function testAFilterThreadsItsValue(): void {
		add_filter( 'the_title', static fn ( string $title ): string => $title . '!', 20 );
		add_filter( 'the_title', static fn ( string $title ): string => strtoupper( $title ), 5 );

		$this->assertSame( 'HELLO!', apply_filters( 'the_title', 'hello' ) );
		$this->assertSame( 1, did_filter( 'the_title' ) );
		$this->assertSame( 0, did_action( 'the_title' ), 'A filter is not an action, and the platform does not count it as one.' );
	}

	/**
	 * A filter callback that accepts more than the value receives what follows it.
	 *
	 * @return void
	 */
	public function testAFilterPassesTheArgumentsThatFollowTheValue(): void {
		add_filter( 'the_title', static fn ( string $title, int $id ): string => $title . '#' . $id, 10, 2 );

		$this->assertSame( 'hello#7', apply_filters( 'the_title', 'hello', 7, 'ignored' ) );
	}

	/**
	 * A shortcode is held one per tag, and is handed the attributes, the content and
	 * the tag when it runs.
	 *
	 * @return void
	 */
	public function testAShortCodeIsCalledWithThreeArguments(): void {
		$body = static function ( array $atts, ?string $content, string $tag ): string {
			$of = $atts['of'] ?? '';

			return sprintf( '%s|%s|%s', is_string( $of ) ? $of : '', $content ?? '', $tag );
		};

		add_shortcode( 'year', $body );

		$this->assertTrue( Hooks::hasShortCode( 'year' ) );
		$this->assertSame( '2026|body|year', Hooks::runShortCode( 'year', array( 'of' => '2026' ), 'body' ) );
	}

	/**
	 * A hook nobody registered against runs, changes nothing, and is still counted.
	 *
	 * @return void
	 */
	public function testAnUnknownHookIsHarmless(): void {
		do_action( 'nobody_listens' );

		$this->assertSame( 'untouched', apply_filters( 'nobody_filters', 'untouched' ) );
		$this->assertFalse( Hooks::has( 'nobody_listens' ) );
		$this->assertSame( 1, did_action( 'nobody_listens' ) );
	}

	/**
	 * The register is empty at the start of every test, which is what lets the suite
	 * run in one process and in a random order.
	 *
	 * @return void
	 */
	public function testTheRegisterStartsEmpty(): void {
		$this->assertFalse( Hooks::has( 'init' ) );
		$this->assertSame( 0, did_action( 'init' ) );
		$this->assertSame( 0, did_filter( 'the_title' ) );
		$this->assertSame( 0, Hooks::callbackCount( 'init' ) );

		// The shortcodes too, since the two tests that open on hasShortCode() would pass
		// on a register that was cleared of everything else and not of them.
		$this->assertFalse( Hooks::hasShortCode( 'year' ) );
	}

	/**
	 * An action clones an object before handing it over, so a handler that mutates what it
	 * was given changes nothing for whoever fired the hook.
	 *
	 * @return void
	 */
	public function testAnActionClonesAnObjectItHandsOver(): void {
		$post       = new \stdClass();
		$post->name = 'original';

		add_action(
			'save_post',
			static function ( \stdClass $given ): void {
				$given->name = 'changed';
			}
		);

		do_action( 'save_post', $post );

		$this->assertSame( 'original', $post->name );
	}

	/**
	 * A callback added while a hook is running joins that same run when its priority has
	 * not been passed yet, and waits for the next run when it has. This is what a kernel
	 * booted from inside a hook depends on.
	 *
	 * @return void
	 */
	public function testACallbackAddedWhileTheHookRunsJoinsItFromWhereItIs(): void {
		$order = array();

		$record = static function ( string $label ) use ( &$order ): callable {
			return static function () use ( &$order, $label ): void {
				$order[] = $label;
			};
		};

		$has_booted = false;

		add_action(
			'init',
			static function () use ( &$order, &$has_booted, $record ): void {
				$order[] = 'booting';

				if ( $has_booted ) {
					return;
				}

				$has_booted = true;

				add_action( 'init', $record( 'ahead' ), 20 );
				add_action( 'init', $record( 'behind' ), 1 );
			},
			10
		);

		do_action( 'init' );

		$this->assertSame( array( 'booting', 'ahead' ), $order );

		$order = array();

		do_action( 'init' );

		$this->assertSame( array( 'behind', 'booting', 'ahead' ), $order );
	}

	/**
	 * Which of the two functions recorded a callback is kept, because they do the same
	 * thing and nothing else could tell an action from a filter afterwards.
	 *
	 * @return void
	 */
	public function testTheRegisterKeepsWhichFunctionRecordedACallback(): void {
		$nothing = static fn (): string => '';

		add_action( 'mixed_hook', $nothing, 10 );
		add_filter( 'mixed_hook', $nothing, 20 );

		$this->assertSame( array( 'add_action', 'add_filter' ), Hooks::addedVia( 'mixed_hook' ) );
	}

	/**
	 * The by-reference form hands its array over as it stands, string keys included, which
	 * is the one way a call can arrive carrying named arguments.
	 *
	 * @return void
	 */
	public function testTheByReferenceFormHandsItsArrayOverAsItStands(): void {
		$received = array();

		add_action(
			'save_post',
			static function ( mixed ...$args ) use ( &$received ): void {
				$received[] = $args;
			},
			10,
			2
		);

		do_action_ref_array( 'save_post', array( 7, 'post' ) );

		$this->assertSame( array( array( 7, 'post' ) ), $received );
		$this->assertSame( 'value!', apply_filters_ref_array( 'nothing_listens', array( 'value!' ) ) );
	}
}
