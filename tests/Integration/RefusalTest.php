<?php
/**
 * OffsetWP Hook Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\Tests\Integration
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\HookBundle\Tests\Integration;

use OffsetWP\Bundle\HookBundle\DependencyInjection\Compiler\HandlerPass;
use OffsetWP\Bundle\HookBundle\HookRegistrar;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Handler\CountedHandler;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Invalid\ArgumentlessFilter;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Invalid\ByReferenceAction;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Invalid\NamelessAction;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Invalid\NegativeArguments;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Invalid\NothingDeclared;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Invalid\SpacedShortCode;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Invalid\UnmarkedPublicHandler;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Invalid\ValuelessFilter;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Invalid\VariadicAction;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Invalid\VoidFilter;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Invalid\VoidShortCode;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\KernelTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Everything the container refuses to compile, and what each refusal says.
 *
 * A declaration that cannot work is worth more as a build that stops than as a hook that
 * quietly does the wrong thing, so every one of them is here with the words it uses. A
 * message that names nothing is a message nobody can act on, so each assertion is on the
 * part that names the class, the method or the hook.
 */
#[CoversClass( HandlerPass::class )]
final class RefusalTest extends KernelTestCase {

	/**
	 * Boot a kernel carrying one class, and assert on what the build says about it.
	 *
	 * @param class-string $handler_class The class to register.
	 * @param string       $expected      What the failure has to contain.
	 * @return void
	 */
	private function assertRefused( string $handler_class, string $expected ): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( $expected );

		$this->bootWith( $handler_class );
	}

	/**
	 * A filter that returns nothing would empty what it filters.
	 *
	 * @return void
	 */
	public function testAFilterThatReturnsNothingIsRefused(): void {
		$this->assertRefused(
			VoidFilter::class,
			'"' . VoidFilter::class . '::handle()" declares "void_filter" with #[AsFilter] and returns void. A filter replaces a value, so it has to return one.'
		);
	}

	/**
	 * A shortcode that returns nothing would empty the post it is in.
	 *
	 * @return void
	 */
	public function testAShortCodeThatReturnsNothingIsRefused(): void {
		$this->assertRefused(
			VoidShortCode::class,
			'"' . VoidShortCode::class . '::handle()" declares "void_code" with #[AsShortCode] and returns void. A shortcode replaces a value, so it has to return one.'
		);
	}

	/**
	 * A filter that takes no parameter is never handed the value it filters.
	 *
	 * @return void
	 */
	public function testAFilterThatTakesNothingIsRefused(): void {
		$this->assertRefused(
			ValuelessFilter::class,
			'"' . ValuelessFilter::class . '::handle()" declares the filter "valueless_filter" and takes no parameter.'
		);
	}

	/**
	 * A variadic handler is asked to say how many arguments it wants, because nothing
	 * else can say it for it.
	 *
	 * @return void
	 */
	public function testAVariadicHandlerWithNoArgumentCountIsRefused(): void {
		$this->assertRefused(
			VariadicAction::class,
			'"' . VariadicAction::class . "::handle()\" declares \"variadic_action\" and is variadic, so how many arguments it wants cannot be read off its signature. Write it: #[AsAction( 'variadic_action', accepted_args: 2 )]."
		);
	}

	/**
	 * A count of arguments is not negative.
	 *
	 * @return void
	 */
	public function testANegativeArgumentCountIsRefused(): void {
		$this->assertRefused(
			NegativeArguments::class,
			'"' . NegativeArguments::class . '::handle()" declares "negative_action" with -1 accepted arguments.'
		);
	}

	/**
	 * A hook with no name would be registered and never fire.
	 *
	 * @return void
	 */
	public function testAHookWithNoNameIsRefused(): void {
		$this->assertRefused(
			NamelessAction::class,
			'"' . NamelessAction::class . '::handle()" carries #[AsAction] with no name.'
		);
	}

	/**
	 * A class marked as a handler that declares nothing is a mark on the wrong class, or
	 * an attribute that was never written.
	 *
	 * @return void
	 */
	public function testAMarkedClassDeclaringNothingIsRefused(): void {
		$this->assertRefused(
			NothingDeclared::class,
			'The class "' . NothingDeclared::class . '" is marked as a hook handler but declares no hook.'
		);
	}

	/**
	 * A public handler on an unmarked class is the one forgotten mark that can be seen,
	 * so it is named rather than ignored.
	 *
	 * @return void
	 */
	public function testAPublicHandlerOnAnUnmarkedClassIsRefused(): void {
		$this->assertRefused(
			UnmarkedPublicHandler::class,
			'"' . UnmarkedPublicHandler::class . '::handle()" carries #[AsAction], but its class is not marked as a hook handler and nothing will register it. Add #[AsHookHandler] to the class, or tag the service "' . UnmarkedPublicHandler::class . '" with "hook.handler".'
		);
	}

	/**
	 * A marked service with no class of its own has no methods to read.
	 *
	 * @return void
	 */
	public function testAMarkedServiceWithNoClassIsRefused(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'The service "app.synthetic" is marked as a hook handler, and its class cannot be worked out' );

		$this->boot(
			static function ( ContainerBuilder $container ): void {
				$container->setDefinition(
					'app.synthetic',
					( new Definition() )
						->setSynthetic( true )
						->setPublic( true )
						->addTag( HandlerPass::HANDLER_TAG )
				);
			}
		);
	}

	/**
	 * A marked service whose class cannot be loaded is named along with the class.
	 *
	 * @return void
	 */
	public function testAMarkedServiceWithAnUnloadableClassIsRefused(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'its class "App\\Nowhere" cannot be loaded' );

		$this->boot(
			static function ( ContainerBuilder $container ): void {
				$container->setDefinition(
					'app.missing',
					( new Definition( 'App\\Nowhere' ) )
						->setPublic( true )
						->addTag( HandlerPass::HANDLER_TAG )
				);
			}
		);
	}

	/**
	 * An abstract service carrying the mark is named, rather than letting the failure
	 * land on the registrar that referenced it.
	 *
	 * @return void
	 */
	public function testAnAbstractMarkedServiceIsRefused(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'The service "app.abstract" tagged "hook.handler" must not be abstract.' );

		$this->boot(
			static function ( ContainerBuilder $container ): void {
				$container->setDefinition(
					'app.abstract',
					( new Definition( NothingDeclared::class ) )
						->setAbstract( true )
						->addTag( HandlerPass::HANDLER_TAG )
				);
			}
		);
	}

	/**
	 * A project that has taken the registrar's id is told what it took with it.
	 *
	 * @return void
	 */
	public function testAProjectRedefiningTheRegistrarIsRefused(): void {
		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage( 'The service "hook.registrar" is defined or aliased by this project as well as by the hook bundle' );

		$this->boot(
			static function ( ContainerBuilder $container ): void {
				$container->setDefinition( HookRegistrar::SERVICE_ID, new Definition( \stdClass::class ) );
			}
		);
	}

	/**
	 * Aliasing the id is the other way of taking it, and the one that used to be silent:
	 * the definition is gone, so the pass collected every hook into nothing while the
	 * service the bundle boots still answered.
	 *
	 * @return void
	 */
	public function testAProjectAliasingTheRegistrarIsRefused(): void {
		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage( 'The service "hook.registrar" is defined or aliased by this project' );

		$this->boot(
			static function ( ContainerBuilder $container ): void {
				$container->setDefinition( 'app.registrar', new Definition( HookRegistrar::class ) );
				$container->setAlias( HookRegistrar::SERVICE_ID, 'app.registrar' );
			}
		);
	}

	/**
	 * A decorated handler resolves to its decorator, so the hooks of the handler would be
	 * looked for on an object that is not one — at the first hook that fires, far from
	 * the build that allowed it.
	 *
	 * @return void
	 */
	public function testADecoratedHandlerIsRefused(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'is marked as a hook handler and is decorated by "app.decorator"' );

		$this->boot(
			static function ( ContainerBuilder $container ): void {
				$container->setDefinition( CountedHandler::class, self::serviceFor( CountedHandler::class ) );
				$container->setDefinition(
					'app.decorator',
					( new Definition( \stdClass::class ) )->setDecoratedService( CountedHandler::class )
				);
			}
		);
	}

	/**
	 * A handler asking for a reference cannot be given one, and a wrong answer with
	 * nothing said is worse than a build that stops.
	 *
	 * @return void
	 */
	public function testAByReferenceParameterIsRefused(): void {
		$this->assertRefused(
			ByReferenceAction::class,
			'"' . ByReferenceAction::class . '::handle()" declares a hook and takes $items by reference.'
		);
	}

	/**
	 * A shortcode tag the platform reserves characters in is dropped by the platform
	 * without a word, so it is refused here where there is something to name.
	 *
	 * @return void
	 */
	public function testAShortCodeTagThePlatformWouldDropIsRefused(): void {
		$this->assertRefused(
			SpacedShortCode::class,
			'"' . SpacedShortCode::class . '::handle()" declares the shortcode "my tag", whose tag carries a space or one of the characters the platform reserves'
		);
	}

	/**
	 * Nought accepted arguments is a real count for an action and an impossible one for a
	 * filter: the platform reads it as calling the handler with nothing at all.
	 *
	 * @return void
	 */
	public function testAFilterAcceptingNoArgumentIsRefused(): void {
		$this->assertRefused(
			ArgumentlessFilter::class,
			'"' . ArgumentlessFilter::class . '::handle()" declares the filter "argumentless_filter" with 0 accepted arguments'
		);
	}
}
