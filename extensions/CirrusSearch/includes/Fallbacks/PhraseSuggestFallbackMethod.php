<?php

namespace CirrusSearch\Fallbacks;

use CirrusSearch\InterwikiResolver;
use CirrusSearch\OtherIndexesUpdater;
use CirrusSearch\Parser\AST\Visitor\QueryFixer;
use CirrusSearch\Parser\BasicQueryClassifier;
use CirrusSearch\Profile\SearchProfileException;
use CirrusSearch\Profile\SearchProfileService;
use CirrusSearch\Search\CirrusSearchResultSet;
use CirrusSearch\Search\SearchQuery;
use CirrusSearch\Searcher;
use HtmlArmor;
use Wikimedia\Assert\Assert;

/**
 * Fallback method based on the elastic phrase suggester.
 */
class PhraseSuggestFallbackMethod implements FallbackMethod, ElasticSearchSuggestFallbackMethod {
	use FallbackMethodTrait;

	/**
	 * @var SearchQuery
	 */
	private $query;

	/**
	 * @var QueryFixer
	 */
	private $queryFixer;

	/**
	 * @var string
	 */
	private $profileName;

	/**
	 * @var array|null settings (lazy loaded)
	 */
	private $profile;

	/**
	 * @param SearchQuery $query
	 * @param string $profileName name of the profile to use (null to use the defaults provided by the ProfileService)
	 */
	private function __construct( SearchQuery $query, $profileName ) {
		Assert::precondition( $query->isWithDYMSuggestion() &&
							  $query->getSearchConfig()->get( 'CirrusSearchEnablePhraseSuggest' ) &&
							  $query->getOffset() == 0, "Unsupported query" );
		$this->query = $query;
		$this->queryFixer = QueryFixer::build( $query->getParsedQuery() );
		$this->profileName = $profileName;
	}

	/**
	 * @param SearchQuery $query
	 * @param array $params
	 * @param InterwikiResolver|null $interwikiResolver
	 * @return FallbackMethod|null
	 */
	public static function build( SearchQuery $query, array $params, InterwikiResolver $interwikiResolver = null ) {
		if ( !$query->isWithDYMSuggestion() ) {
			return null;
		}
		if ( !$query->getSearchConfig()->get( 'CirrusSearchEnablePhraseSuggest' ) ) {
			return null;
		}
		// TODO: Should this be tested at an upper level?
		if ( $query->getOffset() !== 0 ) {
			return null;
		}
		if ( !isset( $params['profile'] ) ) {
			throw new SearchProfileException( "Missing mandatory parameter 'profile'" );
		}
		return new self( $query, $params['profile'] );
	}

	/**
	 * @param FallbackRunnerContext $context
	 * @return float
	 */
	public function successApproximation( FallbackRunnerContext $context ) {
		$firstPassResults = $context->getInitialResultSet();
		if ( !$this->haveSuggestion( $firstPassResults ) ) {
			return 0.0;
		}

		if ( $this->resultContainsFullyHighlightedMatch( $firstPassResults->getElasticaResultSet() ) ) {
			return 0.0;
		}

		if ( $this->totalHitsThresholdMet( $firstPassResults->getTotalHits() ) ) {
			return 0.0;
		}

		return 0.5;
	}

	/**
	 * @param FallbackRunnerContext $context
	 * @return FallbackStatus
	 */
	public function rewrite( FallbackRunnerContext $context ): FallbackStatus {
		$firstPassResults = $context->getInitialResultSet();
		$previousSet = $context->getPreviousResultSet();
		if ( $previousSet->getQueryAfterRewrite() !== null ) {
			// a method rewrote the query before us.
			return FallbackStatus::noSuggestion();
		}
		if ( $previousSet->getSuggestionQuery() !== null ) {
			// a method suggested something before us
			return FallbackStatus::noSuggestion();
		}

		list( $suggestion, $highlight ) = $this->fixDYMSuggestion( $firstPassResults );

		if ( !$context->costlyCallAllowed()
			|| !$this->query->isAllowRewrite()
			|| $this->resultsThreshold( $previousSet )
			|| !$this->query->getParsedQuery()->isQueryOfClass( BasicQueryClassifier::SIMPLE_BAG_OF_WORDS )
		) {
			// Can't perform a full rewrite currently, simply suggest the query.
			return FallbackStatus::suggestQuery( $suggestion, $highlight );
		}

		return $this->maybeSearchAndRewrite( $context, $this->query,
			$suggestion, $highlight );
	}

	/**
	 * @param CirrusSearchResultSet $resultSet
	 * @return bool
	 */
	public function haveSuggestion( CirrusSearchResultSet $resultSet ) {
		return $this->findSuggestion( $resultSet ) !== null;
	}

	private function fixDYMSuggestion( CirrusSearchResultSet $fromResultSet ) {
		$suggestion = $this->findSuggestion( $fromResultSet );
		Assert::precondition( $suggestion !== null, "fixDYMSuggestion called with no suggestions available" );
		return [
			$this->queryFixer->fix( $suggestion['text'] ),
			$this->queryFixer->fix( $this->escapeHighlightedSuggestion( $suggestion['highlighted'] ) )
		];
	}

	/**
	 * Escape a highlighted suggestion coming back from Elasticsearch.
	 *
	 * @param string $suggestion suggestion from elasticsearch
	 * @return HtmlArmor $suggestion with html escaped _except_ highlighting pre and post tags
	 */
	private function escapeHighlightedSuggestion( string $suggestion ): HtmlArmor {
		return new HtmlArmor( strtr( htmlspecialchars( $suggestion ), [
			Searcher::HIGHLIGHT_PRE_MARKER => Searcher::SUGGESTION_HIGHLIGHT_PRE,
			Searcher::HIGHLIGHT_POST_MARKER => Searcher::SUGGESTION_HIGHLIGHT_POST,
		] ) );
	}

	/**
	 * @param int $totalHits
	 * @return bool
	 */
	private function totalHitsThresholdMet( $totalHits ) {
		$threshold = $this->getProfile()['total_hits_threshold'] ?? -1;
		return $threshold >= 0 && $totalHits > $threshold;
	}

	/**
	 * @param CirrusSearchResultSet $resultSet
	 * @return array|null Suggestion options, see "options" part in
	 *      https://www.elastic.co/guide/en/elasticsearch/reference/6.4/search-suggesters.html
	 */
	private function findSuggestion( CirrusSearchResultSet $resultSet ) {
		// TODO some kind of weighting?
		$response = $resultSet->getElasticResponse();
		if ( $response === null ) {
			return null;
		}
		$suggest = $response->getData();
		if ( !isset( $suggest[ 'suggest' ] ) ) {
			return null;
		}
		$suggest = $suggest[ 'suggest' ];
		// Elasticsearch will send back the suggest element but no sub suggestion elements if the wiki is empty.
		// So we should check to see if they exist even though in normal operation they always will.
		if ( isset( $suggest['suggest'][0] ) ) {
			foreach ( $suggest['suggest'][0][ 'options' ] as $option ) {
				return $option;
			}
		}
		return null;
	}

	/**
	 * @return array|null
	 */
	public function getSuggestQueries() {
		$term = $this->queryFixer->getFixablePart();
		if ( $term !== null ) {
			return [
				'suggest' => [
					'text' => $term,
					'suggest' => $this->buildSuggestConfig(),
				]
			];
		}
		return null;
	}

	/**
	 * Build suggest config for 'suggest' field.
	 *
	 * @return array[] array of Elastica configuration
	 */
	private function buildSuggestConfig() {
		$field = 'suggest';
		$config = $this->query->getSearchConfig();
		$suggestSettings = $this->getProfile();
		$settings = [
			'phrase' => [
				'field' => $field,
				'size' => 1,
				'max_errors' => $suggestSettings['max_errors'],
				'confidence' => $suggestSettings['confidence'],
				'real_word_error_likelihood' => $suggestSettings['real_word_error_likelihood'],
				'direct_generator' => [
					[
						'field' => $field,
						'suggest_mode' => $suggestSettings['mode'],
						'max_term_freq' => $suggestSettings['max_term_freq'],
						'min_doc_freq' => $suggestSettings['min_doc_freq'],
						'prefix_length' => $suggestSettings['prefix_length'],
					],
				],
				'highlight' => [
					'pre_tag' => Searcher::HIGHLIGHT_PRE_MARKER,
					'post_tag' => Searcher::HIGHLIGHT_POST_MARKER,
				],
			],
		];
		// Add a second generator with the reverse field
		// Only do this for local queries, we don't know if it's activated
		// on other wikis.
		if ( $config->getElement( 'CirrusSearchPhraseSuggestReverseField', 'use' )
			&& ( !$this->query->getCrossSearchStrategy()->isExtraIndicesSearchSupported()
				|| empty( OtherIndexesUpdater::getExtraIndexesForNamespaces(
					$config,
					$this->query->getNamespaces()
				)
			 ) )
		) {
			$settings['phrase']['direct_generator'][] = [
				'field' => $field . '.reverse',
				'suggest_mode' => $suggestSettings['mode'],
				'max_term_freq' => $suggestSettings['max_term_freq'],
				'min_doc_freq' => $suggestSettings['min_doc_freq'],
				'prefix_length' => $suggestSettings['prefix_length'],
				'pre_filter' => 'token_reverse',
				'post_filter' => 'token_reverse'
			];
		}
		if ( !empty( $suggestSettings['collate'] ) ) {
			$collateFields = [ 'title.plain', 'redirect.title.plain' ];
			if ( $config->get( 'CirrusSearchPhraseSuggestUseText' ) ) {
				$collateFields[] = 'text.plain';
			}
			$settings['phrase']['collate'] = [
				'query' => [
					'inline' => [
						'multi_match' => [
							'query' => '{{suggestion}}',
							'operator' => 'or',
							'minimum_should_match' => $suggestSettings['collate_minimum_should_match'],
							'type' => 'cross_fields',
							'fields' => $collateFields
						],
					],
				],
			];
		}
		if ( isset( $suggestSettings['smoothing_model'] ) ) {
			$settings['phrase']['smoothing'] = $suggestSettings['smoothing_model'];
		}

		return $settings;
	}

	/**
	 * @return array
	 */
	private function getProfile() {
		if ( $this->profile === null ) {
			$this->profile = $this->query->getSearchConfig()->getProfileService()
				->loadProfileByName( SearchProfileService::PHRASE_SUGGESTER,
					$this->profileName );
		}
		return $this->profile;
	}
}
