<?php

namespace Flow\Api;

use ApiBase;
use Flow\Conversion\Utils;
use Flow\Exception\WikitextException;

class ApiParsoidUtilsFlow extends ApiBase {

	public function execute() {
		$params = $this->extractRequestParams();
		$page = $this->getTitleOrPageId( $params );

		try {
			$content = Utils::convert(
				$params['from'],
				$params['to'],
				$params['content'],
				$page->getTitle()
			);
		} catch ( WikitextException $e ) {
			$code = $e->getErrorCode();
			$this->dieWithError( $code, $code,
				[ 'detail' => $e->getMessage() ], $e->getStatusCode()
			);
			return; // helps static analysis know execution does not continue past self::dieUsage
		}

		$result = [
			'format' => $params['to'],
			'content' => $content,
		];
		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	public function getAllowedParams() {
		return [
			'from' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => [ 'html', 'wikitext' ],
			],
			'to' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => [ 'html', 'wikitext' ],
			],
			'content' => [
				ApiBase::PARAM_REQUIRED => true,
			],
			'title' => null,
			'pageid' => [
				ApiBase::PARAM_ISMULTI => false,
				ApiBase::PARAM_TYPE => 'integer'
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			"action=flow-parsoid-utils&from=wikitext&to=html&content='''lorem'''+''blah''&title=Main_Page"
				=> 'apihelp-flow-parsoid-utils-example-1',
		];
	}
}
