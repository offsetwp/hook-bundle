![OffsetWP Hook Bundle](https://raw.githubusercontent.com/offsetwp/offsetwp.github.io/refs/heads/main/public/common/cover/cover-hook-bundle-light.png#gh-light-mode-only)
![OffsetWP Hook Bundle](https://raw.githubusercontent.com/offsetwp/offsetwp.github.io/refs/heads/main/public/common/cover/cover-hook-bundle-dark.png#gh-dark-mode-only)

<h1 align="center">
    OffsetWP Hook Bundle
</h1>

<p align="center">
	Actions, filters and shortcodes declared with PHP attributes. One method, one attribute, and the class is only built when the hook fires.
</p>

## Installation

**requirements:**
- PHP: 8.5+

**command:**
```bash
composer require offsetwp/hook-bundle
```

**register bundle:**
```php
// config/bundles.php
return array(
	\OffsetWP\Bundle\HookBundle\HookBundle::class => array( 'all' => true ),
);
```

There is no `config/packages/hook.php`. This bundle has no configuration.

## Declaring a hook

Where each file goes:

```
my-project/
├─ app/
│  ├─ Hook/                  # your handlers — ordinary classes
│  │  └─ Seo.php
│  └─ Service/               # whatever they depend on
│     └─ SiteName.php
└─ config/
   ├─ bundles.php            # where this bundle is registered
   └─ services.php           # where your classes become services
```

```php
// app/Hook/Seo.php
namespace App\Hook;

use App\Service\SiteName;
use OffsetWP\Bundle\HookBundle\Attribute\AsAction;
use OffsetWP\Bundle\HookBundle\Attribute\AsFilter;
use OffsetWP\Bundle\HookBundle\Attribute\AsHookHandler;
use OffsetWP\Bundle\HookBundle\Attribute\AsShortCode;

#[AsHookHandler]
final class Seo {

	public function __construct( private SiteName $site_name ) {
	}

	#[AsAction( 'init' )]
	private function boot(): void {
		// …
	}

	#[AsFilter( 'the_title', priority: 20, accepted_args: 2 )]
	private function title( string $title, int $id ): string {
		return sprintf( '%s — %s (#%d)', $title, $this->site_name->value(), $id );
	}

	#[AsShortCode( 'year' )]
	private static function year(): string {
		return date( 'Y' );
	}
}
```

```php
// config/services.php
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function ( ContainerConfigurator $container ): void {
	$services = $container->services();

	$services
		->defaults()
			->autowire()
			->autoconfigure()
			->public();

	$services->load( 'App\\', '../app/' );
};
```

`#[AsHookHandler]` on the class, and the class registered as a service. Without it a private handler is invisible, because the container only ever looks at public methods when it scans for attributes.

## Actions

```php
#[AsAction( 'init' )]
private function boot(): void {
}

#[AsAction( 'save_post' )]
private function saved( int $post_id, \WP_Post $post ): void {
}

// One method, several actions.
#[AsAction( 'init' )]
#[AsAction( 'wp_loaded', priority: 5 )]
private function both(): void {
}
```

## Filters

```php
#[AsFilter( 'the_content' )]
private function content( string $content ): string {
	return $content . '<hr>';
}

#[AsFilter( 'the_title', priority: 20, accepted_args: 2 )]
private function title( string $title, int $id ): string {
	return $title . '#' . $id;
}
```

A filter is handed a value and returns the one that replaces it, so it takes at least one parameter and does not return `void`. Both are checked while the container is built.

## Shortcodes

```php
#[AsShortCode( 'year' )]
private static function year(): string {
	return date( 'Y' );
}

#[AsShortCode( 'notice' )]
private function notice( array $atts, ?string $content, string $tag ): string {
	return sprintf( '<p class="%s">%s</p>', $atts['class'] ?? '', $content ?? '' );
}
```

A shortcode is always handed three arguments — the attributes, the enclosed content or
`null`, and the tag itself — and a method that declares fewer simply ignores the rest.
There is no priority and no argument count, because WordPress holds neither.

## Priority and arguments

```php
#[AsFilter( 'the_title', priority: 20 )]          // lower runs first, 10 by default
#[AsAction( 'save_post', accepted_args: 3 )]      // written
#[AsAction( 'save_post' )]                        // read off the signature
```

`accepted_args` is read off the method's signature unless you write it — and has to be
written for a variadic method, which no signature can answer for. Two handlers on one
hook at one priority reach WordPress in an order this bundle does not guarantee; write a
priority when it matters.

A handler cannot take a parameter by reference. It receives its arguments by value, so the
reference would be dropped in silence — which is why declaring one stops the build.

## Visibility, and static

```php
#[AsAction( 'init' )]
private function a(): void {}      // private, and the reason this package exists

#[AsAction( 'init' )]
protected function b(): void {}    // protected

#[AsAction( 'init' )]
public function c(): void {}       // public

#[AsAction( 'init' )]
private static function d(): void {}   // static: the class is never built at all
```

A static handler is called on the class, so it never causes anything to be constructed —
not on boot, not on the first hook that fires, not ever.

## A class you cannot mark

A service with autoconfiguration turned off, or a class from a package you do not own, is
reached with a tag instead. The tag does exactly what the attribute does.

```php
// config/services.php
$services->set( SomeVendor\Legacy::class )
	->autoconfigure( false )
	->tag( 'hook.handler' );
```

## The class form

Classes extending `OffsetWP\Hook\Support\Action`, `…\Filter` or `…\ShortCode` keep working
and need no tag from you, as long as autoconfiguration is on for them:

```php
final class Announcements extends \OffsetWP\Hook\Support\Action {

	public string $hook_name = 'wp_footer';

	protected function handle(): void {
		// …
	}
}
```

Their constructor *is* the registration, so every one of them is built on every request,
whether or not its hook ever fires. That is the cost the attributes exist to remove.

## Static analysis

Nothing in PHP calls a hook handler, so a static analyser reports every private one as dead
code. This package ships the extension that answers that, for PHPStan 2:

```neon
# phpstan.neon
includes:
	- vendor/offsetwp/hook-bundle/extension.neon
```

With `phpstan/extension-installer` you already have it, and the `includes` above is one
line you do not need.
