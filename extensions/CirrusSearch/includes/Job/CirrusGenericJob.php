<?php

namespace CirrusSearch\Job;

use CirrusSearch\SearchConfig;
use MediaWiki\MediaWikiServices;

/**
 * CirrusSearch Job that is not bound to a Title
 */
abstract class CirrusGenericJob extends \Job implements \GenericParameterJob {
	use JobTraits;

	/**
	 * @var SearchConfig
	 */
	protected $searchConfig;

	/**
	 * @inheritDoc
	 */
	public function __construct( array $params ) {
		parent::__construct( self::buildJobName( static::class ), $params + [ 'cluster' => null ] );

		// All CirrusSearch jobs are reasonably expensive.  Most involve parsing and it
		// is ok to remove duplicate _unclaimed_ cirrus jobs.  Once a cirrus job is claimed
		// it can't be deduplicated or else the search index will end up with out of date
		// data.  Luckily, this is how the JobQueue implementations work.
		$this->removeDuplicates = true;

		$this->searchConfig = MediaWikiServices::getInstance()
			->getConfigFactory()
			->makeConfig( 'CirrusSearch' );
	}

	/**
	 * @return SearchConfig
	 */
	public function getSearchConfig(): SearchConfig {
		return $this->searchConfig;
	}
}
