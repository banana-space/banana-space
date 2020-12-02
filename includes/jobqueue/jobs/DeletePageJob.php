<?php

/**
 * Class DeletePageJob
 */
class DeletePageJob extends Job implements GenericParameterJob {
	public function __construct( array $params ) {
		parent::__construct( 'deletePage', $params );

		$this->title = Title::makeTitle( $params['namespace'], $params['title'] );
	}

	public function run() {
		// Failure to load the page is not job failure.
		// A parallel deletion operation may have already completed the page deletion.
		$wikiPage = WikiPage::newFromID( $this->params['wikiPageId'] );
		if ( $wikiPage ) {
			$wikiPage->doDeleteArticleBatched(
				$this->params['reason'],
				$this->params['suppress'],
				User::newFromId( $this->params['userId'] ),
				json_decode( $this->params['tags'] ),
				$this->params['logsubtype'],
				false,
				$this->getRequestId() );
		}
		return true;
	}
}
