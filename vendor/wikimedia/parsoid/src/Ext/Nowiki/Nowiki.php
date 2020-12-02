<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Ext\Nowiki;

use DOMDocument;
use DOMElement;
use DOMText;
use Wikimedia\Assert\Assert;
use Wikimedia\Parsoid\Ext\DOMDataUtils;
use Wikimedia\Parsoid\Ext\DOMUtils;
use Wikimedia\Parsoid\Ext\ExtensionModule;
use Wikimedia\Parsoid\Ext\ExtensionTagHandler;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;
use Wikimedia\Parsoid\Ext\Utils;
use Wikimedia\Parsoid\Utils\DOMCompat;

/**
 * Nowiki treats anything inside it as plain text.
 */
class Nowiki extends ExtensionTagHandler implements ExtensionModule {

	/** @inheritDoc */
	public function getConfig(): array {
		return [
			'name' => '<nowiki>',
			'tags' => [
				[
					'name' => 'nowiki',
					'handler' => self::class,
				]
			]
		];
	}

	/** @inheritDoc */
	public function sourceToDom(
		ParsoidExtensionAPI $extApi, string $txt, array $extArgs
	): DOMDocument {
		$doc = $extApi->htmlToDom( '' ); // Empty doc
		$span = $doc->createElement( 'span' );
		DOMUtils::addTypeOf( $span, 'mw:Nowiki' );

		foreach ( preg_split( '/(&[#0-9a-zA-Z]+;)/', $txt, -1, PREG_SPLIT_DELIM_CAPTURE ) as $i => $t ) {
			if ( $i % 2 === 1 ) {
				$cc = Utils::decodeWtEntities( $t );
				if ( $cc !== $t ) {
					// This should match the output of the "htmlentity" rule
					// in the tokenizer.
					$entity = $doc->createElement( 'span' );
					DOMUtils::addTypeOf( $entity, 'mw:Entity' );
					DOMDataUtils::setDataParsoid( $entity, (object)[
						'src' => $t,
						'srcContent' => $cc,
					] );
					$entity->appendChild( $doc->createTextNode( $cc ) );
					$span->appendChild( $entity );
					continue;
				}
				// else, fall down there
			}
			$span->appendChild( $doc->createTextNode( $t ) );
		}

		$span->normalize();
		DOMCompat::getBody( $doc )->appendChild( $span );
		return $doc;
	}

	/** @inheritDoc */
	public function domToWikitext(
		ParsoidExtensionAPI $extApi, DOMElement $node, bool $wrapperUnmodified
	) {
		if ( !$node->hasChildNodes() ) {
			$extApi->setHtml2wtStateFlag( 'hasSelfClosingNowikis' ); // FIXME
			return '<nowiki/>';
		}
		$str = '<nowiki>';
		for ( $child = $node->firstChild;  $child;  $child = $child->nextSibling ) {
			$out = null;
			if ( $child instanceof DOMElement ) {
				if ( DOMUtils::isDiffMarker( $child ) ) {
					/* ignore */
				} elseif ( $child->nodeName === 'span' &&
					DOMUtils::hasTypeOf( $child, 'mw:Entity' ) &&
					DOMUtils::hasNChildren( $child, 1 )
				) {
					$dp = DOMDataUtils::getDataParsoid( $child );
					if ( isset( $dp->src ) && $dp->srcContent === $child->textContent ) {
						// Unedited content
						$out = $dp->src;
					} else {
						// Edited content
						$out = Utils::entityEncodeAll( $child->firstChild->nodeValue );
					}
				} else {
					/* This is a hacky fallback for what is essentially
					 * undefined behavior. No matter what we emit here,
					 * this won't roundtrip html2html. */
					$extApi->log( 'error/html2wt/nowiki', 'Invalid nowiki content' );
					$out = $child->textContent;
				}
			} elseif ( $child instanceof DOMText ) {
				$out = $child->nodeValue;
			} else {
				Assert::invariant( DOMUtils::isComment( $child ), "Expected a comment here" );
				/* Comments can't be embedded in a <nowiki> */
				$extApi->log( 'error/html2wt/nowiki',
					'Discarded invalid embedded comment in a <nowiki>' );
				$out = '';
			}

			// Always escape any nowikis found in $out
			if ( $out ) {
				// Inlined helper that previously existed in Parsoid's WT Utils
				$str .= preg_replace( '#<(/?nowiki\s*/?\s*)>#i', '&lt;$1&gt;', $out );
			}
		}

		return $str . '</nowiki>';
	}

}
