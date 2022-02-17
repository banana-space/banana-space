<?php

namespace CirrusSearch\Search\Fetch;

interface HighlightFieldGenerator {
	/**
	 * @param string $name
	 * @param string $target
	 * @param string $pattern
	 * @param bool $caseInsensitive
	 * @param int $priority
	 * @return BaseHighlightedField
	 * @see HighlightFieldGenerator::supportsRegexFields()
	 */
	public function newRegexField(
		$name,
		$target,
		$pattern,
		$caseInsensitive,
		$priority = HighlightedField::COSTLY_EXPERT_SYNTAX_PRIORITY
	): BaseHighlightedField;

	/**
	 * @return bool true if regex fields are supported
	 */
	public function supportsRegexFields();

	/**
	 * @param string $name
	 * @param string $target
	 * @param int $priority
	 * @return BaseHighlightedField
	 */
	public function newHighlightField(
		$name,
		$target,
		$priority = HighlightedField::DEFAULT_TARGET_PRIORITY
	): BaseHighlightedField;
}
