<?php

namespace CirrusSearch\BuildDocument;

use CirrusSearch\Connection;
use CirrusSearch\ElasticaErrorHandler;
use CirrusSearch\ElasticsearchIntermediary;
use CirrusSearch\Search\CirrusIndexField;
use CirrusSearch\SearchConfig;
use CirrusSearch\SearchRequestLog;
use Elastica\Document;
use Elastica\Multi\Search as MultiSearch;
use Elastica\Query\BoolQuery;
use Elastica\Query\Terms;
use Elastica\Search;
use MediaWiki\Logger\LoggerFactory;
use Title;
use WikiPage;

/**
 * Adds redirects and incoming links to the documents.  These are done together
 * because one needs the other.
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
class RedirectsAndIncomingLinks extends ElasticsearchIntermediary implements PagePropertyBuilder {
	/**
	 * @var SearchConfig
	 */
	private $config;

	/**
	 * @var MultiSearch
	 */
	private $linkCountMultiSearch;

	/**
	 * @var callable[] Callables expecting to recieve a single argument, the total hits
	 *  of the related query added to linkCountMultiSearch. Array is in same order
	 *  as queries added to the multi-search.
	 */
	private $linkCountClosures = [];

	/**
	 * @var int[] List of page id's in current batch. Only for debug purposes.
	 */
	private $pageIds = [];

	public function __construct( Connection $conn ) {
		parent::__construct( $conn, null, 0 );
		$this->config = $conn->getConfig();
		$this->linkCountMultiSearch = new MultiSearch( $this->connection->getClient() );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Document $doc The document to be populated
	 * @param WikiPage $page The page to scope operation to
	 */
	public function initialize( Document $doc, WikiPage $page ): void {
		$title = $page->getTitle();
		$this->pageIds[] = $page->getId();
		$outgoingLinksToCount = [ $title->getPrefixedDBkey() ];

		// Gather redirects to this page
		$redirectTitles = $title->getBacklinkCache()
			->getLinks( 'redirect', false, false, $this->config->get( 'CirrusSearchIndexedRedirects' ) );
		$redirects = [];
		/** @var Title $redirect */
		foreach ( $redirectTitles as $redirect ) {
			// If the redirect is in main OR the same namespace as the article the index it
			if ( $redirect->getNamespace() === NS_MAIN || $redirect->getNamespace() === $title->getNamespace() ) {
				$redirects[] = [
					'namespace' => $redirect->getNamespace(),
					'title' => $redirect->getText()
				];
				$outgoingLinksToCount[] = $redirect->getPrefixedDBkey();
			}
		}
		$doc->set( 'redirect', $redirects );

		// Count links
		// Incoming links is the sum of:
		// #1 Number of redirects to the page
		// #2 Number of links to the title
		// #3 Number of links to all the redirects

		// #1 we have a list of the "first" $wgCirrusSearchIndexedRedirects redirect so we just count it:
		$redirectCount = count( $redirects );

		// #2 and #3 we count the number of links to the page with Elasticsearch.
		// Since we only have $wgCirrusSearchIndexedRedirects we only count that many terms.
		$this->linkCountMultiSearch->addSearch( $this->buildCount( $outgoingLinksToCount ) );
		$this->linkCountClosures[] = function ( $count ) use( $doc, $redirectCount ) {
			$doc->set( 'incoming_links', $count + $redirectCount );
			CirrusIndexField::addNoopHandler( $doc, 'incoming_links', 'within 20%' );
		};
	}

	/**
	 * {@inheritDoc}
	 */
	public function finishInitializeBatch(): void {
		if ( !$this->linkCountClosures ) {
			return;
		}
		$linkCountClosureCount = count( $this->linkCountClosures );
		try {
			$this->startNewLog( "counting links to {pageCount} pages", 'count_links', [
				'pageCount' => $linkCountClosureCount,
				'query' => $linkCountClosureCount,
			] );
			$result = $this->linkCountMultiSearch->search();
			$foundNull = false;
			for ( $index = 0; $index < $linkCountClosureCount; $index++ ) {
				if ( $result[$index] === null ) {
					// Finish updating other docs that have results before
					// throwing the exception.
					$foundNull = true;
				} else {
					$this->linkCountClosures[ $index ]( $result[ $index ]->getTotalHits() );
				}
			}
			if ( $foundNull ) {
				$this->raiseLinkCountException( $result );
			}
			$this->success();
		} catch ( \Elastica\Exception\ExceptionInterface $e ) {
			// Note that we do not abort the update operation on failure, we simply
			// complain about it and let the remainder of the update continue. The
			// counts can simply be allowed to drift until resolved.
			$this->failure( $e );
			LoggerFactory::getInstance( 'CirrusSearchChangeFailed' )->info(
				'Links for page ids: ' . implode( ',', $this->pageIds ) );
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Document $doc
	 * @param Title $title
	 */
	public function finalize( Document $doc, Title $title ): void {
		// NOOP
	}

	private function raiseLinkCountException( $result ): void {
		$linkCountClosureCount = count( $this->linkCountClosures );
		// Seems to happen during connection issues? Treat it the
		// same as an exception even though it wasn't thrown (why?)
		$numNulls = 0;
		for ( $i = 0; $i < $linkCountClosureCount; $i++ ) {
			if ( $result[$i] === null ) {
				$numNulls++;
			}
		}

		// Log the raw request/response until we understand how these happen
		ElasticaErrorHandler::logRequestResponse( $this->connection,
			"Received null for link count on {numNulls} out of {linkCountClosureCount} pages", [
				'numNulls' => $numNulls,
				'linkCountClosureCount' => $linkCountClosureCount,
			] );

		throw new \Elastica\Exception\RuntimeException(
			"Received null for link count on $numNulls out of $linkCountClosureCount pages" );
	}

	/**
	 * Build a Search that will count all pages that link to $titles.
	 *
	 * @param string[] $titles title in prefixedDBKey form
	 * @return Search that counts all pages that link to $titles
	 */
	private function buildCount( array $titles ): Search {
		$bool = new BoolQuery();
		$bool->addFilter( new Terms( 'outgoing_link', $titles ) );

		$indexPrefix = $this->config->get( SearchConfig::INDEX_BASE_NAME );
		$type = $this->connection->getPageType( $indexPrefix );
		$search = new Search( $type->getIndex()->getClient() );
		$search->addIndex( $type->getIndex() );
		$search->addType( $type );
		$search->setQuery( $bool );
		$search->getQuery()->addParam( 'stats', 'link_count' );
		$search->getQuery()->setSize( 0 );

		return $search;
	}

	/**
	 * @param string $description
	 * @param string $queryType
	 * @param array $extra
	 * @return SearchRequestLog
	 */
	protected function newLog( $description, $queryType, array $extra = [] ) {
		return new SearchRequestLog(
			$this->connection->getClient(),
			$description,
			$queryType,
			$extra
		);
	}
}
