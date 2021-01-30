<?php

namespace Flow\Search;

use Elastica\Filter\BoolFilter;
use Elastica\Filter\Terms;
use Elastica\Query;
use Flow\Exception\InvalidParameterException;

class SearchEngine extends \SearchEngine {

	private const MAX_OFFSET = 100000;

	/**
	 * @var string|false $type Type of revisions to retrieve
	 */
	protected $type = false;

	/**
	 * Unlike \SearchEngine, the default is *no* specific namespace (=ALL)
	 *
	 * @var int[]
	 */
	public $namespaces = [];

	/**
	 * @var int[]
	 */
	protected $pageIds = [];

	/**
	 * @var string[]
	 */
	protected $moderationStates = [];

	/**
	 * @var string
	 */
	protected $sort = 'relevance';

	/**
	 * @param string $term text to search
	 * @return \Status|null
	 */
	public function searchText( $term ) {
		$term = trim( $term );
		// No searching for nothing!  That takes forever!
		if ( !$term ) {
			return null;
		}

		$query = new Query();

		$offset = min( $this->offset, static::MAX_OFFSET );
		if ( $offset ) {
			$query->setFrom( $offset );
		}
		if ( $this->limit ) {
			$query->setSize( $this->limit );
		}

		$filter = new BoolFilter();

		// filters
		if ( $this->namespaces ) {
			$filter->addMust( new Terms( 'namespace', $this->namespaces ) );
		}
		if ( $this->pageIds ) {
			$filter->addMust( new Terms( 'pageid', $this->pageIds ) );
		}
		if ( $this->moderationStates ) {
			$filter->addMust( new Terms( 'revisions.moderation_state', $this->moderationStates ) );
		}

		// only apply filters if there are any
		if ( $filter->toArray() ) {
			// @phan-suppress-next-line PhanTypeMismatchArgumentReal
			$query->setPostFilter( $filter );
		}

		$sortArgs = $this->getSortArgs();
		if ( isset( $sortArgs[$this->sort] ) && $sortArgs[$this->sort] ) {
			$query->setSort( $sortArgs[$this->sort] );
		}

		// @todo: interwiki stuff? (see \CirrusSearch)

		$searcher = new Searcher( $query, false, $this->type );
		return $searcher->searchText( $term );
	}

	/**
	 * Set the search index to search in.
	 * false is allowed (means we'll search *all* types)
	 *
	 * @param string|false $type
	 * @throws InvalidParameterException
	 */
	public function setType( $type ) {
		$allowedTypes = array_merge( Connection::getAllTypes(), [ false ] );
		if ( !in_array( $type, $allowedTypes ) ) {
			throw new InvalidParameterException( 'Invalid search index requested' );
		}

		$this->type = $type;
	}

	/**
	 * Set pages in which to search.
	 *
	 * @param int[] $pageIds
	 */
	public function setPageIds( array $pageIds ) {
		$this->pageIds = $pageIds;
	}

	/**
	 * Set moderation states in which to search.
	 *
	 * @param string[] $moderationStates
	 */
	public function setModerationStates( array $moderationStates = [] ) {
		$this->moderationStates = $moderationStates;
	}

	/**
	 * @param string $sort
	 * @throws InvalidParameterException
	 */
	public function setSort( $sort ) {
		if ( !in_array( $sort, $this->getValidSorts() ) ) {
			throw new InvalidParameterException( 'Invalid search sort requested' );
		}

		$this->sort = $sort;
	}

	/**
	 * Get the sort of sorts we allow.
	 *
	 * @return array
	 */
	public function getValidSorts() {
		// note that API will default to the first sort in this array - make it
		// a sensible default!
		return array_keys( $this->getSortArgs() );
	}

	/**
	 * We may want to revisit this at some later point.
	 *
	 * Nik's advice: "I advise against asking Elasticsearch to sort.
	 * Instead layer some kind of boost for more recent posts on top of the
	 * standard text scoring. That way better matches will still get sorted
	 * higher, even if they are older. Also, sorting isn't super efficient
	 * in Elasticsearch."
	 *
	 * @see https://gerrit.wikimedia.org/r/#/c/126996/6/includes/Search/SearchEngine.php
	 * @return array [description => [sort field => order]]
	 */
	public function getSortArgs() {
		return [
			'relevance' => [ /* default */ ],
			'timestamp_asc' => [ 'timestamp' => 'asc' ],
			'timestamp_desc' => [ 'timestamp' => 'desc' ],
			'update_timestamp_asc' => [ 'update_timestamp' => 'asc' ],
			'update_timestamp_desc' => [ 'update_timestamp' => 'desc' ],
		];
	}

	/**
	 * @inheritDoc
	 */
	public function supports( $feature ) {
		// we're not really an alternative search engine for MW ;)
		return false;
	}
}
