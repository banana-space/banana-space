<?php

namespace Flow\Block;

use Flow\Container;
use Flow\Data\Pager\HistoryPager;
use Flow\Exception\DataModelException;
use Flow\Formatter\BoardHistoryQuery;
use Flow\Formatter\RevisionFormatter;

class BoardHistoryBlock extends AbstractBlock {
	protected $supportedGetActions = [ 'history' ];

	// @Todo - fill in the template names
	protected $templates = [
		'history' => '',
	];

	/**
	 * Board history is read-only block which should not invoke write action
	 */
	public function validate() {
		throw new DataModelException( __CLASS__ . ' should not invoke validate()', 'process-data' );
	}

	/**
	 * Board history is read-only block which should not invoke write action
	 */
	public function commit() {
		throw new DataModelException( __CLASS__ . ' should not invoke commit()', 'process-data' );
	}

	public function renderApi( array $options ) {
		global $wgRequest;

		if ( $this->workflow->isNew() ) {
			return [
				'type' => $this->getName(),
				'revisions' => [],
				'links' => [
				],
			];
		}

		/** @var BoardHistoryQuery $query */
		$query = Container::get( 'query.board.history' );
		/** @var RevisionFormatter $formatter */
		$formatter = Container::get( 'formatter.revision.factory' )->create();
		$formatter->setIncludeHistoryProperties( true );

		list( $limit, /* $offset */ ) = $wgRequest->getLimitOffsetForUser(
			$this->context->getUser()
		);
		// don't use offset from getLimitOffset - that assumes an int, which our
		// UUIDs are not
		$offset = $wgRequest->getText( 'offset' );
		$offset = $offset ?: null;

		$pager = new HistoryPager( $query, $this->workflow->getId() );
		$pager->setLimit( $limit );
		$pager->setOffset( $offset );
		$pager->doQuery();
		$history = $pager->getResult();

		$revisions = [];
		foreach ( $history as $row ) {
			$serialized = $formatter->formatApi( $row, $this->context, 'history' );
			if ( $serialized ) {
				$revisions[$serialized['revisionId']] = $serialized;
			}
		}

		return [
			'type' => $this->getName(),
			'revisions' => $revisions,
			'navbar' => $pager->getNavigationBar(),
			'links' => [
			],
		];
	}

	public function getName() {
		return 'board-history';
	}
}
