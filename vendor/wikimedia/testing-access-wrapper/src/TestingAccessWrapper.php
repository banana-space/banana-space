<?php

namespace Wikimedia;

use DomainException;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Circumvent access restrictions on object internals
 *
 * This can be helpful for writing tests that can probe object internals,
 * without having to modify the class under test to accommodate.
 *
 * Wrap an object with private methods as follows:
 *    $title = TestingAccessWrapper::newFromObject( Title::newFromDBkey( $key ) );
 *
 * You can access private and protected instance methods and variables:
 *    $formatter = $title->getTitleFormatter();
 *
 * You can access private and protected constants:
 *    $value = TestingAccessWrapper::constant( Foo::class, 'FOO_CONSTANT' );
 *
 */
class TestingAccessWrapper {
	/** @var mixed The object, or the class name for static-only access */
	public $object;

	/**
	 * Return a proxy object which can be used the same way as the original,
	 * except that access restrictions can be ignored (protected and private methods and properties
	 * are available for any caller).
	 * @param object $object
	 * @return self
	 * @throws InvalidArgumentException
	 */
	public static function newFromObject( $object ) {
		if ( !is_object( $object ) ) {
			throw new InvalidArgumentException( __METHOD__ . ' must be called with an object' );
		}
		$wrapper = new self();
		$wrapper->object = $object;
		return $wrapper;
	}

	/**
	 * Allow access to non-public static methods and properties of the class.
	 * Returns an object whose methods/properties will correspond to the
	 * static methods/properties of the given class.
	 * @param string $className
	 * @return self
	 * @throws InvalidArgumentException
	 */
	public static function newFromClass( $className ) {
		if ( !is_string( $className ) ) {
			throw new InvalidArgumentException( __METHOD__ . ' must be called with a class name' );
		}
		$wrapper = new self();
		$wrapper->object = $className;
		return $wrapper;
	}

	/**
	 * Allow access to non-public constants of the class.
	 * @param class-string $className
	 * @param string $constantName
	 * @return mixed
	 */
	public static function constant( $className, $constantName ) {
		$classReflection = new ReflectionClass( $className );
		// getConstant() returns `false` if the constant is defined in
		// a parent class; this works more like ReflectionClass::getMethod()
		while ( !$classReflection->hasConstant( $constantName ) ) {
			$classReflection = $classReflection->getParentClass();
			if ( !$classReflection ) {
				throw new \ReflectionException( 'constant not present' );
			}
		}
		return $classReflection->getConstant( $constantName );
	}

	/**
	 * @param string $method
	 * @param array $args
	 * @return mixed
	 */
	public function __call( $method, $args ) {
		$methodReflection = $this->getMethod( $method );

		if ( $this->isStatic() && !$methodReflection->isStatic() ) {
			throw new DomainException( __METHOD__
				. ': Cannot call non-static method when wrapping static class' );
		}

		return $methodReflection->invokeArgs( $methodReflection->isStatic() ? null : $this->object,
			$args );
	}

	/**
	 * @param string $name
	 * @param mixed $value
	 */
	public function __set( $name, $value ) {
		$propertyReflection = $this->getProperty( $name );

		if ( $this->isStatic() && !$propertyReflection->isStatic() ) {
			throw new DomainException( __METHOD__
				. ': Cannot set non-static property when wrapping static class' );
		}

		$propertyReflection->setValue( $this->object, $value );
	}

	/**
	 * @param string $name Field name
	 * @return mixed
	 */
	public function __get( $name ) {
		$propertyReflection = $this->getProperty( $name );

		if ( $this->isStatic() && !$propertyReflection->isStatic() ) {
			throw new DomainException( __METHOD__
				. ': Cannot get non-static property when wrapping static class' );
		}

		if ( $propertyReflection->isStatic() ) {
			// https://bugs.php.net/bug.php?id=69804 - can't use getStaticPropertyValue() on
			// non-public properties
			$class = new ReflectionClass( $this->object );
			$props = $class->getStaticProperties();

			// Can't use isset() as it returns false for null values
			if ( !array_key_exists( $name, $props ) ) {
				throw new DomainException( __METHOD__ . ": class {$class->name} "
					. "doesn't have static property '{$name}'" );
			}
			return $props[$name];
		}

		return $propertyReflection->getValue( $this->object );
	}

	/**
	 * Tells whether this object was created for an object or a class.
	 * @return bool
	 */
	private function isStatic() {
		return is_string( $this->object );
	}

	/**
	 * Return a method and make it accessible.
	 * @param string $name
	 * @return ReflectionMethod
	 * @throws ReflectionException
	 */
	private function getMethod( $name ) {
		$classReflection = new ReflectionClass( $this->object );
		$methodReflection = $classReflection->getMethod( $name );
		$methodReflection->setAccessible( true );
		return $methodReflection;
	}

	/**
	 * Return a property and make it accessible.
	 *
	 * ReflectionClass::getProperty() fails if the private property is defined
	 * in a parent class. This works more like ReflectionClass::getMethod().
	 *
	 * @param string $name
	 * @return ReflectionProperty
	 * @throws ReflectionException
	 */
	private function getProperty( $name ) {
		$classReflection = new ReflectionClass( $this->object );
		try {
			$propertyReflection = $classReflection->getProperty( $name );
		} catch ( ReflectionException $ex ) {
			while ( true ) {
				$classReflection = $classReflection->getParentClass();
				if ( !$classReflection ) {
					throw $ex;
				}
				try {
					$propertyReflection = $classReflection->getProperty( $name );
				} catch ( ReflectionException $ex2 ) {
					continue;
				}
				if ( $propertyReflection->isPrivate() ) {
					break;
				} else {
					// @codeCoverageIgnoreStart
					throw $ex;
					// @codeCoverageIgnoreEnd
				}
			}
		}
		$propertyReflection->setAccessible( true );
		return $propertyReflection;
	}
}
