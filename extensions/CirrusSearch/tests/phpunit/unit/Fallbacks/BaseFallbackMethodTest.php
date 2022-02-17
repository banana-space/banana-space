<?php

namespace CirrusSearch\Fallbacks;

use CirrusSearch\CirrusTestCase;
use CirrusSearch\Search\BaseCirrusSearchResultSet;
use CirrusSearch\Search\CirrusSearchResultSet;
use CirrusSearch\Search\SearchQuery;
use CirrusSearch\Search\TitleHelper;
use CirrusSearch\Searcher;
use Elastica\Query;
use Elastica\Response;
use Elastica\ResultSet;
use Elastica\ResultSet\DefaultBuilder;

abstract class BaseFallbackMethodTest extends CirrusTestCase {

	public function getSearcherFactoryMock( SearchQuery $query = null, CirrusSearchResultSet $resultSet = null ) {
		$searcherMock = $this->createMock( Searcher::class );
		$searcherMock->expects( $query != null ? $this->once() : $this->never() )
			->method( 'search' )
			->with( $query )
			->willReturn( $resultSet === null ? \Status::newFatal( 'Error' ) : \Status::newGood( $resultSet ) );
		$searcherMock->expects( $query != null ? $this->atMost( 1 ) : $this->never() )
			->method( 'getSearchMetrics' )
			->willReturn( [ 'searcherMetrics' => 'called' ] );

		$mock = $this->createMock( SearcherFactory::class );
		$mock->expects( $this->any() )
			->method( 'makeSearcher' )
			->willReturn( $searcherMock );
		return $mock;
	}

	/**
	 * @param array $response
	 * @param bool $containedSyntax
	 * @param TitleHelper|null $titleHelper
	 * @return CirrusSearchResultSet
	 */
	protected function newResultSet(
		array $response,
		$containedSyntax = false,
		TitleHelper $titleHelper = null
	): CirrusSearchResultSet {
		$titleHelper = $titleHelper ?: $this->newTitleHelper();
		$resultSet = ( new DefaultBuilder() )->buildResultSet( new Response( $response ), new Query() );
		return new class( $resultSet, $titleHelper, $containedSyntax ) extends BaseCirrusSearchResultSet {
			/** @var ResultSet */
			private $resultSet;
			/** @var TitleHelper */
			private $titleHelper;
			/** @var bool */
			private $containedSyntax;

			public function __construct( ResultSet $resultSet, TitleHelper $titleHelper, $containedSyntax ) {
				$this->resultSet = $resultSet;
				$this->titleHelper = $titleHelper;
				$this->containedSyntax = $containedSyntax;
			}

			/**
			 * @inheritDoc
			 */
			protected function transformOneResult( \Elastica\Result $result ) {
				return new \CirrusSearch\Search\Result( null, $result );
			}

			/**
			 * @inheritDoc
			 */
			public function getElasticaResultSet() {
				return $this->resultSet;
			}

			/**
			 * @return bool
			 */
			public function searchContainedSyntax() {
				return $this->containedSyntax;
			}

			protected function getTitleHelper(): TitleHelper {
				return $this->titleHelper;
			}
		};
	}
}
