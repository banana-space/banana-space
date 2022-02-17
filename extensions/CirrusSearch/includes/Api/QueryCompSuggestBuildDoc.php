<?php

namespace CirrusSearch\Api;

use ApiQuery;
use ApiQueryBase;
use CirrusSearch\BuildDocument\Completion\SuggestBuilder;
use Elastica\Document;
use InvalidArgumentException;

class QueryCompSuggestBuildDoc extends ApiQueryBase {
	use ApiTrait;

	public function __construct( ApiQuery $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'csb' );
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$method = $this->getParameter( 'method' );
		try {
			$builder = SuggestBuilder::create( $this->getCirrusConnection(), $method );
		} catch ( InvalidArgumentException $e ) {
			$this->addError( 'apierror-compsuggestbuilddoc-bad-method' );
			return;
		}

		foreach ( $this->getPageSet()->getGoodTitles() as $origPageId => $title ) {
			$docs = $this->loadDocuments( $title );
			$this->addExplanation( $builder, $origPageId, $docs );
		}
	}

	protected function getAllowedParams() {
		return [
			'method' => [
				self::PARAM_TYPE => 'string',
				self::PARAM_DFLT => $this->getSearchConfig()->get( 'CirrusSearchCompletionDefaultScore' ),
				self::PARAM_HELP_MSG => 'apihelp-query+compsuggestbuilddoc-param-method',
			],
		];
	}

	private function addExplanation( SuggestBuilder $builder, $pageId, array $docs ) {
		$docs = array_map(
			function ( Document $d ) {
				return [ $d->getId() => $d->getData() ];
			}, $builder->build( $docs, true )
		);

		foreach ( $docs as $doc ) {
			$this->getResult()->addValue(
				[ 'query', 'pages', $pageId ],
				'cirruscompsuggestbuilddoc',
				$doc
			);
		}
	}
}
