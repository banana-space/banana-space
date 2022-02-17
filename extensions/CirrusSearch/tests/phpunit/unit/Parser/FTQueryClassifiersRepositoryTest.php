<?php

namespace CirrusSearch\Parser;

use CirrusSearch\CirrusTestCase;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\Parser\AST\ParsedQuery;

/**
 * @covers \CirrusSearch\Parser\FTQueryClassifiersRepository
 * @group CirrusSearch
 */
class FTQueryClassifiersRepositoryTest extends CirrusTestCase {

	/**
	 * @throws ParsedQueryClassifierException
	 */
	public function testGetConfig() {
		$config = new HashSearchConfig( [] );
		$repo = new FTQueryClassifiersRepository( $config );
		$this->assertSame( $config, $repo->getConfig() );
	}

	/**
	 * @throws ParsedQueryClassifierException
	 */
	public function testDefaultClassifiers() {
		$config = new HashSearchConfig( [] );
		$repo = new FTQueryClassifiersRepository( $config );
		$defaults = [
			BasicQueryClassifier::SIMPLE_BAG_OF_WORDS,
			BasicQueryClassifier::SIMPLE_PHRASE,
			BasicQueryClassifier::BAG_OF_WORDS_WITH_PHRASE,
			BasicQueryClassifier::COMPLEX_QUERY,
			BasicQueryClassifier::BOGUS_QUERY,
		];
		foreach ( $defaults as $default ) {
			$this->assertInstanceOf( ParsedQueryClassifier::class,
				$repo->getClassifier( $default ) );
			$this->assertContains( $default, $repo->getKnownClassifiers() );
		}

		try {
			$repo->getClassifier( 'unknown_classifier_for_testing' );
		} catch ( ParsedQueryClassifierException $e ) {
			$this->assertEquals( 'Classifier unknown_classifier_for_testing not found', $e->getMessage() );
		}
	}

	public function testRegister() {
		$config = new HashSearchConfig( [] );
		$this->setTemporaryHook( 'CirrusSearchRegisterFullTextQueryClassifiers',
			function ( FTQueryClassifiersRepository $repository ) {
				$repository->registerClassifierAsCallable( [ 'hook1' ],
					function ( ParsedQuery $query ) {
						return [ 'hook1' ];
					}
				);
				$repository->registerClassifier(
					new class implements ParsedQueryClassifier {
						public function classify( ParsedQuery $query ) {
							return [ 'hook2' ];
						}

						/**
						 * @return string[]
						 */
						public function classes() {
							return [ 'hook2' ];
						}
					}
				);

				try {
					$repository->registerClassifier(
						new class implements ParsedQueryClassifier {
							public function classify( ParsedQuery $query ) {
								return [ 'hook2' ];
							}

							public function classes() {
								return [ 'hook2' ];
							}
						}
					);
					$this->fail( 'failure should occur when registering duplicates' );
				} catch ( ParsedQueryClassifierException $e ) {
					$this->assertEquals( 'Classifier with hook2 already registered', $e->getMessage() );
				}

				try {
					$repository->registerClassifierAsCallable( [ 'hook1' ],
						function ( ParsedQuery $query ) {
							return [ 'hook1' ];
						}
					);
					$this->fail( 'failure should occur when registering duplicates' );
				} catch ( ParsedQueryClassifierException $e ) {
					$this->assertEquals( 'Classifier with hook1 already registered', $e->getMessage() );
				}
			}
		);
		$repo = new FTQueryClassifiersRepository( $config );
		foreach ( [ 'hook1', 'hook2' ] as $name ) {
			$this->assertInstanceOf( ParsedQueryClassifier::class,
				$repo->getClassifier( $name ) );
			$this->assertContains( $name, $repo->getKnownClassifiers() );
		}

		try {
			$repo->registerClassifierAsCallable( [ 'hook3' ], function () {
				$this->fail( "Cannot be called" );
			} );
			$this->fail( "the repository must not accept a new classifier when it's frozen." );
		} catch ( ParsedQueryClassifierException $e ) {
			$this->assertEquals( 'Repository is frozen', $e->getMessage() );
		}
	}
}
