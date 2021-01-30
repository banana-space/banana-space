<?php

namespace Flow\Formatter;

use Flow\Data\ManagerGroup;
use Flow\Exception\FlowException;
use Flow\Model\PostRevision;
use Flow\Model\UUID;
use Flow\Model\Workflow;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * This class is necessary so we can inject the name of
 * a topic title into the category.  Once we have pages
 * in the topic namespace named after the topic themselves
 * this can be simplified down to only pre-load the workflow
 * and not the related posts.
 */
class CategoryViewerQuery {
	/**
	 * @var PostRevision[]
	 */
	protected $posts = [];

	/**
	 * @var Workflow[]
	 */
	protected $workflows = [];

	/**
	 * @var ManagerGroup
	 */
	protected $storage;

	public function __construct( ManagerGroup $storage ) {
		$this->storage = $storage;
	}

	/**
	 * Accepts a result set as sent out to the CategoryViewer::doCategoryQuery
	 * hook.
	 *
	 * @param IResultWrapper|array $rows
	 */
	public function loadMetadataBatch( $rows ) {
		$neededPosts = [];
		$neededWorkflows = [];
		foreach ( $rows as $row ) {
			if ( $row->page_namespace != NS_TOPIC ) {
				continue;
			}
			$uuid = UUID::create( strtolower( $row->page_title ) );
			if ( $uuid ) {
				$alpha = $uuid->getAlphadecimal();
				$neededPosts[$alpha] = [ 'rev_type_id' => $uuid ];
				$neededWorkflows[$alpha] = $uuid;
			}
		}

		if ( !$neededPosts ) {
			return;
		}
		$this->posts = $this->storage->findMulti(
			'PostRevision',
			$neededPosts,
			[ 'sort' => 'rev_id', 'order' => 'DESC', 'limit' => 1 ]
		);
		$workflows = $this->storage->getMulti(
			'Workflow',
			$neededWorkflows
		);
		// @todo fixme: these should have come back with the apropriate array
		// key since we passed it in above, but didn't.
		foreach ( $workflows as $workflow ) {
			$this->workflows[$workflow->getId()->getAlphadecimal()] = $workflow;
		}
	}

	public function getResult( UUID $uuid ) {
		$alpha = $uuid->getAlphadecimal();

		// Minimal set of data needed for the CategoryViewFormatter
		$row = new FormatterRow;
		if ( !isset( $this->posts[$alpha] ) ) {
			throw new FlowException( "A required post has not been loaded: $alpha" );
		}
		$row->revision = reset( $this->posts[$alpha] );
		if ( !isset( $this->workflows[$alpha] ) ) {
			throw new FlowException( "A required workflow has not been loaded: $alpha" );
		}
		$row->workflow = $this->workflows[$alpha];

		return $row;
	}
}
