<?php

namespace CirrusSearch\Search\Fetch;

use CirrusSearch\SearchConfig;
use CirrusSearch\Searcher;
use Elastica\Query\AbstractQuery;
use Wikimedia\Assert\Assert;

/**
 * Class holding the building state of the fetch phase elements of
 * an elasticsearch query.
 * Currently only supports the highlight section but can be extended to support
 * source filtering and stored field.
 */
class FetchPhaseConfigBuilder implements HighlightFieldGenerator {

	/** @var HighlightedField[] */
	private $highlightedFields = [];

	/** @var SearchConfig */
	private $config;

	/**
	 * @var string
	 */
	private $factoryGroup;

	/**
	 * FetchPhaseConfigBuilder constructor.
	 * @param SearchConfig $config
	 * @param string|null $factoryGroup
	 */
	public function __construct( SearchConfig $config, $factoryGroup = null ) {
		$this->config = $config;
		$this->factoryGroup = $factoryGroup;
	}

	/**
	 * @inheritDoc
	 */
	public function newHighlightField(
		$name,
		$target,
		$priority = HighlightedField::DEFAULT_TARGET_PRIORITY
	): BaseHighlightedField {
		$useExp = $this->config->get( 'CirrusSearchUseExperimentalHighlighter' );
		if ( $useExp ) {
			$factories = ExperimentalHighlightedFieldBuilder::getFactories();
		} else {
			$factories = BaseHighlightedField::getFactories();
		}
		if ( $this->factoryGroup !== null && isset( $factories[$this->factoryGroup][$name] ) ) {
			return ( $factories[$this->factoryGroup][$name] )( $this->config, $name, $target, $priority );
		}
		if ( $useExp ) {
			return new ExperimentalHighlightedFieldBuilder( $name, $target, $priority );
		} else {
			return new BaseHighlightedField( $name, BaseHighlightedField::FVH_HL_TYPE, $target, $priority );
		}
	}

	/**
	 * @param string $name
	 * @param string $target
	 * @param string $pattern
	 * @param bool $caseInsensitive
	 * @param int $priority
	 */
	public function addNewRegexHLField(
		$name,
		$target,
		$pattern,
		$caseInsensitive,
		$priority = HighlightedField::COSTLY_EXPERT_SYNTAX_PRIORITY
	) {
		if ( !$this->supportsRegexFields() ) {
			return;
		}
		$this->addHLField( $this->newRegexField( $name, $target, $pattern, $caseInsensitive, $priority ) );
	}

	/**
	 * Whether this builder can generate regex fields
	 * @return bool
	 */
	public function supportsRegexFields() {
		return (bool)$this->config->get( 'CirrusSearchUseExperimentalHighlighter' );
	}

	/**
	 * @inheritDoc
	 */
	public function newRegexField(
		$name,
		$target,
		$pattern,
		$caseInsensitive,
		$priority = HighlightedField::COSTLY_EXPERT_SYNTAX_PRIORITY
	): BaseHighlightedField {
		Assert::precondition( $this->supportsRegexFields(), 'Regex fields not supported' );
		return ExperimentalHighlightedFieldBuilder::newRegexField(
			$this->config, $name, $target, $pattern, $caseInsensitive, $priority );
	}

	/**
	 * @param HighlightedField $field
	 */
	public function addHLField( HighlightedField $field ) {
		$prev = $this->highlightedFields[$field->getFieldName()] ?? null;
		if ( $prev === null ) {
			$this->highlightedFields[$field->getFieldName()] = $field;
		} else {
			$this->highlightedFields[$field->getFieldName()] = $prev->merge( $field );
		}
	}

	/**
	 * @param string $field
	 * @return HighlightedField|null
	 */
	public function getHLField( $field ) {
		return $this->highlightedFields[$field] ?? null;
	}

	/**
	 * @param AbstractQuery|null $mainHLQuery
	 * @return array
	 */
	public function buildHLConfig( AbstractQuery $mainHLQuery = null ): array {
		$fields = [];
		foreach ( $this->highlightedFields as $field ) {
			$fields[$field->getFieldName()] = $field->toArray();
		}
		$config = [
			'pre_tags' => [ Searcher::HIGHLIGHT_PRE_MARKER ],
			'post_tags' => [ Searcher::HIGHLIGHT_POST_MARKER ],
			'fields' => $fields,
		];

		if ( $mainHLQuery !== null ) {
			$config['highlight_query'] = $mainHLQuery->toArray();
		}

		return $config;
	}

	/**
	 * @param SearchConfig $config
	 * @return FetchPhaseConfigBuilder
	 */
	public function withConfig( SearchConfig $config ): self {
		return new self( $config, $this->factoryGroup );
	}

	/**
	 * Return the list of highlighted fields indexed per target
	 * and ordered by priority (reverse natural order)
	 * @return HighlightedField[][]
	 */
	public function getHLFieldsPerTargetAndPriority(): array {
		$fields = [];
		foreach ( $this->highlightedFields as $f ) {
			$fields[$f->getTarget()][] = $f;
		}
		return array_map(
			function ( array $v ) {
				usort( $v, function ( HighlightedField $g1, HighlightedField $g2 ) {
					return $g2->getPriority() <=> $g1->getPriority();
				} );
				return $v;
			},
			$fields
		);
	}

	public function configureDefaultFullTextFields() {
		// TODO: find a better place for this
		// Title/redir/category/template
		$field = $this->newHighlightField( 'title', HighlightedField::TARGET_TITLE_SNIPPET );
		$this->addHLField( $field );
		$field = $this->newHighlightField( 'redirect.title', HighlightedField::TARGET_REDIRECT_SNIPPET );
		$this->addHLField( $field->skipIfLastMatched() );
		$field = $this->newHighlightField( 'category', HighlightedField::TARGET_CATEGORY_SNIPPET );
		$this->addHLField( $field->skipIfLastMatched() );

		$field = $this->newHighlightField( 'heading', HighlightedField::TARGET_SECTION_SNIPPET );
		$this->addHLField( $field->skipIfLastMatched() );

		// content
		$field = $this->newHighlightField( 'text', HighlightedField::TARGET_MAIN_SNIPPET );
		$this->addHLField( $field );

		$field = $this->newHighlightField( 'auxiliary_text', HighlightedField::TARGET_MAIN_SNIPPET );
		$this->addHLField( $field->skipIfLastMatched() );

		$field = $this->newHighlightField( 'file_text', HighlightedField::TARGET_MAIN_SNIPPET );
		$this->addHLField( $field->skipIfLastMatched() );
	}
}
