<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Wt2Html\PP\Handlers;

use DOMComment;
use DOMElement;
use DOMText;
use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Core\DomSourceRange;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\Utils;
use Wikimedia\Parsoid\Utils\WTUtils;
use Wikimedia\Parsoid\Wt2Html\TT\Sanitizer;

/**
 * Apply french space armoring.
 *
 * See https://www.mediawiki.org/wiki/Specs/HTML#Display_space
 */
class DisplaySpace {

	/**
	 * @param DOMText $node
	 * @return ?int
	 */
	private static function getTextNodeDSRStart( DOMText $node ): ?int {
		$parent = $node->parentNode;
		'@phan-var DOMElement $parent';  /** @var DOMElement $parent */
		$dsr = DOMDataUtils::getDataParsoid( $parent )->dsr ?? null;
		if ( !Utils::isValidDSR( $dsr, true ) ) {
			return null;
		}
		$start = $dsr->innerStart();
		$c = $parent->firstChild;
		while ( $c !== $node ) {
			if ( $c instanceof DOMComment ) {
				$start += WTUtils::decodedCommentLength( $c );
			} elseif ( $c instanceof DOMText ) {
				$start += strlen( $c->nodeValue );
			} else {
				'@phan-var DOMElement $c';  /** @var DOMElement $c */
				$dsr = DOMDataUtils::getDataParsoid( $c )->dsr ?? null;
				if ( !Utils::isValidDSR( $dsr ) ) {
					return null;
				}
				$start = $dsr->end;
			}
			$c = $c->nextSibling;
		}
		return $start;
	}

	/**
	 * @param DOMText $node
	 * @param int $offset
	 */
	private static function insertDisplaySpace(
		DOMText $node, int $offset
	): void {
		$str = $str = $node->nodeValue;

		$prefix = substr( $str, 0, $offset );
		$suffix = substr( $str, $offset + 1 );

		$node->nodeValue = $prefix;

		$doc = $node->ownerDocument;
		$post = $doc->createTextNode( $suffix );
		$node->parentNode->insertBefore( $post, $node->nextSibling );

		$start = self::getTextNodeDSRStart( $node );
		if ( $start !== null ) {
			$start += strlen( $prefix );
			$dsr = new DomSourceRange( $start, $start + 1, 0, 0 );
		} else {
			$dsr = new DomSourceRange( null, null, null, null );
		}

		$span = $doc->createElement( 'span' );
		$span->appendChild( $doc->createTextNode( "\u{00A0}" ) );
		// FIXME(T254502): Do away with the mw:Placeholder and the associated
		// data-parsoid.src
		$span->setAttribute( 'typeof', 'mw:DisplaySpace mw:Placeholder' );
		DOMDataUtils::setDataParsoid( $span, (object)[
			'src' => ' ', 'dsr' => $dsr,
		] );
		$node->parentNode->insertBefore( $span, $post );
	}

	/**
	 * French spaces, Guillemet-left
	 *
	 * @param DOMText $node
	 * @param Env $env
	 * @return bool|DOMElement
	 */
	public static function leftHandler( DOMText $node, Env $env ) {
		$key = array_keys( array_slice( Sanitizer::FIXTAGS, 0, 1 ) )[0];
		if ( preg_match( $key, $node->nodeValue, $matches, PREG_OFFSET_CAPTURE ) ) {
			$offset = $matches[0][1];
			self::insertDisplaySpace( $node, $offset );
			return true;
		}
		return true;
	}

	/**
	 * French spaces, Guillemet-right
	 *
	 * @param DOMText $node
	 * @param Env $env
	 * @return bool|DOMElement
	 */
	public static function rightHandler( DOMText $node, Env $env ) {
		$key = array_keys( array_slice( Sanitizer::FIXTAGS, 1, 1 ) )[0];
		if ( preg_match( $key, $node->nodeValue, $matches, PREG_OFFSET_CAPTURE ) ) {
			$offset = $matches[1][1] + strlen( $matches[1][0] );
			self::insertDisplaySpace( $node, $offset );
			return true;
		}
		return true;
	}

}
