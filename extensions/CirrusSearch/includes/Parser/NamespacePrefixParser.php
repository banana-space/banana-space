<?php

namespace CirrusSearch\Parser;

interface NamespacePrefixParser {

	/**
	 * @param string $query
	 * @return false|array false if no namespace was extracted, an array
	 * with the parsed query at index 0 and an array of namespaces at index
	 * 1 (or null for all namespaces).
	 */
	public function parse( $query );
}
