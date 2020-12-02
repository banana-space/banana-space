<?php

namespace PageImages\Job;

use Job;
use MediaWiki\MediaWikiServices;
use MWExceptionHandler;
use RefreshLinks;
use Title;

class InitImageDataJob extends Job {
	/**
	 * @param Title $title Title object associated with this job
	 * @param array $params Parameters to the job, containing an array of
	 * page ids representing which pages to process
	 */
	public function __construct( Title $title, array $params ) {
		parent::__construct( 'InitImageDataJob', $title, $params );
	}

	/**
	 * @inheritDoc
	 */
	public function run() {
		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();

		foreach ( $this->params['page_ids'] as $id ) {
			try {
				RefreshLinks::fixLinksFromArticle( $id );
				$lbFactory->waitForReplication();
			} catch ( \Exception $e ) {
				// There are some broken pages out there that just don't parse.
				// Log it and keep on trucking.
				MWExceptionHandler::logException( $e );
			}
		}
		return true;
	}
}
