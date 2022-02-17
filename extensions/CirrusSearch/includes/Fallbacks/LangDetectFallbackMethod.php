<?php

namespace CirrusSearch\Fallbacks;

use CirrusSearch\InterwikiResolver;
use CirrusSearch\LanguageDetector\Detector;
use CirrusSearch\LanguageDetector\LanguageDetectorFactory;
use CirrusSearch\Parser\BasicQueryClassifier;
use CirrusSearch\Search\CirrusSearchResultSet;
use CirrusSearch\Search\SearchMetricsProvider;
use CirrusSearch\Search\SearchQuery;
use CirrusSearch\Search\SearchQueryBuilder;
use CirrusSearch\SearchConfig;
use CirrusSearch\Searcher;
use Wikimedia\Assert\Assert;

class LangDetectFallbackMethod implements FallbackMethod, SearchMetricsProvider {
	use FallbackMethodTrait;

	/**
	 * @var SearchQuery
	 */
	private $query;

	/**
	 * @var SearcherFactory
	 */
	private $searcherFactory;

	/**
	 * @var array|null
	 */
	private $searchMetrics = [];

	/**
	 * @var Detector[]
	 */
	private $detectors;

	/**
	 * @var InterwikiResolver
	 */
	private $interwikiResolver;

	/**
	 * @var SearchConfig|null
	 */
	private $detectedLangWikiConfig;

	/**
	 * @var int
	 */
	private $threshold;

	/**
	 * Do not use this constructor outside of tests!
	 * @param SearchQuery $query
	 * @param Detector[] $detectors
	 * @param InterwikiResolver $interwikiResolver
	 */
	public function __construct(
		SearchQuery $query,
		array $detectors,
		InterwikiResolver $interwikiResolver
	) {
		Assert::precondition( $query->getCrossSearchStrategy()->isCrossLanguageSearchSupported(),
			"Cross language search must be supported for this query" );
		$this->query = $query;
		$this->detectors = $detectors;
		$this->interwikiResolver = $interwikiResolver;
		$this->threshold = $query->getSearchConfig()->get( 'CirrusSearchInterwikiThreshold' );
	}

	/**
	 * @param SearchQuery $query
	 * @param array $params
	 * @param InterwikiResolver $interwikiResolver
	 * @return FallbackMethod|null
	 */
	public static function build( SearchQuery $query, array $params, InterwikiResolver $interwikiResolver ) {
		if ( !$query->getCrossSearchStrategy()->isCrossLanguageSearchSupported() ) {
			return null;
		}
		$langDetectFactory = new LanguageDetectorFactory( $query->getSearchConfig() );
		return new self( $query, $langDetectFactory->getDetectors(), $interwikiResolver );
	}

	/**
	 * @param FallbackRunnerContext $context
	 * @return float
	 */
	public function successApproximation( FallbackRunnerContext $context ) {
		$firstPassResults = $context->getInitialResultSet();
		if ( !$this->query->isAllowRewrite() ) {
			return 0.0;
		}
		if ( $this->resultsThreshold( $firstPassResults, $this->threshold ) ) {
			return 0.0;
		}
		if ( !$this->query->getParsedQuery()->isQueryOfClass( BasicQueryClassifier::SIMPLE_BAG_OF_WORDS ) ) {
			return 0.0;
		}
		foreach ( $this->detectors as $name => $detector ) {
			$lang = $detector->detect( $this->query->getParsedQuery()->getRawQuery() );
			if ( $lang === null ) {
				continue;
			}
			if ( $lang === $this->query->getSearchConfig()->get( 'LanguageCode' ) ) {
				// The query is in the wiki language so we
				// don't need to actually try another wiki.
				// Note that this may not be very accurate for
				// wikis that use deprecated language codes
				// but the interwiki resolver should not return
				// ourselves.
				continue;
			}
			$iwPrefixAndConfig = $this->interwikiResolver->getSameProjectConfigByLang( $lang );
			if ( !empty( $iwPrefixAndConfig ) ) {
				// it might be more accurate to attach these to the 'next'
				// log context? It would be inconsistent with the
				// langdetect => false condition which does not have a next
				// request though.
				Searcher::appendLastLogPayload( 'langdetect', $name );
				$prefix = key( $iwPrefixAndConfig );
				$config = $iwPrefixAndConfig[$prefix];
				$metric = [ $config->getWikiId(), $prefix ];
				$this->detectedLangWikiConfig = $config;
				return 0.5;
			}
		}
		Searcher::appendLastLogPayload( 'langdetect', 'failed' );
		return 0.0;
	}

	/**
	 * @param FallbackRunnerContext $context
	 * @return FallbackStatus
	 */
	public function rewrite( FallbackRunnerContext $context ): FallbackStatus {
		$previousSet = $context->getPreviousResultSet();
		Assert::precondition( $this->detectedLangWikiConfig !== null,
			'nothing has been detected, this should not even be tried.' );

		if ( $this->resultsThreshold( $previousSet, $this->threshold ) ) {
			return FallbackStatus::noSuggestion();
		}

		if ( !$context->costlyCallAllowed() ) {
			return FallbackStatus::noSuggestion();
		}

		$crossLangQuery = SearchQueryBuilder::forCrossLanguageSearch( $this->detectedLangWikiConfig,
			$this->query )->build();
		$searcher = $context->makeSearcher( $crossLangQuery );
		$status = $searcher->search( $crossLangQuery );
		if ( !$status->isOK() ) {
			return FallbackStatus::noSuggestion();
		}
		$crossLangResults = $status->getValue();
		if ( !$crossLangResults instanceof CirrusSearchResultSet ) {
			// NOTE: Can/should this happen?
			return FallbackStatus::noSuggestion();
		}
		if ( $crossLangResults->numRows() > 0 ) {
			return FallbackStatus::addInterwikiResults( $crossLangResults,
				$this->detectedLangWikiConfig->getWikiId() );
		}
		return FallbackStatus::noSuggestion();
	}

	public function getMetrics() {
		return $this->searchMetrics;
	}
}
