<?php

namespace Flow\Import\LiquidThreadsApi;

class MovedImportPost extends ImportPost {
	public function getRevisions() {
		$scriptUser = $this->importSource->getScriptUser();
		$factory = function ( $data, $parent ) use ( $scriptUser ) {
			return new MovedImportRevision( $data, $parent, $scriptUser );
		};
		$pageData = $this->importSource->getPageData( $this->pageId );

		return new RevisionIterator( $pageData, $this, $factory );
	}
}
