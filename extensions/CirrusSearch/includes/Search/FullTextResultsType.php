<?php

namespace CirrusSearch\Search;

use CirrusSearch\Search\Fetch\FetchPhaseConfigBuilder;
use Elastica\ResultSet as ElasticaResultSet;

/**
 * Result type for a full text search.
 */
final class FullTextResultsType extends BaseResultsType {
	/**
	 * @var bool
	 */
	private $searchContainedSyntax;

	/**
	 * @var FetchPhaseConfigBuilder
	 */
	private $fetchPhaseBuilder;
	/**
	 * @var TitleHelper
	 */
	private $titleHelper;

	/**
	 * @param FetchPhaseConfigBuilder $fetchPhaseBuilder
	 * @param bool $searchContainedSyntax
	 * @param TitleHelper $titleHelper
	 */
	public function __construct(
		FetchPhaseConfigBuilder $fetchPhaseBuilder,
		$searchContainedSyntax,
		TitleHelper $titleHelper
	) {
		$this->fetchPhaseBuilder = $fetchPhaseBuilder;
		$this->searchContainedSyntax = $searchContainedSyntax;
		$this->titleHelper = $titleHelper;
	}

	/**
	 * @return false|string|array corresponding to Elasticsearch source filtering syntax
	 */
	public function getSourceFiltering() {
		return array_merge(
			parent::getSourceFiltering(),
			[ 'redirect.*', 'timestamp', 'text_bytes' ]
		);
	}

	/**
	 * @return array
	 */
	public function getStoredFields() {
		return [ "text.word_count" ]; // word_count is only a stored field and isn't part of the source.
	}

	/**
	 * Setup highlighting.
	 * Don't fragment title because it is small.
	 * Get just one fragment from the text because that is all we will display.
	 * Get one fragment from redirect title and heading each or else they
	 * won't be sorted by score.
	 *
	 * @param array $extraHighlightFields (deprecated and ignored)
	 * @return array|null of highlighting configuration
	 */
	public function getHighlightingConfiguration( array $extraHighlightFields = [] ) {
		$this->fetchPhaseBuilder->configureDefaultFullTextFields();
		return $this->fetchPhaseBuilder->buildHLConfig();
	}

	/**
	 * @param ElasticaResultSet $result
	 * @return CirrusSearchResultSet
	 */
	public function transformElasticsearchResult( ElasticaResultSet $result ) {
		// Should we make this a concrete class?
		return new class( $this->titleHelper, $this->fetchPhaseBuilder, $result, $this->searchContainedSyntax )
				extends BaseCirrusSearchResultSet {
			/** @var TitleHelper */
			private $titleHelper;
			/** @var FullTextCirrusSearchResultBuilder */
			private $resultBuilder;
			/** @var ElasticaResultSet */
			private $results;
			/** @var bool */
			private $searchContainedSyntax;

			public function __construct(
				TitleHelper $titleHelper,
				FetchPhaseConfigBuilder $builder,
				ElasticaResultSet $results,
				$searchContainedSyntax
			) {
				$this->titleHelper = $titleHelper;
				$this->resultBuilder = new FullTextCirrusSearchResultBuilder( $this->titleHelper,
					$builder->getHLFieldsPerTargetAndPriority() );
				$this->results = $results;
				$this->searchContainedSyntax = $searchContainedSyntax;
			}

			/**
			 * @inheritDoc
			 */
			protected function transformOneResult( \Elastica\Result $result ) {
				return $this->resultBuilder->build( $result );
			}

			/**
			 * @return \Elastica\ResultSet|null
			 */
			public function getElasticaResultSet() {
				return $this->results;
			}

			/**
			 * @inheritDoc
			 */
			public function searchContainedSyntax() {
				return $this->searchContainedSyntax;
			}

			protected function getTitleHelper(): TitleHelper {
				return $this->titleHelper;
			}
		};
	}

	/**
	 * @param FetchPhaseConfigBuilder $builder
	 * @return FullTextResultsType
	 */
	public function withFetchPhaseBuilder( FetchPhaseConfigBuilder $builder ): FullTextResultsType {
		return new self( $builder, $this->searchContainedSyntax, $this->titleHelper );
	}

	/**
	 * @return CirrusSearchResultSet
	 */
	public function createEmptyResult() {
		return BaseCirrusSearchResultSet::emptyResultSet();
	}
}
