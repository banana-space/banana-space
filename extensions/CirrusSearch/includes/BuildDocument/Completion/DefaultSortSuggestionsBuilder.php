<?php

namespace CirrusSearch\BuildDocument\Completion;

/**
 * Extra builder that appends the defaultsort value to suggest and suggest-stop
 * inputs on title suggestions
 */
class DefaultSortSuggestionsBuilder implements ExtraSuggestionsBuilder {
	const FIELD = 'defaultsort';

	/**
	 * @inheritDoc
	 */
	public function getRequiredFields() {
		return [ self::FIELD ];
	}

	/**
	 * @param mixed[] $inputDoc
	 * @param string $suggestType (title or redirect)
	 * @param int $score
	 * @param \Elastica\Document $suggestDoc suggestion type (title or redirect)
	 * @param int $targetNamespace
	 */
	public function build( array $inputDoc, $suggestType, $score, \Elastica\Document $suggestDoc, $targetNamespace ) {
		if ( $targetNamespace != $inputDoc['namespace'] ) {
			// This is a cross namespace redirect, we don't
			// add defaultsort for this one.
			return;
		}
		if ( $suggestType === SuggestBuilder::TITLE_SUGGESTION && isset( $inputDoc[ self::FIELD ] ) ) {
			$value = $inputDoc[self::FIELD];
			if ( is_string( $value ) ) {
				$this->addInputToFST( $value, 'suggest', $suggestDoc );
				$this->addInputToFST( $value, 'suggest-stop', $suggestDoc );
			}
		}
	}

	/**
	 * @param string $input the new input
	 * @param string $fstField field name
	 * @param \Elastica\Document $suggestDoc
	 */
	private function addInputToFST( $input, $fstField, $suggestDoc ) {
		if ( $suggestDoc->has( $fstField ) ) {
			$entryDef = $suggestDoc->get( $fstField );
			$entryDef['input'][] = $input;
			$suggestDoc->set( $fstField, $entryDef );
		}
	}
}
