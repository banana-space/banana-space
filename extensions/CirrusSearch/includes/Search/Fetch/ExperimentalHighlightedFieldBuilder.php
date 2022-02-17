<?php

namespace CirrusSearch\Search\Fetch;

use CirrusSearch\Search\SearchQuery;
use CirrusSearch\SearchConfig;

class ExperimentalHighlightedFieldBuilder extends BaseHighlightedField {
	const EXPERIMENTAL_HL_TYPE = 'experimental';

	/**
	 * @param string $fieldName
	 * @param string $target
	 * @param int $priority
	 */
	public function __construct( $fieldName, $target, $priority = self::DEFAULT_TARGET_PRIORITY ) {
		parent::__construct( $fieldName, self::EXPERIMENTAL_HL_TYPE, $target, $priority );
	}

	/**
	 * @return callable
	 */
	public static function entireValue(): callable {
		return function ( SearchConfig $config, $fieldName, $target, $priority ) {
			$self = new self( $fieldName, $target, $priority );
			$self->matchPlainFields();
			$self->setFragmenter( 'none' );
			$self->setNumberOfFragments( 1 );
			return $self;
		};
	}

	/**
	 * @return callable
	 */
	public static function redirectAndHeadings(): callable {
		return function ( SearchConfig $config, $fieldName, $target, $priority ) {
			$self = new self( $fieldName, $target, $priority );
			$self->matchPlainFields();
			$self->addOption( 'skip_if_last_matched', true );
			$self->setFragmenter( 'none' );
			$self->setOrder( 'score' );
			$self->setNumberOfFragments( 1 );
			return $self;
		};
	}

	/**
	 * @return callable
	 */
	public static function text(): callable {
		return function ( SearchConfig $config, $fieldName, $target, $priority ) {
			$self = new self( $fieldName, $target, $priority );
			$self->matchPlainFields();
			$self->addOption( 'skip_if_last_matched', true );
			$self->setFragmenter( 'scan' );
			$self->setNumberOfFragments( 1 );
			$self->setFragmentSize( $config->get( 'CirrusSearchFragmentSize' ) );
			$self->setOptions( [
				'top_scoring' => true,
				'boost_before' => [
					// Note these values are super arbitrary right now.
					'20' => 2,
					'50' => 1.8,
					'200' => 1.5,
					'1000' => 1.2,
				],
				// We should set a limit on the number of fragments we try because if we
				// don't then we'll hit really crazy documents, say 10MB of "d d".  This'll
				// keep us from scanning more then the first couple thousand of them.
				// Setting this too low (like 50) can bury good snippets if the search
				// contains common words.
				'max_fragments_scored' => 5000,
			] );
			return $self;
		};
	}

	/**
	 * @return callable
	 */
	protected static function mainText(): callable {
		return function ( SearchConfig $config, $fieldName, $target, $priority ) {
			$self = ( self::text() )( $config, $fieldName, $target, $priority );
			/** @var BaseHighlightedField $self */
			$self->setNoMatchSize( $config->get( 'CirrusSearchFragmentSize' ) );
			return $self;
		};
	}

	/**
	 * @param SearchConfig $config
	 * @param string $name
	 * @param string $target
	 * @param string $pattern
	 * @param bool $caseInsensitive
	 * @param int $priority
	 * @return self
	 */
	public static function newRegexField(
		SearchConfig $config,
		$name,
		$target,
		$pattern,
		$caseInsensitive,
		$priority
	): self {
		// TODO: verify that we actually need to have all the text() options when running a regex
		/** @var self $self */
		$self = ( self::text() )( $config, $name, $target, $priority );
		$self->addOption( 'regex', [ $pattern ] );
		$self->addOption( 'locale', $config->get( 'LanguageCode' ) );
		$self->addOption( 'regex_flavor', 'lucene' );
		$self->addOption( 'skip_query', true );
		$self->addOption( 'regex_case_insensitive', $caseInsensitive );
		$self->addOption( 'max_determinized_states', $config->get( 'CirrusSearchRegexMaxDeterminizedStates' ) );

		if ( $name == 'source_text.plain' ) {
			$self->setNoMatchSize( $config->get( 'CirrusSearchFragmentSize' ) );
		}
		return $self;
	}

	/**
	 * @inheritDoc
	 */
	public function merge( HighlightedField $other ): HighlightedField {
		if ( isset( $this->options['regex'] ) &&
			 $other instanceof ExperimentalHighlightedFieldBuilder &&
			 isset( $other->options['regex'] ) &&
			 $this->getFieldName() === $other->getFieldName()
		) {
			$this->options['regex'] = array_merge( $this->options['regex'], $other->options['regex'] );
			$mergedInsensitivity = $this->options['regex_case_insensitive'] || $other->options['regex_case_insensitive'];
			$this->options['regex_case_insensitive'] = $mergedInsensitivity;
			return $this;
		} else {
			return parent::merge( $other );
		}
	}

	/**
	 * @return ExperimentalHighlightedFieldBuilder
	 */
	public function skipIfLastMatched(): BaseHighlightedField {
		$this->addOption( 'skip_if_last_matched', true );
		return $this;
	}

	/**
	 * @return array
	 */
	public static function getFactories() {
		return [
			SearchQuery::SEARCH_TEXT => [
				'title' => self::entireValue(),
				'redirect.title' => self::redirectAndHeadings(),
				'category' => self::redirectAndHeadings(),
				'heading' => self::redirectAndHeadings(),
				'text' => self::mainText(),
				'source_text.plain' => self::mainText(),
				'auxiliary_text' => self::text(),
				'file_text' => self::text(),
			]
		];
	}
}
