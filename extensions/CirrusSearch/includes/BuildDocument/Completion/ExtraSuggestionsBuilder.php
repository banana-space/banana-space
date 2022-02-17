<?php

namespace CirrusSearch\BuildDocument\Completion;

/**
 * Extra builder for the completion suggester index.
 * Useful to add extra suggestions that are not part of the default strategy.
 */
interface ExtraSuggestionsBuilder {
	/**
	 * Builds extra suggestions.
	 * This method can be called twice per cirrus document.
	 * - first time with title suggestions
	 * - second time with redirect suggestions
	 *
	 * @param mixed[] $inputDoc
	 * @param string $suggestType (title or redirect)
	 * @param int $score
	 * @param \Elastica\Document $suggestDoc suggestion type (title or redirect)
	 * @param int $targetNamespace
	 */
	public function build( array $inputDoc, $suggestType, $score, \Elastica\Document $suggestDoc, $targetNamespace );

	/**
	 * The fields needed by this extra builder.
	 * @return string[] the list of fields
	 */
	public function getRequiredFields();
}
