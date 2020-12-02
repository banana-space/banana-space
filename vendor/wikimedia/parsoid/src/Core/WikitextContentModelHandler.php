<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Core;

use DOMDocument;

use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Html2Wt\SelectiveSerializer;
use Wikimedia\Parsoid\Html2Wt\WikitextSerializer;
use Wikimedia\Parsoid\Utils\ContentUtils;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMDataUtils;

class WikitextContentModelHandler extends ContentModelHandler {

	/**
	 * Fetch prior DOM for selser.
	 *
	 * @param Env $env
	 * @param SelserData $selserData
	 */
	private function setupSelser( Env $env, SelserData $selserData ) {
		// Why is it safe to use a reparsed dom for dom diff'ing?
		// (Since that's the only use of `env.page.dom`)
		//
		// There are two types of non-determinism to discuss:
		//
		//   * The first is from parsoid generated ids.  At this point,
		//     data-attributes have already been applied so there's no chance
		//     that variability in the ids used to associate data-attributes
		//     will lead to data being applied to the wrong nodes.
		//
		//     Further, although about ids will differ, they belong to the set
		//     of ignorable attributes in the dom differ.
		//
		//   * Templates, and encapsulated content in general, are the second.
		//     Since that content can change in between parses, the resulting
		//     dom might not be the same.  However, because dom diffing on
		//     on those regions only uses data-mw for comparision (which will
		//     remain constant between parses), this also shouldn't be an
		//     issue.
		//
		//     There is one caveat.  Because encapsulated content isn't
		//     guaranteed to be "balanced", the template affected regions
		//     may change between parses.  This should be rare.
		//
		// We therefore consider this safe since it won't corrupt the page
		// and, at worst, mixed up diff'ing annotations can end up with an
		// unfaithful serialization of the edit.
		//
		// However, in cases where original content is not returned by the
		// client / RESTBase, selective serialization cannot proceed and
		// we're forced to fallback to normalizing the entire page.  This has
		// proved unacceptable to editors as is and, as we lean heavier on
		// selser, will only get worse over time.
		//
		// So, we're forced to trade off the correctness for usability.
		if ( $selserData->oldHTML === null ) {
			$doc = $this->toDOM( $env );
		} else {
			$doc = $env->createDocument( $selserData->oldHTML );
		}
		$body = DOMCompat::getBody( $doc );
		DOMDataUtils::visitAndLoadDataAttribs( $body, [ 'markNew' => true ] );
		// Update DSR offsets if necessary.
		ContentUtils::convertOffsets(
			$env, $doc, $env->getRequestOffsetType(), 'byte'
		);
		$env->setOrigDOM( $body );
	}

	/**
	 * @inheritDoc
	 */
	public function toDOM( Env $env ): DOMDocument {
		return $env->getPipelineFactory()->parse(
			$env->getPageConfig()->getPageMainContent()
		);
	}

	/**
	 * @inheritDoc
	 */
	public function fromDOM(
		Env $env, DOMDocument $doc, ?SelserData $selserData = null
	): string {
		$serializerOpts = [
			'env' => $env,
			'selserData' => $selserData,
		];
		$Serializer = null;
		if ( $selserData ) {
			$Serializer = SelectiveSerializer::class;
			$this->setupSelser( $env, $selserData );
		} else {
			$Serializer = WikitextSerializer::class;
		}
		$serializer = new $Serializer( $serializerOpts );
		$env->getPageConfig()->editedDoc = $doc;
		$body = DOMCompat::getBody( $doc );
		DOMDataUtils::visitAndLoadDataAttribs( $body, [ 'markNew' => true ] );
		// Update DSR offsets if necessary.
		ContentUtils::convertOffsets(
			$env, $doc, $env->getRequestOffsetType(), 'byte'
		);
		return $serializer->serializeDOM( $body );
	}

}
