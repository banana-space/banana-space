<?php

namespace CirrusSearch\Parser;

use CirrusSearch\SearchConfig;

/**
 * Repository of query classifiers
 */
interface ParsedQueryClassifiersRepository {
	/**
	 * @param ParsedQueryClassifier $classifier
	 * @throws ParsedQueryClassifierException
	 */
	public function registerClassifier( ParsedQueryClassifier $classifier );

	/**
	 * @param string[] $classes list of classes that this classifier can produce
	 * @param callable $callable called as ParsedQueryClassifier::classify( ParsedQuery $query )
	 * @throws ParsedQueryClassifierException
	 * @see ParsedQueryClassifier::classify()
	 */
	public function registerClassifierAsCallable( array $classes, callable $callable );

	/**
	 * The host wiki SearchConfig
	 * @return SearchConfig
	 */
	public function getConfig();

	/**
	 * @param string $name
	 * @return ParsedQueryClassifier
	 */
	public function getClassifier( $name );

	/**
	 * List known classifiers
	 * @return string[]
	 */
	public function getKnownClassifiers();
}
