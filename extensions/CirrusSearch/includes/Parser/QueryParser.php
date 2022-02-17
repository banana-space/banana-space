<?php

namespace CirrusSearch\Parser;

use CirrusSearch\Parser\AST\ParsedQuery;
use CirrusSearch\Parser\QueryStringRegex\SearchQueryParseException;

/**
 * Query parser.
 *
 * Parse a user query (usually fulltext query) into a ParsedQuery
 */
interface QueryParser {

	/**
	 * Parse a user query.
	 * @param string $query
	 * @return ParsedQuery
	 * @throws SearchQueryParseException
	 */
	public function parse( string $query ): ParsedQuery;
}
