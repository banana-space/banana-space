<?php

namespace Flow\Api;

use ApiBase;
use Flow\Container;
use Flow\Exception\InvalidDataException;
use Flow\Formatter\TopicListFormatter;
use Flow\Formatter\TopicListQuery;
use Flow\Model\UUID;
use Flow\Search\Connection;
use Flow\Search\SearchEngine;
use Flow\Search\Searcher;
use Status;

class ApiFlowSearch extends ApiFlowBaseGet {
	/**
	 * @var SearchEngine
	 */
	protected $searchEngine;

	public function __construct( $api, $modName ) {
		parent::__construct( $api, $modName, 'q' );
		$this->searchEngine = new SearchEngine();
	}

	public function execute() {
		$params = $this->extractRequestParams();

		if ( $params['type'] ) {
			$this->searchEngine->setType( $params['type'] );
		}

		$this->searchEngine->setLimitOffset( $params['limit'], $params['offset'] );
		$this->searchEngine->setSort( $params['sort'] );

		// pages are optional; if not provided, all pages will be searched
		$pageIds = $this->getPageIds( $params );
		if ( $pageIds ) {
			$this->searchEngine->setPageIds( $pageIds );
		}

		// namespaces are optional; if not provided, all namespaces will be searched
		if ( $params['namespaces'] ) {
			$this->searchEngine->setNamespaces( $params['namespaces'] );
		}

		// moderationStates are optional; if not provided, all moderation states will be searched
		$moderationState = $this->getModerationState( $params );
		if ( $moderationState ) {
			$this->searchEngine->setModerationStates( $moderationState );
		}

		/** @var Status $status */
		$status = $this->searchEngine->searchText( $params['term'] );
		if ( !$status->isOK() ) {
			throw new InvalidDataException( $status->getMessage()->text(), 'fail-search' );
		}
		$this->getMain()->getErrorFormatter()->addMessagesFromStatus(
			$this->getModuleName(), $status );

		$resultSet = $status->getValue();
		// $resultSet can be null, if nothing was found
		if ( $resultSet === null ) {
			return;
		}
		/** @var \Elastica\ResultSet $resultSet */
		$results = $resultSet->getResults();

		// list of highlighted words
		$highlights = [];
		/** @var \Elastica\Result $result */
		foreach ( $results as $result ) {
			// there'll always be exactly 1 excerpt
			// see Searcher.php, ...->setHighlight() config
			$excerpt = $result->getHighlights();
			$excerpt = $excerpt[Searcher::HIGHLIGHT_FIELD][0];

			$pre = preg_quote( Searcher::HIGHLIGHT_PRE, '/' );
			$post = preg_quote( Searcher::HIGHLIGHT_POST, '/' );
			if ( preg_match_all( '/' . $pre . '(.*?)' . $post . '/', $excerpt, $matches ) ) {
				$highlights += array_flip( $matches[1] );
			}
		}
		$highlights = array_keys( $highlights );

		// total term frequency
		$ttf = $resultSet->getAggregation( 'ttf' );
		$ttf = $ttf['value'];

		$topicIds = [];
		foreach ( $results as $topic ) {
			$topicIds[] = UUID::create( $topic->getId() );
		}

		// output similar to view-topiclist
		$results = $this->formatApi( $topicIds );
		// search-specific output
		$results['total'] = $resultSet->getTotalHits();
		$results['highlights'] = $highlights;
		$results['ttf'] = $ttf;

		$this->getResult()->addValue( null, $this->getModuleName(), $results );
	}

	/**
	 * Given an array of topic UUIDs, we'll use TopicListQuery & TopicListFormatter
	 * to return API output very similar to ApiFlowViewTopicList.
	 *
	 * @param array $topicIds
	 * @return array
	 */
	protected function formatApi( array $topicIds ) {
		/** @var TopicListQuery $query */
		$query = Container::get( 'query.topiclist' );
		$found = $query->getResults( $topicIds );

		$storage = Container::get( 'storage' );
		$workflows = $storage->getMulti( 'Workflow', $topicIds );

		/** @var TopicListFormatter $serializer */
		$serializer = Container::get( 'formatter.topiclist' );
		return $serializer->buildResult( $workflows, $found, $this->getContext() );
	}

	/**
	 * @param array $params
	 * @return int[]
	 */
	protected function getPageIds( $params ) {
		$pageIds = [];
		// page is optional - if not provided, all pages will be searched
		if ( $params['title'] || $params['pageid'] ) {
			$page = $this->getTitleOrPageId( $params );

			// validate the page that was passed in
			if ( !$page->exists() ) {
				$this->dieWithError( 'apierror-missingtitle', 'invalid-page' );
			}

			if ( $page->getTitle()->getContentModel() !== CONTENT_MODEL_FLOW_BOARD ) {
				$this->dieWithError( 'apierror-flow-notenabled', 'invalid-page' );
			}

			$pageIds[] = $page->getId();
		}

		return $pageIds;
	}

	/**
	 * If only state AbstractRevision::MODERATED_NONE (which is '') is
	 * passed in, this param will be an empty array. Let's fix this and
	 * turn it into an array with exactly 1 value: ''
	 * Other scenarios are fine: if multiple states are passed in, ''
	 * is correctly recognized. If no state has been passed in, this
	 * parameter value is null, which will by default search all
	 * moderation states.
	 * Regardless of which status a user searches in, we'll never
	 * display results the user has no permissions for.
	 *
	 * @param array $params
	 * @return string[]|null
	 */
	protected function getModerationState( $params ) {
		$moderationState = $params['moderationState'];
		if ( $moderationState !== null && count( $moderationState ) === 0 ) {
			$moderationState = [ '' ];
		}

		return $moderationState;
	}

	public function getAllowedParams() {
		global $wgFlowDefaultLimit;

		$sorts = $this->searchEngine->getValidSorts();

		return [
			'term' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => 'string',
			],
			'title' => null,
			'pageid' => [
				ApiBase::PARAM_ISMULTI => false,
				ApiBase::PARAM_TYPE => 'integer'
			],
			'namespaces' => [
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_TYPE => 'namespace',
			],
			'moderationState' => [
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_TYPE => $this->getModerationStates( false ),
			],
			'sort' => [
				ApiBase::PARAM_TYPE => $sorts,
				ApiBase::PARAM_DFLT => reset( $sorts ),
			],
			'type' => [
				// false is allowed (means we'll search *all* types)
				ApiBase::PARAM_TYPE => array_merge( Connection::getAllTypes(), [ false ] ),
				ApiBase::PARAM_DFLT => false,
			],
			'offset' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_DFLT => 0,
			],
			'limit' => [
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_DFLT => $wgFlowDefaultLimit,
				ApiBase::PARAM_MAX => $wgFlowDefaultLimit,
				ApiBase::PARAM_MAX2 => $wgFlowDefaultLimit,
			],
		];
	}

	public function getParamDescription() {
		$p = $this->getModulePrefix();
		return [
			'term' => 'Search term',
			'title' => "Title of the boards to search in. Cannot be used together with {$p}pageid",
			'pageid' => "ID of the boards to search in. Cannot be used together with {$p}title",
			'namespaces' => 'Namespaces to search in',
			'moderationState' => 'Search for revisions in (a) particular moderation state(s)',
			'sort' => 'What to order the search results by',
			'type' => 'Desired type of results (' . implode( '|', Connection::getAllTypes() ) . ')',
			'offset' => 'Offset value to start fetching results at',
			'limit' => 'Amount of results to fetch',
		];
	}

	public function getDescription() {
		return 'Search within Flow boards';
	}

	public function getExamplesMessages() {
		return [
			'api.php?action=flow&submodule=search&qterm=keyword&qtitle=Main_Page',
		];
	}

	public function needsPage() {
		// irrelevant for search API
		return false;
	}

	protected function getBlockParams() {
		// irrelevant for search API
		return [];
	}

	protected function getAction() {
		// irrelevant for search API
		return '';
	}
}
