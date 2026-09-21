<?php
/**
 * OffsetWP Hook Bundle
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\HookBundle;

use OffsetWP\Bundle\HookBundle\Attribute\AsAction;
use OffsetWP\Bundle\HookBundle\Attribute\AsFilter;
use OffsetWP\Bundle\HookBundle\Attribute\AsHookHandler;
use OffsetWP\Bundle\HookBundle\Attribute\AsShortCode;
use OffsetWP\Bundle\HookBundle\DependencyInjection\Compiler\HandlerPass;
use OffsetWP\Framework\Bundle\Bundle;
use OffsetWP\Hook\Support\Action;
use OffsetWP\Hook\Support\Filter;
use OffsetWP\Hook\Support\ShortCode;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * HookBundle
 *
 * Turns the hooks a project declares with attributes into registrations the platform
 * receives when the kernel boots — and into nothing else. No class behind a hook is built
 * until the hook fires.
 *
 * The bundle name is the short class name, so the extension alias derived from it reads
 * "hook". There is no configuration under it, and none is planned: everything this bundle
 * does is decided by the attributes a project writes on its own classes.
 */
final class HookBundle extends Bundle {

	/**
	 * The three classes of the hook library a project can still extend.
	 *
	 * Their constructor is the registration, which is exactly what the attributes are
	 * here to replace: a class extending one of them is built on every request, fired or
	 * not. They keep working, and the README says what that costs.
	 *
	 * @var array<int, class-string>
	 */
	private const SUPPORT_CLASSES = array( Action::class, Filter::class, ShortCode::class );

	/**
	 * Hands every declared hook to the platform.
	 *
	 * This is the only moment anything is registered, and it is still not the moment
	 * anything is built: what the platform receives is a closure per handler.
	 *
	 * A hook that has already fired by the time a kernel boots will not fire again. That is
	 * a fact about where a kernel is booted from rather than something this bundle can fix:
	 * a kernel booted from a theme is past muplugins_loaded and plugins_loaded, and a
	 * handler declared against either of those will sit there unfired. Boot from a
	 * mu-plugin when you need them.
	 *
	 * @throws \LogicException When the bundle was booted without a container, or without its own service.
	 * @return void
	 */
	public function boot(): void {
		if ( ! isset( $this->container ) ) {
			throw new \LogicException(
				'Cannot boot the hook bundle without a container. A bundle receives one from the kernel that registered it, so boot the kernel rather than the bundle.'
			);
		}

		$registrar = $this->container->get( HookRegistrar::SERVICE_ID );

		if ( ! $registrar instanceof HookRegistrar ) {
			throw new \LogicException(
				sprintf(
					'The service "%s" is a "%s", not a "%s". Something else in this project has defined a service under that id.',
					HookRegistrar::SERVICE_ID,
					get_debug_type( $registrar ),
					HookRegistrar::class
				)
			);
		}

		$registrar->register();
	}

	/**
	 * Registers what finds the handlers, and the pass that reads them.
	 *
	 * The pass goes in the before-optimization phase at the default priority, which puts
	 * it after the library's own tag resolution: by the time it reads a tag, the classes
	 * are resolved and everything autoconfiguration had to add has been added.
	 *
	 * @param ContainerBuilder $container The service container.
	 * @return void
	 */
	public function build( ContainerBuilder $container ): void {
		parent::build( $container );

		/*
		 * The form the hook library has always taken: a class whose constructor is the
		 * registration. Building it is registering it, so the framework has to build it,
		 * and this is what says so — rather than every project writing the tag itself on
		 * every class. The tag name is written out because the framework offers no
		 * constant for it.
		 *
		 * A project that does write it keeps working: the library skips a tag identical
		 * to one already there, and two of them would ask the container for one shared
		 * service twice anyway.
		 */
		foreach ( self::SUPPORT_CLASSES as $support_class ) {
			$container->registerForAutoconfiguration( $support_class )->addTag( 'kernel.autoload' );
		}

		$container->registerAttributeForAutoconfiguration( AsHookHandler::class, $this->markHandlerClass( ... ) );

		/*
		 * Not a way in, and never documented as one. A hook declared on a public method
		 * is found here so that a class which forgot its mark fails by name instead of
		 * doing nothing at all — which is what the same class with a private handler
		 * does, because nothing can see it.
		 */
		foreach ( HandlerPass::ATTRIBUTES as $attribute ) {
			$container->registerAttributeForAutoconfiguration( $attribute, $this->markAttributedMethod( ... ) );
		}

		$container->addCompilerPass( new HandlerPass() );
	}

	/**
	 * Marks a class the class attribute was found on.
	 *
	 * The third argument is typed \Reflector and narrowed inside, which is one step more
	 * than it looks. The library reads that type to decide what to scan, and the
	 * signature it declares for this callable is \Reflector, so a narrower one is a
	 * static-analysis error rather than a preference. The guard below costs a comparison
	 * and makes the difference unobservable.
	 *
	 * @param ChildDefinition $definition The definition of the class carrying the attribute.
	 * @param AsHookHandler   $attribute  The attribute that was found, and carries nothing.
	 * @param \Reflector      $reflector  What the attribute was found on.
	 * @return void
	 */
	public function markHandlerClass( ChildDefinition $definition, AsHookHandler $attribute, \Reflector $reflector ): void {
		unset( $attribute );

		if ( ! $reflector instanceof \ReflectionClass ) {
			return;
		}

		$definition->addTag( HandlerPass::HANDLER_TAG );
	}

	/**
	 * Marks a class one of the three method attributes was found on a public method of.
	 *
	 * What the tag carries is what the failure needs to name: which method, and which
	 * attribute. Both are strings, because everything on a tag is written into the
	 * container.
	 *
	 * @param ChildDefinition               $definition The definition of the class carrying the attribute.
	 * @param AsAction|AsFilter|AsShortCode $attribute  The attribute that was found.
	 * @param \Reflector                    $reflector  What the attribute was found on.
	 * @return void
	 */
	public function markAttributedMethod( ChildDefinition $definition, AsAction|AsFilter|AsShortCode $attribute, \Reflector $reflector ): void {
		if ( ! $reflector instanceof \ReflectionMethod ) {
			return;
		}

		$definition->addTag(
			HandlerPass::ATTRIBUTED_METHOD_TAG,
			array(
				'method'    => $reflector->getName(),
				'attribute' => ( new \ReflectionClass( $attribute ) )->getShortName(),
			)
		);
	}

	/**
	 * Declares the configuration tree, which is empty and says so.
	 *
	 * @param DefinitionConfigurator $definition The definition configurator.
	 * @return void
	 */
	public function configure( DefinitionConfigurator $definition ): void {
		$definition->import( __DIR__ . '/Resources/config/definition.php' );
	}

	/**
	 * Registers the one service this bundle owns.
	 *
	 * @param array<string, mixed>  $config    The processed configuration, which is empty and stays empty.
	 * @param ContainerConfigurator $container The container configurator.
	 * @param ContainerBuilder      $builder   The service container.
	 * @return void
	 */
	public function loadExtension( array $config, ContainerConfigurator $container, ContainerBuilder $builder ): void {
		unset( $config, $builder );

		$container->import( __DIR__ . '/Resources/config/services.php' );
	}
}
