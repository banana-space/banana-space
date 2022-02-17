<?php

namespace CirrusSearch\Parser;

use CirrusSearch\Parser\AST\ParsedQuery;

interface ParsedQueryClassifier {

	/**
	 * @param ParsedQuery $query
	 * @return string[]
	 */
	public function classify( ParsedQuery $query );

	/**
	 * @return string[]
	 */
	public function classes();
}
