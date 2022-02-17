<?php

namespace CirrusSearch\Query;

use CirrusSearch\Parser\AST\KeywordFeatureNode;
use CirrusSearch\Query\Builder\QueryBuildingContext;
use CirrusSearch\Search\Escaper;
use CirrusSearch\Search\Fetch\HighlightedField;
use CirrusSearch\Search\Fetch\HighlightFieldGenerator;
use CirrusSearch\Search\Filters;
use CirrusSearch\Search\SearchContext;
use CirrusSearch\SearchConfig;
use Elastica\Query\AbstractQuery;

/**
 * Handles non-regexp version of insource: keyword.  The value
 * (including possible quotes) is used as part of a QueryString
 * query while allows some bit of advanced syntax. Because quotes
 * are included, if present, multi-word queries containing AND or
 * OR do not work.
 *
 * Examples:
 *   insource:Foo
 *   insource:Foo*
 *   insource:"gold rush"
 *
 * Regex support:
 *   insource:/abc?/
 *
 * Things that don't work:
 *   insource:"foo*"
 *   insource:"foo OR bar"
 */
class InSourceFeature extends BaseRegexFeature {

	/**
	 * Source field
	 */
	const FIELD = 'source_text';

	/**
	 * @var Escaper $escaper an escaper used to sanitize queries when not used as regular expression
	 *
	 * TODO: do not rely on escaper here, this should be consistent with what the Parser does.
	 * @see Filters::intitle()
	 */
	private $escaper;

	/**
	 * @param SearchConfig $config
	 */
	public function __construct( SearchConfig $config ) {
		parent::__construct( $config, [ self::FIELD => HighlightedField::TARGET_MAIN_SNIPPET ] );
		$this->escaper = new Escaper( $config->get( 'LanguageCode' ), $config->get( 'CirrusSearchAllowLeadingWildcard' ) );
	}

	/**
	 * @return string[]
	 */
	protected function getKeywords() {
		return [ 'insource' ];
	}

	/**
	 * @param SearchContext $context
	 * @param string $key
	 * @param string $value
	 * @param string $quotedValue
	 * @param bool $negated
	 * @return array
	 */
	protected function doApply( SearchContext $context, $key, $value, $quotedValue, $negated ) {
		$filter = Filters::insource( $context->escaper(), $quotedValue );
		if ( !$negated ) {
			foreach ( $this->doGetNonRegexHLFields( $context->getFetchPhaseBuilder(), $filter ) as $field ) {
				$context->getFetchPhaseBuilder()->addHLField( $field );
			}
		}
		return [ $filter, false ];
	}

	/**
	 * @param KeywordFeatureNode $node
	 * @param QueryBuildingContext $context
	 * @return AbstractQuery|null
	 */
	protected function getNonRegexFilterQuery( KeywordFeatureNode $node, QueryBuildingContext $context ) {
		return Filters::insource( $this->escaper, $node->getQuotedValue() );
	}

	/**
	 * @inheritDoc
	 */
	public function buildNonRegexHLFields( KeywordFeatureNode $node, QueryBuildingContext $buildingContext ) {
		$query = Filters::insource( $this->escaper, $node->getQuotedValue() );
		return $this->doGetNonRegexHLFields( $buildingContext->getHighlightFieldGenerator(), $query );
	}

	/**
	 * @param HighlightFieldGenerator $generator
	 * @param AbstractQuery $query
	 * @return HighlightedField[]
	 */
	private function doGetNonRegexHLFields( HighlightFieldGenerator $generator, AbstractQuery $query ): array {
		$field = $generator->newHighlightField( self::FIELD . '.plain',
			HighlightedField::TARGET_MAIN_SNIPPET, HighlightedField::EXPERT_SYNTAX_PRIORITY );
		$field->setHighlightQuery( $query );
		return [ $field ];
	}
}
