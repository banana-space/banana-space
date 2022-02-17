<?php

namespace CirrusSearch;

use ApiUsageException;
use CirrusSearch\Parser\NamespacePrefixParser;
use CirrusSearch\Parser\QueryStringRegex\SearchQueryParseException;
use CirrusSearch\Profile\ContextualProfileOverride;
use CirrusSearch\Profile\SearchProfileService;
use CirrusSearch\Search\CirrusSearchIndexFieldFactory;
use CirrusSearch\Search\CirrusSearchResultSet;
use CirrusSearch\Search\FancyTitleResultsType;
use CirrusSearch\Search\SearchMetricsProvider;
use CirrusSearch\Search\SearchQuery;
use CirrusSearch\Search\SearchQueryBuilder;
use CirrusSearch\Search\TitleHelper;
use CirrusSearch\Search\TitleResultsType;
use ISearchResultSet;
use MediaWiki\MediaWikiServices;
use RequestContext;
use SearchEngine;
use SearchIndexField;
use SearchSuggestionSet;
use Status;
use Title;
use User;
use WebRequest;

/**
 * SearchEngine implementation for CirrusSearch.  Delegates to
 * CirrusSearchSearcher for searches and CirrusSearchUpdater for updates.  Note
 * that lots of search behavior is hooked in CirrusSearchHooks rather than
 * overridden here.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */
class CirrusSearch extends SearchEngine {

	/**
	 * Special profile to instruct this class to use profile
	 * selection mechanism.
	 * This allows to defer profile selection to when we actually perform
	 * the search. The reason is that the list of possible profiles
	 * is returned by self::getProfiles so instead of assigning a default
	 * profile at this point we use this special profile.
	 */
	const AUTOSELECT_PROFILE = 'engine_autoselect';

	/** @const string name of the prefixsearch fallback profile */
	const COMPLETION_PREFIX_FALLBACK_PROFILE = 'classic';

	/**
	 * @const int Maximum title length that we'll check in prefix and keyword searches.
	 * Since titles can be 255 bytes in length we're setting this to 255
	 * characters.
	 */
	const MAX_TITLE_SEARCH = 255;

	/**
	 * @var array metrics about the last thing we searched sourced from the
	 *  Searcher instance
	 */
	private $lastSearchMetrics = [];

	/**
	 * @var array additional metrics about the search sourced within this class
	 */
	private $extraSearchMetrics = [];

	/**
	 * @var Connection
	 */
	private $connection;

	/**
	 * Search configuration.
	 * @var SearchConfig immutable
	 */
	private $config;

	/**
	 * Current request.
	 * @var WebRequest
	 */
	private $request;

	/**
	 * @var RequestContext
	 */
	private $requestContext;

	/**
	 * @var CirrusSearchIndexFieldFactory
	 */
	private $searchIndexFieldFactory;

	/**
	 * @var CirrusDebugOptions
	 */
	private $debugOptions;

	/**
	 * @var NamespacePrefixParser
	 */
	private $namespacePrefixParser;

	/**
	 * @var InterwikiResolver
	 */
	private $interwikiResolver;

	/**
	 * @var TitleHelper
	 */
	private $titleHelper;

	/**
	 * @param SearchConfig|null $config
	 * @param CirrusDebugOptions|null $debugOptions
	 * @param NamespacePrefixParser|null $namespacePrefixParser
	 * @param InterwikiResolver|null $interwikiResolver
	 * @param TitleHelper|null $titleHelper
	 */
	public function __construct(
		SearchConfig $config = null,
		CirrusDebugOptions $debugOptions = null,
		NamespacePrefixParser $namespacePrefixParser = null,
		InterwikiResolver $interwikiResolver = null,
		TitleHelper $titleHelper = null
	) {
		// Initialize UserTesting before we create a Connection
		// This is useful to do tests across multiple clusters
		UserTesting::getInstance();
		$this->config = $config ?? MediaWikiServices::getInstance()
			->getConfigFactory()
			->makeConfig( 'CirrusSearch' );
		$this->connection = new Connection( $this->config );
		$this->requestContext = RequestContext::getMain();
		$this->request = $this->requestContext->getRequest();
		$this->searchIndexFieldFactory = new CirrusSearchIndexFieldFactory( $this->config );
		$this->namespacePrefixParser = $namespacePrefixParser ?: new class() implements NamespacePrefixParser {
			public function parse( $query ) {
				return CirrusSearch::parseNamespacePrefixes( $query, true, true );
			}
		};
		$this->interwikiResolver = $interwikiResolver ?: MediaWikiServices::getInstance()->getService( InterwikiResolver::SERVICE );

		// enable interwiki by default
		$this->features['interwiki'] = true;
		$this->features['show-multimedia-search-results'] = $this->config->get( 'CirrusSearchCrossProjectShowMultimedia' ) == true;
		$this->debugOptions = $debugOptions ?? CirrusDebugOptions::fromRequest( $this->request );
		$this->titleHelper = $titleHelper ?: new TitleHelper( wfWikiID(), $interwikiResolver,
			function ( $v ) {
				return \Sanitizer::escapeIdForLink( $v );
			}
		);
	}

	public function setConnection( Connection $connection ) {
		$this->connection = $connection;
	}

	/**
	 * @return Connection
	 */
	public function getConnection() {
		return $this->connection;
	}

	/**
	 * Get search config
	 * @return SearchConfig
	 */
	public function getConfig() {
		return $this->config;
	}

	/**
	 * Override supports to shut off updates to Cirrus via the SearchEngine infrastructure.  Page
	 * updates and additions are chained on the end of the links update job.  Deletes are noticed
	 * via the ArticleDeleteComplete hook.
	 * @param string $feature feature name
	 * @return bool is this feature supported?
	 */
	public function supports( $feature ) {
		switch ( $feature ) {
		case 'search-update':
		case 'list-redirects':
			return false;
		default:
			return parent::supports( $feature );
		}
	}

	/**
	 * Overridden to delegate prefix searching to Searcher.
	 * @param string $term text to search
	 * @return Status Value is either SearchResultSet, or null on error.
	 */
	protected function doSearchText( $term ) {
		try {
			$builder = SearchQueryBuilder::newFTSearchQueryBuilder( $this->config,
				$term, $this->namespacePrefixParser );
		} catch ( SearchQueryParseException $e ) {
			return $e->asStatus();
		}

		$builder->setDebugOptions( $this->debugOptions )
			->setInitialNamespaces( $this->namespaces )
			->setLimit( $this->limit )
			->setOffset( $this->offset )
			->setSort( $this->getSort() )
			->setExtraIndicesSearch( true )
			->setCrossProjectSearch( $this->isFeatureEnabled( 'interwiki' ) )
			->setWithDYMSuggestion( $this->showSuggestion )
			->setAllowRewrite( $this->isFeatureEnabled( 'rewrite' ) )
			->addProfileContextParameter( ContextualProfileOverride::LANGUAGE,
				$this->requestContext->getLanguage()->getCode() );

		if ( $this->prefix !== '' ) {
			$builder->addContextualFilter( 'prefix',
				\CirrusSearch\Query\PrefixFeature::asContextualFilter( $this->prefix ) );
		}

		$profile = $this->extractProfileFromFeatureData( SearchEngine::FT_QUERY_INDEP_PROFILE_TYPE );
		if ( $profile !== null ) {
			$builder->addForcedProfile( SearchProfileService::RESCORE, $profile );
		}

		$query = $builder->build();

		$status = $this->searchTextReal( $query );
		$matches = $status->getValue();
		if ( $matches instanceof CirrusSearchResultSet ) {
			ElasticsearchIntermediary::setResultPages( [ $matches ] );
		}
		if ( $matches instanceof SearchMetricsProvider ) {
			$this->extraSearchMetrics += $status->getValue()->getMetrics();
		}

		return $status;
	}

	/**
	 * @param string $feature
	 * @return bool
	 */
	private function isFeatureEnabled( $feature ) {
		return isset( $this->features[$feature] ) && $this->features[$feature];
	}

	/**
	 * Do the hard part of the searching - actual Searcher invocation
	 * @param SearchQuery $query
	 * @return Status
	 */
	protected function searchTextReal( SearchQuery $query ) {
		$searcher = $this->makeSearcher( $query->getSearchConfig() );
		$status = $searcher->search( $query );
		$this->lastSearchMetrics = $searcher->getSearchMetrics();
		if ( !$status->isOK() ) {
			return $status;
		}

		$result = $status->getValue();

		// Add interwiki results, if we have a sane result
		// Note that we have no way of sending warning back to the user.  In this case all warnings
		// are logged when they are added to the status object so we just ignore them here....
		// TODO: move this to the Searcher class and get rid of InterwikiSearcher
		// there are no reasons we can't do this in a single msearch request.
		if ( $query->getCrossSearchStrategy()->isCrossProjectSearchSupported() &&
			$searcher->getSearchContext()->areResultsPossible() &&
			( $this->debugOptions->isReturnRaw() || method_exists( $result, 'addInterwikiResults' ) )
		) {
			$iwSearch = new InterwikiSearcher( $this->connection, $query->getSearchConfig(), $this->namespaces, null,
				$this->debugOptions, $this->namespacePrefixParser, $this->interwikiResolver, $this->titleHelper );
			$interwikiResults = $iwSearch->getInterwikiResults( $query );
			if ( $interwikiResults->isOK() && $interwikiResults->getValue() !== [] ) {
				foreach ( $interwikiResults->getValue() as $interwiki => $interwikiResult ) {
					if ( $this->debugOptions->isReturnRaw() ) {
						$result[$interwiki] = $interwikiResult;
					} elseif ( $interwikiResult && $interwikiResult->numRows() > 0 ) {
						$result->addInterwikiResults(
							$interwikiResult, ISearchResultSet::SECONDARY_RESULTS, $interwiki
						);
					}
				}
			}
		}

		if ( $this->debugOptions->isReturnRaw() ) {
			$status->setResult( true,
				$searcher->processRawReturn( $result, $this->request ) );
		}

		return $status;
	}

	/**
	 * Look for suggestions using ES completion suggester.
	 * @param string $search Search string
	 * @param string[]|null $variants Search term variants
	 * @param SearchConfig $config search configuration
	 * @return SearchSuggestionSet Set of suggested names
	 */
	protected function getSuggestions( $search, $variants, SearchConfig $config ) {
		// Inspect features to check if the user selected a specific profile
		$profile = $this->extractProfileFromFeatureData( SearchEngine::COMPLETION_PROFILE_TYPE );

		$clusterOverride = $config->getElement( 'CirrusSearchClusterOverrides', 'completion' );
		if ( $clusterOverride !== null ) {
			$connection = Connection::getPool( $config, $clusterOverride );
		} else {
			$connection = $this->connection;
		}
		$suggester = new CompletionSuggester( $connection, $this->limit,
				$this->offset, $config, $this->namespaces, null,
				false, $profile );

		$response = $suggester->suggest( $search, $variants );
		if ( $response->isOK() ) {
			// Errors will be logged, let's try the exact db match
			return $response->getValue();
		} else {
			return SearchSuggestionSet::emptySuggestionSet();
		}
	}

	/**
	 * Get the sort of sorts we allow
	 * @return string[]
	 */
	public function getValidSorts() {
		return [
			'relevance', 'just_match', 'none',
			'incoming_links_asc', 'incoming_links_desc',
			'last_edit_asc', 'last_edit_desc',
			'create_timestamp_asc', 'create_timestamp_desc',
			'random',
		];
	}

	/**
	 * Get the metrics for the last search we performed. Null if we haven't done any.
	 * @return array
	 */
	public function getLastSearchMetrics() {
		return $this->lastSearchMetrics + $this->extraSearchMetrics;
	}

	/**
	 * Perform a completion search.
	 * Does not resolve namespaces and does not check variants.
	 * We use parent search for:
	 * - Special: namespace
	 * We use old prefix search for:
	 * - Suggester not enabled
	 * -
	 * @param string $search
	 * @return SearchSuggestionSet
	 */
	protected function completionSearchBackend( $search ) {
		if ( in_array( NS_SPECIAL, $this->namespaces ) ) {
			// delegate special search to parent
			return parent::completionSearchBackend( $search );
		}

		// Not really useful, mostly for testing purpose
		$variants = $this->debugOptions->getCirrusCompletionVariant();
		if ( empty( $variants ) ) {
			$contentLang = MediaWikiServices::getInstance()->getContentLanguage();
			$variants = $contentLang->autoConvertToAllVariants( $search );
		} elseif ( count( $variants ) > 3 ) {
			// We should not allow too many variants
			$variants = array_slice( $variants, 0, 3 );
		}

		if ( !$this->config->isCompletionSuggesterEnabled() ) {
			// Completion suggester is not enabled, fallback to
			// default implementation
			return $this->prefixSearch( $search, $variants );
		}

		// the completion suggester is only worth a try if NS_MAIN is requested
		if ( !in_array( NS_MAIN, $this->namespaces ) ) {
			return $this->prefixSearch( $search, $variants );
		}

		$profile = $this->extractProfileFromFeatureData( SearchEngine::COMPLETION_PROFILE_TYPE );
		if ( $profile === null ) {
			// Need to fetch the name to fallback to prefix (not ideal)
			// We should probably refactor this to have a single code path for prefix and completion suggester.
			$profile = $this->config->getProfileService()
				->getProfileName( SearchProfileService::COMPLETION, SearchProfileService::CONTEXT_DEFAULT );
		}
		if ( $profile === self::COMPLETION_PREFIX_FALLBACK_PROFILE ) {
			// Fallback to prefixsearch if the classic profile was selected.
			return $this->prefixSearch( $search, $variants );
		}

		return $this->getSuggestions( $search, $variants, $this->config );
	}

	/**
	 * Override variants function because we always do variants
	 * in the backend.
	 * @see SearchEngine::completionSearchWithVariants()
	 * @param string $search
	 * @return SearchSuggestionSet
	 */
	public function completionSearchWithVariants( $search ) {
		return $this->completionSearch( $search );
	}

	/**
	 * Older prefix search.
	 * @param string $search search text
	 * @param string[] $variants
	 * @return SearchSuggestionSet
	 */
	protected function prefixSearch( $search, $variants ) {
		$searcher = $this->makeSearcher();

		if ( $search ) {
			$searcher->setResultsType( new FancyTitleResultsType( 'prefix' ) );
		} else {
			// Empty searches always find the title.
			$searcher->setResultsType( new TitleResultsType() );
		}

		try {
			$status = $searcher->prefixSearch( $search, $variants );
		} catch ( ApiUsageException $e ) {
			if ( defined( 'MW_API' ) ) {
				throw $e;
			}
			return SearchSuggestionSet::emptySuggestionSet();
		}

		// There is no way to send errors or warnings back to the caller here so we have to make do with
		// only sending results back if there are results and relying on the logging done at the status
		// construction site to log errors.
		if ( $status->isOK() ) {
			if ( !$search ) {
				// No need to unpack the simple title matches from non-fancy TitleResultsType
				return SearchSuggestionSet::fromTitles( $status->getValue() );
			}
			$results = array_filter( array_map(
				[ FancyTitleResultsType::class, 'chooseBestTitleOrRedirect' ],
				$status->getValue() ) );
			return SearchSuggestionSet::fromTitles( $results );
		}

		return SearchSuggestionSet::emptySuggestionSet();
	}

	/**
	 * @param string $profileType
	 * @param User|null $user
	 * @return array|null
	 * @see SearchEngine::getProfiles()
	 */
	public function getProfiles( $profileType, User $user = null ) {
		$profileService = $this->config->getProfileService();
		$serviceProfileType = null;
		switch ( $profileType ) {
		case SearchEngine::COMPLETION_PROFILE_TYPE:
			if ( $this->config->isCompletionSuggesterEnabled() ) {
				$serviceProfileType = SearchProfileService::COMPLETION;
			}
			break;
		case SearchEngine::FT_QUERY_INDEP_PROFILE_TYPE:
			$serviceProfileType = SearchProfileService::RESCORE;
			break;
		}

		if ( $serviceProfileType === null ) {
			return null;
		}

		$allowedProfiles = $profileService->listExposedProfiles( $serviceProfileType );

		$profiles = [];
		foreach ( $allowedProfiles as $name => $profile ) {
			// @todo: decide what to with profiles we declare
			// in wmf-config with no i18n messages.
			// Do we want to expose them anyway, or simply
			// hide them but still allow Api to pass them to us.
			// It may require a change in core since ApiBase is
			// strict and won't allow unknown values to be set
			// here.
			$profiles[] = [
				'name' => $name,
				'desc-message' => $profile['i18n_msg'] ?? null,
			];
		}
		if ( $profiles !== [] ) {
			$profiles[] = [
				'name' => self::AUTOSELECT_PROFILE,
				'desc-message' => 'cirrussearch-autoselect-profile',
				'default' => true,
			];
		}
		return $profiles;
	}

	/**
	 * (public for testing purposes)
	 * @param string $profileType
	 * @return string|null the profile name set in SearchEngine::features
	 * null if none present or equal to self::AUTOSELECT_PROFILE
	 */
	public function extractProfileFromFeatureData( $profileType ) {
		if ( isset( $this->features[$profileType] )
			&& $this->features[$profileType] !== self::AUTOSELECT_PROFILE
		) {
			return $this->features[$profileType];
		}
		return null;
	}

	/**
	 * Create a search field definition
	 * @param string $name
	 * @param string $type
	 * @return SearchIndexField
	 */
	public function makeSearchFieldMapping( $name, $type ): SearchIndexField {
		return $this->searchIndexFieldFactory->makeSearchFieldMapping( $name, $type );
	}

	/**
	 * Perform a title search in the article archive.
	 *
	 * @param string $term Raw search term
	 * @return Status<Title[]>
	 */
	public function searchArchiveTitle( $term ) {
		if ( !$this->config->get( 'CirrusSearchEnableArchive' ) ) {
			return Status::newGood( [] );
		}

		$term = trim( $term );

		if ( empty( $term ) ) {
			return Status::newGood( [] );
		}

		$searcher = $this->makeSearcher();
		$status = $searcher->searchArchive( $term );
		if ( $status->isOK() && $searcher->isReturnRaw() ) {
			$status->setResult( true,
				$searcher->processRawReturn( $status->getValue(), $this->request ) );
		}
		return $status;
	}

	/**
	 * @return Status Contains a single integer indicating the number
	 *  of content words in the wiki
	 */
	public function countContentWords() {
		$this->limit = 1;
		$searcher = $this->makeSearcher();
		$status = $searcher->countContentWords();

		if ( $status->isOK() && $searcher->isReturnRaw() ) {
			$status->setResult( true,
				$searcher->processRawReturn( $status->getValue(), $this->request ) );
		}
		return $status;
	}

	/**
	 * @param SearchConfig|null $config
	 * @return Searcher
	 */
	private function makeSearcher( SearchConfig $config = null ) {
		return new Searcher( $this->connection, $this->offset, $this->limit, $config ?? $this->config, $this->namespaces,
				null, false, $this->debugOptions, $this->namespacePrefixParser, $this->interwikiResolver, $this->titleHelper );
	}
}
