<?php

namespace CirrusSearch\Query;

use CirrusSearch\Parser\AST\KeywordFeatureNode;
use CirrusSearch\Query\Builder\QueryBuildingContext;
use CirrusSearch\Search\Escaper;
use CirrusSearch\Search\Fetch\HighlightedField;
use CirrusSearch\Search\Filters;
use CirrusSearch\Search\SearchContext;
use CirrusSearch\SearchConfig;
use Elastica\Query\AbstractQuery;

/**
 * Applies a filter against the title field in elasticsearch. When not negated
 * the term remains in the original query as a scoring signal. The term itself
 * is used as a QueryString query, so some advanced syntax like * and phrase
 * matches can be used. Note that quotes in the incoming query are maintained
 * in the generated filter.
 *
 * Examples:
 *   intitle:Foo
 *   intitle:Foo*
 *   intitle:"gold rush"
 *
 * Things that might seem like they would work, but don't. This is because the
 * quotes are maintained in the filter and in the top level query.
 *   intitle:"foo*"
 *   intitle:"foo OR bar"
 */
class InTitleFeature extends BaseRegexFeature {

	/**
	 * @var Escaper $escaper an escaper used to sanitize queries when not used as regular expression
	 *
	 * TODO: do not rely on escaper here, this should be consistent with what the Parser does.
	 * @see Filters::intitle()
	 */
	private $escaper;

	public function __construct( SearchConfig $config ) {
		parent::__construct(
			$config,
			[
				'title' => HighlightedField::TARGET_TITLE_SNIPPET,
				'redirect.title' => HighlightedField::TARGET_REDIRECT_SNIPPET
			]
		);
		$this->escaper = new Escaper( $config->get( 'LanguageCode' ), $config->get( 'CirrusSearchAllowLeadingWildcard' ) );
	}

	/**
	 * @return string[]
	 */
	protected function getKeywords() {
		return [ 'intitle' ];
	}

	/**
	 * @param SearchContext $context
	 * @param string $key The keyword
	 * @param string $value The value attached to the keyword with quotes stripped
	 * @param string $quotedValue The original value in the search string, including quotes if used
	 * @param bool $negated Is the search negated? Not used to generate the returned AbstractQuery,
	 *  that will be negated as necessary. Used for any other building/context necessary.
	 * @return array Two element array, first an AbstractQuery or null to apply to the
	 *  query. Second a boolean indicating if the quotedValue should be kept in the search
	 *  string.
	 */
	protected function doApply( SearchContext $context, $key, $value, $quotedValue, $negated ) {
		$filter = Filters::intitle( $context->escaper(), $quotedValue );

		return [ $filter, !$negated ];
	}

	/**
	 * @param KeywordFeatureNode $node
	 * @param QueryBuildingContext $context
	 * @return AbstractQuery|null
	 */
	protected function getNonRegexFilterQuery( KeywordFeatureNode $node, QueryBuildingContext $context ) {
		return Filters::intitle( $this->escaper, $node->getQuotedValue() );
	}

	public function buildNonRegexHLFields( KeywordFeatureNode $node, QueryBuildingContext $context ) {
		// we highlight this field a bit differently as it's part of the main query
		return [];
	}
}
