<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Html2Wt\DOMHandlers;

use DOMElement;
use DOMNode;
use LogicException;
use Wikimedia\Assert\Assert;
use Wikimedia\Parsoid\Core\ClientError;
use Wikimedia\Parsoid\Html2Wt\SerializerState;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\PHPUtils;
use Wikimedia\Parsoid\Utils\WTUtils;

class EncapsulatedContentHandler extends DOMHandler {

	/** @var array[] Maps list item HTML elements to the expected parent element */
	private $parentMap = [
		'li' => [ 'ul', 'ol' ],
		'dt' => [ 'dl' ],
		'dd' => [ 'dl' ],
	];

	public function __construct() {
		parent::__construct( false );
	}

	/**
	 * @inheritDoc
	 * @throws ClientError
	 */
	public function handle(
		DOMElement $node, SerializerState $state, bool $wrapperUnmodified = false
	): ?DOMNode {
		$env = $state->getEnv();
		$serializer = $state->serializer;
		$dp = DOMDataUtils::getDataParsoid( $node );
		$dataMw = DOMDataUtils::getDataMw( $node );
		$src = null;
		$transclusionType = DOMUtils::matchTypeOf( $node, '/^mw:(Transclusion|Param)$/' );
		$extType = DOMUtils::matchTypeOf( $node, '!^mw:Extension/!' );
		if ( $transclusionType ) {
			if ( !empty( $dataMw->parts ) ) {
				$src = $serializer->serializeFromParts( $state, $node, $dataMw->parts );
			} elseif ( isset( $dp->src ) ) {
				$env->log( 'error', 'data-mw missing in: ' . DOMCompat::getOuterHTML( $node ) );
				$src = $dp->src;
			} else {
				throw new ClientError(
					"Cannot serialize $transclusionType without data-mw.parts or data-parsoid.src"
				);
			}
		} elseif ( $extType ) {
			if ( ( $dataMw->name ?? null ) == '' && !isset( $dp->src ) ) {
				// If there was no typeOf name, and no dp.src, try getting
				// the name out of the mw:Extension type. This will
				// generate an empty extension tag, but it's better than
				// just an error.
				$extGivenName = substr( $extType, strlen( 'mw:Extension/' ) );
				if ( $extGivenName ) {
					$env->log( 'error', 'no data-mw name for extension in: ', DOMCompat::getOuterHTML( $node ) );
					$dataMw->name = $extGivenName;
				}
			}
			if ( ( $dataMw->name ?? null ) != '' ) {
				$ext = $env->getSiteConfig()->getExtTagImpl( $dataMw->name );
				if ( $ext ) {
					$src = $ext->domToWikitext( $state->extApi, $node, $wrapperUnmodified );
					if ( $src === false ) {
						$src = $serializer->defaultExtensionHandler( $node, $state );
					}
				} else {
					$src = $serializer->defaultExtensionHandler( $node, $state );
				}
			} elseif ( isset( $dp->src ) ) {
				$env->log( 'error', 'data-mw missing in: ' . DOMCompat::getOuterHTML( $node ) );
				$src = $dp->src;
			} else {
				throw new ClientError( 'Cannot serialize extension without data-mw.name or data-parsoid.src.' );
			}
		} elseif ( DOMUtils::hasTypeOf( $node, 'mw:LanguageVariant' ) ) {
			$state->serializer->languageVariantHandler( $node );
			return $node->nextSibling;
		} else {
			throw new LogicException( 'Should never reach here' );
		}
		$state->singleLineContext->disable();
		// FIXME: https://phabricator.wikimedia.org/T184779
		$src = PHPUtils::coalesce( $dataMw->extPrefix ?? null, '' ) . $src
			. PHPUtils::coalesce( $dataMw->extSuffix ?? null, '' );
		$serializer->emitWikitext( $this->handleListPrefix( $node, $state ) . $src, $node );
		$state->singleLineContext->pop();
		return WTUtils::skipOverEncapsulatedContent( $node );
	}

	// XXX: This is questionable, as the template can expand
	// to newlines too. Which default should we pick for new
	// content? We don't really want to make separator
	// newlines in HTML significant for the semantics of the
	// template content.

	/** @inheritDoc */
	public function before( DOMElement $node, DOMNode $otherNode, SerializerState $state ): array {
		$env = $state->getEnv();
		$dataMw = DOMDataUtils::getDataMw( $node );
		$dp = DOMDataUtils::getDataParsoid( $node );

		// Handle native extension constraints.
		if ( DOMUtils::matchTypeOf( $node, '!^mw:Extension/!' )
			// Only apply to plain extension tags.
			 && !DOMUtils::hasTypeOf( $node, 'mw:Transclusion' )
		) {
			if ( isset( $dataMw->name ) ) {
				$extConfig = $env->getSiteConfig()->getExtTagConfig( $dataMw->name );
				if ( ( $extConfig['options']['html2wt']['format'] ?? '' ) === 'block' &&
					WTUtils::isNewElt( $node )
				) {
					return [ 'min' => 1, 'max' => 2 ];
				}
			}
		}

		// If this content came from a multi-part-template-block
		// use the first node in that block for determining
		// newline constraints.
		if ( isset( $dp->firstWikitextNode ) ) {
			$h = ( new DOMHandlerFactory )->newFromTagHandler( mb_strtolower( $dp->firstWikitextNode ) );
			if ( !$h && ( $dp->stx ?? null ) === 'html' && $dp->firstWikitextNode !== 'a' ) {
				$h = new FallbackHTMLHandler();
			}
			if ( $h ) {
				return $h->before( $node, $otherNode, $state );
			}
		}

		// default behavior
		return [];
	}

	/** @inheritDoc */
	public function after( DOMElement $node, DOMNode $otherNode, SerializerState $state ): array {
		$env = $state->getEnv();
		$dataMw = DOMDataUtils::getDataMw( $node );

		// Handle native extension constraints.
		if ( DOMUtils::matchTypeOf( $node, '!^mw:Extension/!' )
			// Only apply to plain extension tags.
			 && !DOMUtils::hasTypeOf( $node, 'mw:Transclusion' )
		) {
			if ( isset( $dataMw->name ) ) {
				$extConfig = $env->getSiteConfig()->getExtTagConfig( $dataMw->name );
				if ( ( $extConfig['options']['html2wt']['format'] ?? '' ) === 'block' &&
					WTUtils::isNewElt( $node ) && !DOMUtils::isBody( $otherNode )
				) {
					return [ 'min' => 1, 'max' => 2 ];
				}
			}
		}

		// default behavior
		return [];
	}

	/**
	 * @param DOMElement $node
	 * @param SerializerState $state
	 * @return string
	 */
	private function handleListPrefix( DOMElement $node, SerializerState $state ): string {
		$bullets = '';
		if ( DOMUtils::isListOrListItem( $node )
			&& !$this->parentBulletsHaveBeenEmitted( $node )
			&& !DOMUtils::previousNonSepSibling( $node ) // Maybe consider parentNode.
			&& $this->isTplListWithoutSharedPrefix( $node )
			// Nothing to do for definition list rows,
			// since we're emitting for the parent node.
			 && !( $node->nodeName === 'dd'
				   && ( DOMDataUtils::getDataParsoid( $node )->stx ?? null ) === 'row' )
		) {
			// phan fails to infer that the parent of a DOMElement is always a DOMElement
			$parentNode = $node->parentNode;
			'@phan-var DOMElement $parentNode';
			$bullets = $this->getListBullets( $state, $parentNode );
		}
		return $bullets;
	}

	/**
	 * Normally we wait until hitting the deepest nested list element before
	 * emitting bullets. However, if one of those list elements is about-id
	 * marked, the tag handler will serialize content from data-mw parts or src.
	 * This is a problem when a list wasn't assigned the shared prefix of bullets.
	 * For example,
	 *
	 * ** a
	 * ** b
	 *
	 * Will assign bullets as,
	 *
	 * <ul><li-*>
	 * <ul>
	 * <li-*> a</li>   <!-- no shared prefix  -->
	 * <li-**> b</li>  <!-- gets both bullets -->
	 * </ul>
	 * </li></ul>
	 *
	 * For the b-li, getListsBullets will walk up and emit the two bullets it was
	 * assigned. If it was about-id marked, the parts would contain the two bullet
	 * start tag it was assigned. However, for the a-li, only one bullet is
	 * associated. When it's about-id marked, serializing the data-mw parts or
	 * src would miss the bullet assigned to the container li.
	 *
	 * @param DOMElement $node
	 * @return bool
	 */
	private function isTplListWithoutSharedPrefix( DOMElement $node ): bool {
		if ( !WTUtils::isEncapsulationWrapper( $node ) ) {
			return false;
		}

		if ( DOMUtils::hasTypeOf( $node, 'mw:Transclusion' ) ) {
			// If the first part is a string, template ranges were expanded to
			// include this list element. That may be trouble. Otherwise,
			// containers aren't part of the template source and we should emit
			// them.
			$dataMw = DOMDataUtils::getDataMw( $node );
			if ( !isset( $dataMw->parts ) || !is_string( $dataMw->parts[0] ) ) {
				return true;
			}
			// Less than two bullets indicates that a shared prefix was not
			// assigned to this element. A safe indication that we should call
			// getListsBullets on the containing list element.
			return !preg_match( '/^[*#:;]{2,}$/D', $dataMw->parts[0] );
		} elseif ( DOMUtils::matchTypeOf( $node, '/^mw:(Extension|Param)/' ) ) {
			// Containers won't ever be part of the src here, so emit them.
			return true;
		} else {
			return false;
		}
	}

	/**
	 * @param DOMElement $node
	 * @return bool
	 */
	private function parentBulletsHaveBeenEmitted( DOMElement $node ): bool {
		if ( WTUtils::isLiteralHTMLNode( $node ) ) {
			return true;
		} elseif ( DOMUtils::isList( $node ) ) {
			return !DOMUtils::isListItem( $node->parentNode );
		} else {
			Assert::invariant( DOMUtils::isListItem( $node ),
				'$node must be a list, list item or literal html node' );
			$parentNode = $node->parentNode;
			// Skip builder-inserted wrappers
			while ( $this->isBuilderInsertedElt( $parentNode ) ) {
				$parentNode = $parentNode->parentNode;
			}
			return !in_array( $parentNode->nodeName, $this->parentMap[$node->nodeName], true );
		}
	}
}
