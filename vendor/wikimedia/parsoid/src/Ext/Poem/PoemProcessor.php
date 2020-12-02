<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Ext\Poem;

use DOMElement;
use Wikimedia\Parsoid\Ext\DOMProcessor;
use Wikimedia\Parsoid\Ext\DOMUtils;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;

class PoemProcessor extends DOMProcessor {

	/**
	 * @inheritDoc
	 */
	public function wtPostprocess(
		ParsoidExtensionAPI $extApi, DOMElement $node, array $options, bool $atTopLevel
	): void {
		if ( !$atTopLevel ) {
			return;
		}

		$c = $node->firstChild;
		while ( $c ) {
			if ( $c instanceof DOMElement ) {
				if ( DOMUtils::hasTypeOf( $c, 'mw:Extension/poem' ) ) {
					// Replace newlines found in <nowiki> fragment with <br/>s
					self::processNowikis( $c );
				} else {
					$this->wtPostprocess( $extApi, $c, $options, $atTopLevel );
				}
			}
			$c = $c->nextSibling;
		}
	}

	/**
	 * @param DOMElement $node
	 */
	private function processNowikis( DOMElement $node ): void {
		$doc = $node->ownerDocument;
		$c = $node->firstChild;
		while ( $c ) {
			if ( !$c instanceof DOMElement ) {
				$c = $c->nextSibling;
				continue;
			}

			if ( !DOMUtils::hasTypeOf( $c, 'mw:Nowiki' ) ) {
				self::processNowikis( $c );
				$c = $c->nextSibling;
				continue;
			}

			// Replace the nowiki's text node with a combination
			// of content and <br/>s. Take care to deal with
			// entities that are still entity-wrapped (!!).
			$cc = $c->firstChild;
			while ( $cc ) {
				$next = $cc->nextSibling;
				if ( DOMUtils::isText( $cc ) ) {
					$pieces = preg_split( '/\n/', $cc->nodeValue );
					$n = count( $pieces );
					$nl = '';
					for ( $i = 0;  $i < $n;  $i++ ) {
						$p = $pieces[$i];
						$c->insertBefore( $doc->createTextNode( $nl . $p ), $cc );
						if ( $i < $n - 1 ) {
							$c->insertBefore( $doc->createElement( 'br' ), $cc );
							$nl = "\n";
						}
					}
					$c->removeChild( $cc );
				}
				$cc = $next;
			}
			$c = $c->nextSibling;
		}
	}
}
