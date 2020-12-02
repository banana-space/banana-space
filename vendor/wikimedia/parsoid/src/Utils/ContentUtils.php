<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Utils;

use DOMDocument;
use DOMElement;
use DOMNode;
use Wikimedia\Assert\Assert;
use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Core\DomSourceRange;
use Wikimedia\Parsoid\Wt2Html\XMLSerializer;

/**
 * These utilities are for processing content that's generated
 * by parsing source input (ex: wikitext)
 */
class ContentUtils {
	/**
	 * XML Serializer.
	 *
	 * @param DOMNode $node
	 * @param array $options XMLSerializer options.
	 * @return string
	 */
	public static function toXML( DOMNode $node, array $options = [] ): string {
		return XMLSerializer::serialize( $node, $options )['html'];
	}

	/**
	 * dataobject aware XML serializer, to be used in the DOM post-processing phase.
	 *
	 * @param DOMNode $node
	 * @param array $options
	 * @return string
	 */
	public static function ppToXML( DOMNode $node, array $options = [] ): string {
		// We really only want to pass along `$options['keepTmp']`
		DOMDataUtils::visitAndStoreDataAttribs( $node, $options );
		return self::toXML( $node, $options );
	}

	/**
	 * .dataobject aware HTML parser, to be used in the DOM
	 * post-processing phase.
	 *
	 * @param Env $env
	 * @param string $html
	 * @param array|null $options
	 * @return DOMElement
	 */
	public static function ppToDOM( Env $env, string $html, array $options = [] ): DOMElement {
		$options += [
			'node' => null,
			'reinsertFosterableContent' => null,
		];
		$node = $options['node'];
		if ( $node === null ) {
			$node = DOMCompat::getBody( $env->createDocument( $html ) );
		} else {
			DOMUtils::assertElt( $node );
			DOMCompat::setInnerHTML( $node, $html );
		}

		if ( $options['reinsertFosterableContent'] ) {
			DOMUtils::visitDOM( $node, function ( $n, ...$args ) use ( $env ) {
				// untunnel fostered content
				$meta = WTUtils::reinsertFosterableContent( $env, $n, true );
				$n = $meta ?? $n;

				// load data attribs
				DOMDataUtils::loadDataAttribs( $n, ...$args );
			}, $options );
		} else {
			DOMDataUtils::visitAndLoadDataAttribs( $node, $options );
		}
		return $node;
	}

	/**
	 * Pull the data-parsoid script element out of the doc before serializing.
	 *
	 * @param DOMNode $node
	 * @param array $options XMLSerializer options.
	 * @return array
	 */
	public static function extractDpAndSerialize( DOMNode $node, array $options = [] ): array {
		$doc = DOMUtils::isBody( $node ) ? $node->ownerDocument : $node;
		$pb = DOMDataUtils::extractPageBundle( $doc );
		$out = XMLSerializer::serialize( $node, $options );
		$out['pb'] = $pb;
		return $out;
	}

	/**
	 * Strip Parsoid-inserted section wrappers and fallback id spans with
	 * HTML4 ids for headings from the DOM.
	 *
	 * @param DOMElement $node
	 */
	public static function stripSectionTagsAndFallbackIds( DOMElement $node ): void {
		$n = $node->firstChild;
		while ( $n ) {
			$next = $n->nextSibling;
			if ( $n instanceof DOMElement ) {
				// Recurse into subtree before stripping this
				self::stripSectionTagsAndFallbackIds( $n );

				// Strip <section> tags
				if ( WTUtils::isParsoidSectionTag( $n ) ) {
					DOMUtils::migrateChildren( $n, $n->parentNode, $n );
					$n->parentNode->removeChild( $n );
				}

				// Strip <span typeof='mw:FallbackId' ...></span>
				if ( WTUtils::isFallbackIdSpan( $n ) ) {
					$n->parentNode->removeChild( $n );
				}
			}
			$n = $next;
		}
	}

	/**
	 * @param DOMNode $node
	 * @param DOMNode $clone
	 * @param array $options
	 */
	private static function cloneData(
		DOMNode $node, DOMNode $clone, array $options
	): void {
		if ( !( $node instanceof DOMElement ) ) {
			return;
		}
		DOMUtils::assertElt( $clone );

		$d = DOMDataUtils::getNodeData( $node );
		DOMDataUtils::setNodeData( $clone,  Utils::clone( $d ) );
		$node = $node->firstChild;
		$clone = $clone->firstChild;
		while ( $node ) {
			self::cloneData( $node, $clone, $options );
			$node = $node->nextSibling;
			$clone = $clone->nextSibling;
		}
	}

	/**
	 * @param array $buf
	 * @param array &$opts
	 */
	private static function emit( array $buf, array &$opts ): void {
		$str = implode( "\n", $buf ) . "\n";
		if ( isset( $opts['outBuffer'] ) ) {
			$opts['outBuffer'] .= $str;
		} elseif ( isset( $opts['outStream'] ) ) {
			fwrite( $opts['outStream'], $str . "\n" );
		} else {
			error_log( $str );
		}
	}

	/**
	 * Shift the DSR of a DOM fragment.
	 * @param Env $env
	 * @param DOMNode $rootNode
	 * @param callable $dsrFunc
	 * @return DOMNode Returns the $rootNode passed in to allow chaining.
	 */
	public static function shiftDSR( Env $env, DOMNode $rootNode, callable $dsrFunc ): DOMNode {
		$doc = $rootNode->ownerDocument;
		$convertString = function ( $str ) {
			// Stub $convertString out to allow definition of a pair of
			// mutually-recursive functions.
			return $str;
		};
		$convertNode = function ( DOMNode $node ) use (
			$env, $dsrFunc, &$convertString, &$convertNode
		) {
			if ( !( $node instanceof DOMElement ) ) {
				return;
			}
			$dp = DOMDataUtils::getDataParsoid( $node );
			if ( ( $dp->dsr ?? null ) !== null ) {
				$dp->dsr = $dsrFunc( clone $dp->dsr );
				// We don't need to setDataParsoid because dp is not a copy
			}
			if ( ( $dp->tmp->origDSR ?? null ) !== null ) {
				// Even though tmp shouldn't escape Parsoid, go ahead and
				// convert to enable hybrid testing.
				$dp->tmp->origDSR = $dsrFunc( clone $dp->tmp->origDSR );
			}
			if ( ( $dp->extTagOffsets ?? null ) !== null ) {
				$dp->extTagOffsets = $dsrFunc( clone $dp->extTagOffsets );
			}

			// Handle embedded HTML in Language Variant markup
			$dmwv = DOMDataUtils::getJSONAttribute( $node, 'data-mw-variant', null );
			if ( $dmwv ) {
				if ( isset( $dmwv->disabled ) ) {
					$dmwv->disabled->t = $convertString( $dmwv->disabled->t );
				}
				if ( isset( $dmwv->twoway ) ) {
					foreach ( $dmwv->twoway as $l ) {
						$l->t = $convertString( $l->t );
					}
				}
				if ( isset( $dmwv->oneway ) ) {
					foreach ( $dmwv->oneway as $l ) {
						$l->f = $convertString( $l->f );
						$l->t = $convertString( $l->t );
					}
				}
				if ( isset( $dmwv->filter ) ) {
					$dmwv->filter->t = $convertString( $dmwv->filter->t );
				}
				DOMDataUtils::setJSONAttribute( $node, 'data-mw-variant', $dmwv );
			}

			if ( DOMUtils::matchTypeOf( $node, '#^mw:(ExpandedAttrs|Image|Extension)\b#D' ) ) {
				$dmw = DOMDataUtils::getDataMw( $node );
				// Handle embedded HTML in template-affected attributes
				if ( $dmw->attribs ?? null ) {
					foreach ( $dmw->attribs as &$a ) {
						foreach ( $a as $kOrV ) {
							if ( gettype( $kOrV ) !== 'string' && isset( $kOrV->html ) ) {
								$kOrV->html = $convertString( $kOrV->html );
							}
						}
					}
				}
				// Handle embedded HTML in figure-inline captions
				if ( $dmw->caption ?? null ) {
					$dmw->caption = $convertString( $dmw->caption );
				}
				// FIXME: Cite-specific handling here maybe?
				if ( $dmw->body->html ?? null ) {
					$dmw->body->html = $convertString( $dmw->body->html );
				}
				DOMDataUtils::setDataMw( $node, $dmw );
			}

			if ( DOMUtils::matchTypeOf( $node, '#^mw:DOMFragment(/|$)#D' ) ) {
				$dp = DOMDataUtils::getDataParsoid( $node );
				if ( $dp->html ?? null ) {
					$nodes = $env->getDOMFragment( $dp->html );
					foreach ( $nodes as $n ) {
						DOMPostOrder::traverse( $n, $convertNode );
					}
				}
			}
		};
		$convertString = function ( string $str ) use ( $doc, $env, $convertNode ): string {
			$parentNode = $doc->createElement( 'body' );
			$node = self::ppToDOM( $env, $str, [ 'node' => $parentNode ] );
			DOMPostOrder::traverse( $node, $convertNode );
			return self::ppToXML( $node, [ 'innerXML' => true ] );
		};
		DOMPostOrder::traverse( $rootNode, $convertNode );
		return $rootNode; // chainable
	}

	/**
	 * Convert DSR offsets in a Document between utf-8/ucs2/codepoint
	 * indices.
	 *
	 * Offset types are:
	 *  - 'byte': Bytes (UTF-8 encoding), e.g. PHP `substr()` or `strlen()`.
	 *  - 'char': Unicode code points (encoding irrelevant), e.g. PHP `mb_substr()` or `mb_strlen()`.
	 *  - 'ucs2': 16-bit code units (UTF-16 encoding), e.g. JavaScript `.substring()` or `.length`.
	 *
	 * @see TokenUtils::convertTokenOffsets for a related function on tokens.
	 *
	 * @param Env $env
	 * @param DOMDocument $doc The document to convert
	 * @param string $from Offset type to convert from.
	 * @param string $to Offset type to convert to.
	 */
	public static function convertOffsets(
		Env $env,
		DOMDocument $doc,
		string $from,
		string $to
	): void {
		$env->setCurrentOffsetType( $to );
		if ( $from === $to ) {
			return; // Hey, that was easy!
		}
		$offsetMap = [];
		$offsets = [];
		$collect = function ( int $n ) use ( &$offsetMap, &$offsets ) {
			if ( !array_key_exists( $n, $offsetMap ) ) {
				$box = PHPUtils::arrayToObject( [ 'value' => $n ] );
				$offsetMap[$n] = $box;
				$offsets[] =& $box->value;
			}
		};
		// Collect DSR offsets throughout the document
		$collectDSR = function ( DomSourceRange $dsr ) use ( $collect ) {
			if ( $dsr->start !== null ) {
				$collect( $dsr->start );
				$collect( $dsr->innerStart() );
			}
			if ( $dsr->end !== null ) {
				$collect( $dsr->innerEnd() );
				$collect( $dsr->end );
			}
			return $dsr;
		};
		$body = DOMCompat::getBody( $doc );
		self::shiftDSR( $env, $body, $collectDSR );
		if ( count( $offsets ) === 0 ) {
			return; /* nothing to do (shouldn't really happen) */
		}
		// Now convert these offsets
		TokenUtils::convertOffsets(
			$env->topFrame->getSrcText(), $from, $to, $offsets
		);
		// Apply converted offsets
		$applyDSR = function ( DomSourceRange $dsr ) use ( $offsetMap ) {
			$start = $dsr->start;
			$openWidth = $dsr->openWidth;
			if ( $start !== null ) {
				$start = $offsetMap[$start]->value;
				$openWidth = $offsetMap[$dsr->innerStart()]->value - $start;
			}
			$end = $dsr->end;
			$closeWidth = $dsr->closeWidth;
			if ( $end !== null ) {
				$end = $offsetMap[$end]->value;
				$closeWidth = $end - $offsetMap[$dsr->innerEnd()]->value;
			}
			return new DomSourceRange(
				$start, $end, $openWidth, $closeWidth
			);
		};
		self::shiftDSR( $env, $body, $applyDSR );
	}

	/**
	 * Dump the DOM with attributes.
	 *
	 * @param DOMNode $rootNode
	 * @param string $title
	 * @param array &$options
	 */
	public static function dumpDOM(
		DOMNode $rootNode, string $title, array &$options = []
	): void {
		if ( !empty( $options['storeDiffMark'] ) || !empty( $options['dumpFragmentMap'] ) ) {
			Assert::invariant( isset( $options['env'] ), "env should be set" );
		}

		if ( $rootNode instanceof DOMElement ) {
			// cloneNode doesn't clone data => walk DOM to clone it
			$clonedRoot = $rootNode->cloneNode( true );
			self::cloneData( $rootNode, $clonedRoot, $options );
		} else {
			$clonedRoot = $rootNode;
		}

		$buf = [];
		if ( empty( $options['quiet'] ) ) {
			$buf[] = '----- ' . $title . ' -----';
		}

		$buf[] = self::ppToXML( $clonedRoot, $options );
		self::emit( $buf, $options );

		// Dump cached fragments
		if ( !empty( $options['dumpFragmentMap'] ) ) {
			foreach ( $options['env']->getDOMFragmentMap() as $k => $fragment ) {
				$buf = [];
				$buf[] = str_repeat( '=', 15 );
				$buf[] = 'FRAGMENT ' . $k;
				$buf[] = '';
				self::emit( $buf, $options );

				$newOpts = $options;
				$newOpts['dumpFragmentMap'] = false;
				$newOpts['quiet'] = true;
				self::dumpDOM( is_array( $fragment ) ? $fragment[0] : $fragment, '', $newOpts );
			}
		}

		if ( empty( $options['quiet'] ) ) {
			self::emit( [ str_repeat( '-', mb_strlen( $title ) + 12 ) ], $options );
		}
	}

}
