<?php

namespace Flow\Parsoid;

use DOMXPath;
use Flow\Conversion\Utils;
use Flow\Exception\InvalidReferenceException;
use Flow\Model\Reference;
use Flow\Model\UUID;
use Flow\Model\Workflow;
use MWException;

/**
 * Extracts references to templates, files and pages (in the form of links)
 * from Parsoid HTML.
 */
class ReferenceExtractor {
	/**
	 * @var Extractor[][] Map from revision type (AbstractRevision::getRevisionType())
	 *  to list of Extractor objects to use.
	 */
	protected $extractors;

	/**
	 * @param Extractor[][] $extractors Map from revision type (AbstractRevision::getRevisionType())
	 *  to a list of extractors to use.
	 */
	public function __construct( array $extractors ) {
		$this->extractors = $extractors;
	}

	/**
	 * @param Workflow $workflow
	 * @param string $objectType
	 * @param UUID $objectId
	 * @param string $text
	 * @return array
	 */
	public function getReferences( Workflow $workflow, $objectType, UUID $objectId, $text ) {
		if ( isset( $this->extractors[$objectType] ) ) {
			return $this->extractReferences(
				new ReferenceFactory( $workflow, $objectType, $objectId ),
				$this->extractors[$objectType],
				$text
			);
		} else {
			throw new \Exception( "No extractors available for $objectType" );
		}
	}

	/**
	 * @param ReferenceFactory $factory
	 * @param Extractor[] $extractors
	 * @param string $text
	 * @return Reference[]
	 * @throws MWException
	 * @throws \Flow\Exception\WikitextException
	 */
	protected function extractReferences( ReferenceFactory $factory, array $extractors, $text ) {
		$dom = Utils::createDOM( $text );

		$output = [];

		$xpath = new DOMXPath( $dom );

		foreach ( $extractors as $extractor ) {
			$elements = $xpath->query( $extractor->getXPath() );

			if ( !$elements ) {
				$class = get_class( $extractor );
				throw new MWException( "Malformed xpath from $class: " . $extractor->getXPath() );
			}

			foreach ( $elements as $element ) {
				try {
					$ref = $extractor->perform( $factory, $element );
				} catch ( InvalidReferenceException $e ) {
					wfDebugLog( 'Flow', 'Invalid reference detected, skipping element' );
					$ref = null;
				}
				// no reference was generated
				if ( $ref === null ) {
					continue;
				}
				// reference points to a special page
				if ( $ref->getSrcTitle()->isSpecialPage() ) {
					continue;
				}

				$output[] = $ref;
			}
		}

		return $output;
	}
}
