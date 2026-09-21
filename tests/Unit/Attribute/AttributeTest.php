<?php
/**
 * OffsetWP Hook Bundle Tests
 *
 * @author Jérôme Wohlschlegel
 * @package OffsetWP\Bundle\HookBundle\Tests\Unit\Attribute
 */

declare( strict_types=1 );

namespace OffsetWP\Bundle\HookBundle\Tests\Unit\Attribute;

use OffsetWP\Bundle\HookBundle\Attribute\AsAction;
use OffsetWP\Bundle\HookBundle\Attribute\AsFilter;
use OffsetWP\Bundle\HookBundle\Attribute\AsHookHandler;
use OffsetWP\Bundle\HookBundle\Attribute\AsShortCode;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\Handler\Declarations;
use OffsetWP\Bundle\HookBundle\Tests\Fixtures\HookTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * What the four attributes are, and what reflection sees of them.
 *
 * The whole package rests on one property of PHP that is easy to assume and worth
 * asserting: an attribute on a private method is read exactly as one on a public method.
 * If that ever stopped being true, this is where it would be caught.
 */
#[CoversClass( AsAction::class )]
#[CoversClass( AsFilter::class )]
#[CoversClass( AsShortCode::class )]
#[CoversClass( AsHookHandler::class )]
final class AttributeTest extends HookTestCase {

	/**
	 * One method, two actions, and both are read off a private method.
	 *
	 * @return void
	 */
	public function testAMethodCanDeclareSeveralActions(): void {
		$method = new \ReflectionMethod( Declarations::class, 'boot' );

		$this->assertFalse( $method->isPublic() );

		$actions = array_map(
			static fn ( \ReflectionAttribute $attribute ): AsAction => $attribute->newInstance(),
			$method->getAttributes( AsAction::class )
		);

		$this->assertCount( 2, $actions );
		$this->assertSame( 'init', $actions[0]->name );
		$this->assertSame( 10, $actions[0]->priority );
		$this->assertNull( $actions[0]->accepted_args );
		$this->assertSame( 'wp_loaded', $actions[1]->name );
		$this->assertSame( 5, $actions[1]->priority );
	}

	/**
	 * A filter carries the two settings the platform accepts, written by name.
	 *
	 * @return void
	 */
	public function testAFilterCarriesItsPriorityAndArgumentCount(): void {
		$method = new \ReflectionMethod( Declarations::class, 'title' );

		$this->assertFalse( $method->isPublic() );

		$filter = $method->getAttributes( AsFilter::class )[0]->newInstance();

		$this->assertSame( 'the_title', $filter->name );
		$this->assertSame( 20, $filter->priority );
		$this->assertSame( 2, $filter->accepted_args );
	}

	/**
	 * A shortcode carries a tag and nothing else, because the platform holds nothing
	 * else about one.
	 *
	 * @return void
	 */
	public function testAShortCodeCarriesOnlyItsTag(): void {
		$method = new \ReflectionMethod( Declarations::class, 'year' );

		$this->assertFalse( $method->isPublic() );
		$this->assertTrue( $method->isStatic() );

		$short_code = $method->getAttributes( AsShortCode::class )[0]->newInstance();

		$this->assertSame( 'year', $short_code->name );
		$this->assertSame(
			array( 'name' ),
			array_map(
				static fn ( \ReflectionParameter $parameter ): string => $parameter->getName(),
				( new \ReflectionMethod( AsShortCode::class, '__construct' ) )->getParameters()
			)
		);
	}

	/**
	 * The class attribute is on the class, carries nothing, and is what makes the three
	 * private methods above findable at all.
	 *
	 * @return void
	 */
	public function testTheClassAttributeMarksTheClass(): void {
		$attributes = ( new \ReflectionClass( Declarations::class ) )->getAttributes( AsHookHandler::class );

		$this->assertCount( 1, $attributes );
		$this->assertInstanceOf( AsHookHandler::class, $attributes[0]->newInstance() );
		$this->assertSame( array(), $attributes[0]->getArguments() );
	}

	/**
	 * Each attribute allows what it has to allow: the three method attributes repeat and
	 * go on a method, the class attribute goes on a class and does not repeat.
	 *
	 * @return void
	 */
	public function testEachAttributeAllowsWhatItHasTo(): void {
		foreach ( array( AsAction::class, AsFilter::class, AsShortCode::class ) as $class ) {
			$flags = $this->attributeFlags( $class );

			$this->assertSame( \Attribute::TARGET_METHOD, $flags & \Attribute::TARGET_ALL, $class );
			$this->assertSame( \Attribute::IS_REPEATABLE, $flags & \Attribute::IS_REPEATABLE, $class );
		}

		$flags = $this->attributeFlags( AsHookHandler::class );

		$this->assertSame( \Attribute::TARGET_CLASS, $flags & \Attribute::TARGET_ALL );
		$this->assertSame( 0, $flags & \Attribute::IS_REPEATABLE );
	}

	/**
	 * The flags one attribute class was declared with.
	 *
	 * @param class-string $attribute_class The attribute class.
	 * @return int
	 */
	private function attributeFlags( string $attribute_class ): int {
		$declaration = ( new \ReflectionClass( $attribute_class ) )->getAttributes( \Attribute::class );

		$this->assertCount( 1, $declaration );

		return $declaration[0]->newInstance()->flags;
	}
}
