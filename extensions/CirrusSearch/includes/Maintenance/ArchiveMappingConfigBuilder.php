<?php

namespace CirrusSearch\Maintenance;

use CirrusSearch\Connection;
use CirrusSearch\Search\TextIndexField;

class ArchiveMappingConfigBuilder extends MappingConfigBuilder {
	const VERSION = '1.0';

	public function buildConfig() {
		return [ $this->getMainType() => [
			'dynamic' => false,
			'properties' => [
				'namespace' => $this->searchIndexFieldFactory
					->newLongField( 'namespace' )
					->getMapping( $this->engine ),
				'title' => $this->searchIndexFieldFactory->newStringField( 'title',
					TextIndexField::ENABLE_NORMS )->setMappingFlags( $this->flags )->getMapping( $this->engine ),
				'wiki' => $this->searchIndexFieldFactory
					->newKeywordField( 'wiki' )
					->getMapping( $this->engine ),
			],
		] ];
	}

	/**
	 * The elastic type name used by this index
	 * @return string
	 */
	public function getMainType() {
		return Connection::ARCHIVE_TYPE_NAME;
	}

	/**
	 * @return bool
	 */
	public function canOptimizeAnalysisConfig() {
		return true;
	}
}
