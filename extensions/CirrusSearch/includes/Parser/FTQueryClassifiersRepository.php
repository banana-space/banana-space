<?php

namespace CirrusSearch\Parser;

use CirrusSearch\Parser\AST\ParsedQuery;
use CirrusSearch\SearchConfig;
use Wikimedia\Assert\Assert;

/**
 * Repository of query classifiers
 */
class FTQueryClassifiersRepository implements ParsedQueryClassifiersRepository {
	/**
	 * @var ParsedQueryClassifier[]
	 */
	private $classifiers = [];

	/**
	 * @var SearchConfig
	 */
	private $config;

	/**
	 * @var bool
	 */
	private $frozen = false;

	/**
	 * @param SearchConfig $config
	 * @throws ParsedQueryClassifierException
	 */
	public function __construct( SearchConfig $config ) {
		$this->config = $config;
		$this->registerClassifier( new BasicQueryClassifier() );
		\Hooks::run( 'CirrusSearchRegisterFullTextQueryClassifiers', [ $this ] );
		$this->frozen = true;
	}

	/**
	 * @param ParsedQueryClassifier $classifier
	 * @throws ParsedQueryClassifierException
	 */
	public function registerClassifier( ParsedQueryClassifier $classifier ) {
		if ( $this->frozen ) {
			throw new ParsedQueryClassifierException( 'Repository is frozen' );
		}
		foreach ( $classifier->classes() as $class ) {
			if ( array_key_exists( $class, $this->classifiers ) ) {
				throw new ParsedQueryClassifierException( "Classifier with $class already registered" );
			}
			$this->classifiers[$class] = $classifier;
		}
	}

	/**
	 * @param string[] $classes list of $classes that this classifier can produce
	 * @param callable $callable called as ParsedQueryClassifier::classify( ParsedQuery $query )
	 * @throws ParsedQueryClassifierException
	 * @see ParsedQueryClassifier::classify()
	 */
	public function registerClassifierAsCallable( array $classes, callable $callable ) {
		Assert::parameter( $classes !== [], '$classes', 'A classifier must support at least one class' );
		$factory = new class( $classes, $callable ) implements ParsedQueryClassifier {
			/**
			 * @var string[]
			 */
			private $classes;

			/**
			 * @var callable
			 */
			private $callable;

			/**
			 * @param string[] $classes
			 * @param callable $callable
			 */
			public function __construct( $classes, callable $callable ) {
				$this->classes = $classes;
				$this->callable = $callable;
			}

			/**
			 * @param ParsedQuery $query
			 * @return string[]
			 */
			public function classify( ParsedQuery $query ) {
				return ( $this->callable )( $query );
			}

			/**
			 * @return string[]
			 */
			public function classes() {
				return $this->classes;
			}
		};
		$this->registerClassifier( $factory );
	}

	/**
	 * The host wiki SearchConfig
	 * @return SearchConfig
	 */
	public function getConfig() {
		return $this->config;
	}

	/**
	 * @param string $name
	 * @return ParsedQueryClassifier
	 * @throws ParsedQueryClassifierException
	 */
	public function getClassifier( $name ) {
		if ( array_key_exists( $name, $this->classifiers ) ) {
			return $this->classifiers[$name];
		}
		throw new ParsedQueryClassifierException( "Classifier $name not found" );
	}

	/**
	 * List known classifiers
	 * @return string[]
	 */
	public function getKnownClassifiers() {
		return array_keys( $this->classifiers );
	}
}
