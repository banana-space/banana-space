<?php

namespace CirrusSearch;

use CirrusSearch\Fallbacks\FallbackRunner;
use CirrusSearch\Parser\BasicQueryClassifier;
use CirrusSearch\Parser\NamespacePrefixParser;
use CirrusSearch\Search\CrossProjectBlockScorerFactory;
use CirrusSearch\Search\FullTextResultsType;
use CirrusSearch\Search\MSearchRequests;
use CirrusSearch\Search\SearchContext;
use CirrusSearch\Search\SearchQuery;
use CirrusSearch\Search\SearchQueryBuilder;
use CirrusSearch\Search\TitleHelper;
use MediaWiki\MediaWikiServices;
use Status;
use User;

/**
 * Performs searches using Elasticsearch -- on interwikis!
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
class InterwikiSearcher extends Searcher {

	/**
	 * @param Connection $connection
	 * @param SearchConfig $config
	 * @param int[]|null $namespaces Namespace numbers to search, or null for all of them
	 * @param User|null $user
	 * @param CirrusDebugOptions|null $debugOptions
	 * @param NamespacePrefixParser|null $namespacePrefixParser
	 * @param InterwikiResolver|null $interwikiResolver
	 * @param TitleHelper|null $titleHelper
	 */
	public function __construct(
		Connection $connection,
		SearchConfig $config,
		array $namespaces = null,
		User $user = null,
		CirrusDebugOptions $debugOptions = null,
		NamespacePrefixParser $namespacePrefixParser = null,
		InterwikiResolver $interwikiResolver = null,
		TitleHelper $titleHelper = null
	) {
		$maxResults = $config->get( 'CirrusSearchNumCrossProjectSearchResults' );
		parent::__construct( $connection, 0, $maxResults, $config, $namespaces, $user, false,
			$debugOptions, $namespacePrefixParser, $interwikiResolver, $titleHelper );
	}

	/**
	 * Fetch search results, from caches, if there's any
	 * @param SearchQuery $query original search query
	 * @return Status
	 */
	public function getInterwikiResults( SearchQuery $query ): Status {
		$sources = MediaWikiServices::getInstance()
			->getService( InterwikiResolver::SERVICE )
			->getSisterProjectConfigs();
		if ( !$sources ) {
			// Nothing to search for
			return Status::newGood( [] );
		}

		$iwQueries = [];
		foreach ( $sources as $interwiki => $config ) {
			$iwQueries[$interwiki] = SearchQueryBuilder::forCrossProjectSearch( $config, $query )
				->build();
		}

		$blockScorer = CrossProjectBlockScorerFactory::load( $this->config );
		$msearches = new MSearchRequests();
		foreach ( $iwQueries as $interwiki => $iwQuery ) {
			$context = SearchContext::fromSearchQuery( $iwQuery,
				FallbackRunner::create( $iwQuery, $this->interwikiResolver ) );
			$this->searchContext = $context;
			$this->setResultsType( new FullTextResultsType( $this->searchContext->getFetchPhaseBuilder(),
				$query->getParsedQuery()->isQueryOfClass( BasicQueryClassifier::COMPLEX_QUERY ), $this->titleHelper ) );
			$this->config = $context->getConfig();
			$this->limit = $iwQuery->getLimit();
			$this->offset = $iwQuery->getOffset();
			$this->buildFullTextSearch( $query->getParsedQuery()->getQueryWithoutNsHeader() );
			$this->indexBaseName = $context->getConfig()->get( 'CirrusSearchIndexBaseName' );
			$search = $this->buildSearch();
			if ( $this->searchContext->areResultsPossible() ) {
				$msearches->addRequest( $interwiki, $search );
			}
		}

		$searchDescription = "{$this->searchContext->getSearchType()} search for '{$this->searchContext->getOriginalSearchTerm()}'";
		if ( $this->searchContext->getDebugOptions()->isCirrusDumpQuery() ) {
			return $msearches->dumpQuery( $searchDescription );
		}
		$mresponses = $this->searchMulti( $msearches );
		if ( $mresponses->hasFailure() ) {
			return $mresponses->getFailure();
		}

		if ( $this->searchContext->getDebugOptions()->isReturnRaw() ) {
			return $mresponses->dumpResults( $searchDescription );
		}

		return $mresponses->transformAndGetMulti( $this->searchContext->getResultsType(), array_keys( $iwQueries ),
			function ( array $v ) use ( $blockScorer ) {
				return $blockScorer->reorder( $v );
			} );
	}

	/**
	 * @return string The stats key used for reporting hit/miss rates of the
	 *  application side query cache.
	 */
	protected function getQueryCacheStatsKey() {
		return 'CirrusSearch.query_cache.interwiki';
	}
}
