<?php

namespace Flow\Parsoid;

use Flow\Conversion\Utils;
use Flow\Model\URLReference;
use Flow\Model\UUID;
use Flow\Model\WikiReference;
use Flow\Model\Workflow;
use Title;

class ReferenceFactory {
	/**
	 * @var string
	 */
	protected $wikiId;

	/**
	 * @var UUID
	 */
	protected $workflowId;

	/**
	 * @var Title
	 */
	protected $title;

	/**
	 * @var string
	 */
	protected $objectType;

	/**
	 * @var UUID
	 */
	protected $objectId;

	/**
	 * @param Workflow $workflow
	 * @param string $objectType
	 * @param UUID $objectId
	 */
	public function __construct( Workflow $workflow, $objectType, UUID $objectId ) {
		$this->wikiId = $workflow->getWiki();
		$this->workflowId = $workflow->getId();
		$this->title = $workflow->getArticleTitle();
		$this->objectType = $objectType;
		$this->objectId = $objectId;
	}

	/**
	 * @param string $refType
	 * @param string $value
	 * @return URLReference
	 */
	public function createUrlReference( $refType, $value ) {
		return new URLReference(
			UUID::create(),
			$this->wikiId,
			$this->workflowId,
			$this->title,
			$this->objectType,
			$this->objectId,
			$refType,
			$value
		);
	}

	/**
	 * @param string $refType
	 * @param string $value
	 * @return WikiReference|null
	 */
	public function createWikiReference( $refType, $value ) {
		$title = Utils::createRelativeTitle( $value, $this->title );

		if ( $title === null ) {
			return null;
		}

		// exclude virtual namespaces
		if ( $title->getNamespace() < 0 ) {
			return null;
		}

		return new WikiReference(
			UUID::create(),
			$this->wikiId,
			$this->workflowId,
			$this->title,
			$this->objectType,
			$this->objectId,
			$refType,
			$title
		);
	}
}
