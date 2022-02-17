<?php

namespace CirrusSearch\Query\Builder;

/**
 * A filter added as context to the search query
 */
interface ContextualFilter {
	/**
	 * @param FilterBuilder $builder
	 */
	public function populate( FilterBuilder $builder );

	/**
	 * @return int[]|null a list of namespace, an empty array for all namespaces or null
	 * if no extra namespaces are required.
	 */
	public function requiredNamespaces();
}
