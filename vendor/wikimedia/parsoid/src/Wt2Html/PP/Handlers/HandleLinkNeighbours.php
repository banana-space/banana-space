<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Wt2Html\PP\Handlers;

use DOMElement;
use DOMNode;

use DOMText;

use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\WTUtils;

class HandleLinkNeighbours {
	/**
	 * Function for fetching the link prefix based on a link node.
	 * The content will be reversed, so be ready for that.
	 *
	 * @param Env $env
	 * @param DOMNode|null $node
	 * @return array|null
	 */
	private static function getLinkPrefix( Env $env, ?DOMNode $node ): ?array {
		$regex = $env->getSiteConfig()->linkPrefixRegex();
		if ( !$regex ) {
			return null;
		}

		if ( $node instanceof DOMElement && WTUtils::hasParsoidAboutId( $node ) ) {
			$baseAbout = $node->getAttribute( 'about' );
		} else {
			$baseAbout = '';
		}

		if ( $node !== null ) {
			$node = $node->previousSibling;
		}
		return self::findAndHandleNeighbour( $env, false, $regex, $node, $baseAbout );
	}

	/**
	 * Function for fetching the link trail based on a link node.
	 *
	 * @param Env $env
	 * @param DOMNode|null $node
	 * @return array|null
	 */
	private static function getLinkTrail( Env $env, ?DOMNode $node ): ?array {
		$regex = $env->getSiteConfig()->linkTrailRegex();

		if ( !$regex ) {
			return null;
		}

		if ( $node instanceof DOMElement && WTUtils::hasParsoidAboutId( $node ) ) {
			$baseAbout = $node->getAttribute( 'about' );
		} else {
			$baseAbout = '';
		}

		if ( $node !== null ) {
			$node = $node->nextSibling;
		}
		return self::findAndHandleNeighbour( $env, true, $regex, $node, $baseAbout );
	}

	/**
	 * Abstraction of both link-prefix and link-trail searches.
	 *
	 * @param Env $env
	 * @param bool $goForward
	 * @param string $regex
	 * @param DOMNode|null $node
	 * @param string $baseAbout
	 * @return array
	 */
	private static function findAndHandleNeighbour(
		Env $env, bool $goForward, string $regex, ?DOMNode $node, string $baseAbout
	): array {
		$nextNode = $goForward ? 'nextSibling' : 'previousSibling';
		$innerNode = $goForward ? 'firstChild' : 'lastChild';
		$getInnerNeighbour = $goForward ? 'getLinkTrail' : 'getLinkPrefix';
		$result = [ 'content' => [], 'src' => '' ];

		while ( $node !== null ) {
			$nextSibling = $node->{ $nextNode };
			$document = $node->ownerDocument;

			if ( $node instanceof DOMText ) {
				$value = [ 'content' => $node, 'src' => $node->nodeValue ];
				if ( preg_match( $regex, $node->nodeValue, $matches ) ) {
					$value['src'] = $matches[0];
					if ( $value['src'] === $node->nodeValue ) {
						// entire node matches linkprefix/trail
						$node->parentNode->removeChild( $node );
					} else {
						// part of node matches linkprefix/trail
						$value['content'] = $document->createTextNode( $matches[0] );
						$tn = $document->createTextNode( preg_replace( $regex, '', $node->nodeValue ) );
						$node->parentNode->replaceChild( $tn, $node );
					}
				} else {
					break;
				}
			} elseif ( $node instanceof DOMElement && WTUtils::hasParsoidAboutId( $node ) &&
				$baseAbout !== '' && $node->getAttribute( 'about' ) === $baseAbout
			) {
				$value = self::{ $getInnerNeighbour }( $env, $node->{ $innerNode } );
			} else {
				break;
			}

			$vc = $value['content'];
			if ( $vc ) {
				if ( $vc instanceof DOMNode ) {
					$result['content'][] = $vc;
				} else { // $vs is array
					$result['content'] = array_merge( $result['content'], $vc );
				}
			}

			if ( $goForward ) {
				$result['src'] .= $value['src'];
			} else {
				$result['src'] = $value['src'] . $result['src'];
			}

			if ( $value['src'] !== $node->nodeValue ) {
				break;
			}

			$node = $nextSibling;
		}

		return $result;
	}

	/**
	 * Workhorse function for bringing linktrails and link prefixes into link content.
	 * NOTE that this function mutates the node's siblings on either side.
	 *
	 * @param DOMElement $node
	 * @param Env $env
	 * @return bool|DOMElement
	 */
	public static function handler( DOMElement $node, Env $env ) {
		$rel = $node->getAttribute( 'rel' );
		if ( !preg_match( '#^mw:WikiLink(/Interwiki)?$#D', $rel ) ) {
			return true;
		}

		$dp = DOMDataUtils::getDataParsoid( $node );
		$prefix = self::getLinkPrefix( $env, $node );
		$trail = self::getLinkTrail( $env, $node );

		if ( !empty( $prefix['content'] ) ) {
			foreach ( $prefix['content'] as &$pc ) {
				$node->insertBefore( $pc, $node->firstChild );
			}
			if ( !empty( $prefix['src'] ) ) {
				$dp->prefix = $prefix['src'];
				if ( DOMUtils::hasTypeOf( $node, 'mw:Transclusion' ) ) {
					// only necessary if we're the first
					$dataMW = DOMDataUtils::getDataMw( $node );
					if ( isset( $dataMW->parts ) ) {
						array_unshift( $dataMW->parts, $prefix['src'] );
					}
				}
				if ( !empty( $dp->dsr ) ) {
					$len = strlen( $prefix['src'] );
					$dp->dsr->start -= $len;
					$dp->dsr->openWidth += $len;
				}
			}
		}

		if ( !empty( $trail['content'] ) ) {
			foreach ( $trail['content'] as &$tc ) {
				$node->appendChild( $tc );
			}
			if ( !empty( $trail['src'] ) ) {
				$dp->tail = $trail['src'];
				$about = $node->getAttribute( 'about' );
				if ( WTUtils::hasParsoidAboutId( $node )
					&& count( WTUtils::getAboutSiblings( $node, $about ) ) === 1
				) {
					// search back for the first wrapper but
					// only if we're the last. otherwise can assume
					// template encapsulation will handle it
					$wrapper = WTUtils::findFirstEncapsulationWrapperNode( $node );
					if ( $wrapper instanceof DOMElement && DOMUtils::hasTypeOf( $wrapper, 'mw:Transclusion' ) ) {
						$dataMW = DOMDataUtils::getDataMw( $wrapper );
						if ( isset( $dataMW->parts ) ) {
							$dataMW->parts[] = $trail['src'];
						}
					}
				}
				if ( !empty( $dp->dsr ) ) {
					$len = strlen( $trail['src'] );
					$dp->dsr->end += $len;
					$dp->dsr->closeWidth += $len;
				}
			}
			// indicate that the node's tail siblings have been consumed
			return $node;
		} else {
			return true;
		}
	}
}
