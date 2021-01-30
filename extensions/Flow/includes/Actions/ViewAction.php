<?php

namespace Flow\Actions;

use Article;
use IContextSource;
use OutputPage;
use Page;
use Title;

class ViewAction extends FlowAction {

	/**
	 * @param Article|Page $page
	 * @param IContextSource $context
	 */
	public function __construct( Page $page, IContextSource $context ) {
		parent::__construct( $page, $context, 'view' );
	}

	public function doesWrites() {
		return false;
	}

	public function showForAction( $action, OutputPage $output = null ) {
		parent::showForAction( $action, $output );

		$title = $this->context->getTitle();
		$this->context->getUser()->clearNotification( $title );

		if ( $output === null ) {
			$output = $this->context->getOutput();
		}
		$output->addCategoryLinks( $this->getCategories( $title ) );
	}

	protected function getCategories( Title $title ) {
		$id = $title->getArticleID();
		if ( !$id ) {
			return [];
		}

		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select(
			/* from */ 'categorylinks',
			/* select */ [ 'cl_to', 'cl_sortkey' ],
			/* conditions */ [ 'cl_from' => $id ],
			__METHOD__
		);

		$categories = [];
		foreach ( $res as $row ) {
			$categories[$row->cl_to] = $row->cl_sortkey;
		}

		return $categories;
	}
}
