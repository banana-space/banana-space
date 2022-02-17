<?php

namespace CirrusSearch\Search;

/**
 * Simple TextIndexField subclass useful to customize COPY_TO_SUGGEST
 * @package CirrusSearch
 */
class OpeningTextIndexField extends TextIndexField {
	/**
	 * Force COPY_TO_SUGGEST if CirrusSearchPhraseSuggestUseOpeningText
	 * is set.
	 * @param int $mappingFlags
	 * @return int
	 */
	protected function getTextOptions( $mappingFlags ) {
		$options = parent::getTextOptions( $mappingFlags );
		if ( $this->config->get( 'CirrusSearchEnablePhraseSuggest' ) &&
			 $this->config->get( 'CirrusSearchPhraseSuggestUseOpeningText' )
		) {
			$options |= self::COPY_TO_SUGGEST;
		}
		return $options;
	}
}
