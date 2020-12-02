<?php
declare( strict_types = 1 );

/**
 * This is a Serializer class that will compare two versions of a DOM
 * and re-use the original wikitext for unmodified regions of the DOM.
 * Originally this relied on special change markers inserted by the
 * editor, but we now generate these ourselves using DOMDiff.
 */

namespace Wikimedia\Parsoid\Html2Wt;

use DOMElement;
use Wikimedia\Assert\Assert;
use Wikimedia\Parsoid\Core\SelserData;
use Wikimedia\Parsoid\Utils\ContentUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\Timing;

/**
 * If we have the page source (this.env.page.src), we use the selective
 * serialization method, only reporting the serialized wikitext for parts of
 * the page that changed. Else, we fall back to serializing the whole DOM.
 */
class SelectiveSerializer {
	private $env;
	private $wts;
	private $trace;
	private $metrics;

	/** @var SelserData */
	private $selserData;

	/**
	 * SelectiveSerializer constructor.
	 * @param array $options
	 */
	public function __construct( $options ) {
		$env = $options['env'];
		$this->env = $env;
		$this->wts = new WikitextSerializer( $options );
		$this->selserData = $options['selserData'];

		// Debug options
		$this->trace = $env->hasTraceFlag( 'selser' );

		// Performance Timing option
		$this->metrics = $env->getSiteConfig()->metrics();
	}

	/**
	 * Selectively serialize an HTML DOM document.
	 *
	 * WARNING: You probably want to use FromHTML.serializeDOM instead.
	 * @param DOMElement $body
	 * @return string
	 */
	public function serializeDOM( DOMElement $body ): string {
		Assert::invariant( DOMUtils::isBody( $body ), 'Expected a body node.' );
		// See WSP.serializeDOM
		Assert::invariant( $this->env->getPageConfig()->editedDoc, 'Should be set.' );

		$serializeStart = null;
		$domDiffStart = null;
		$r = null;

		$timing = Timing::start( $this->metrics );

		if (
			( !$this->env->getOrigDOM() && !$this->env->getDOMDiff() ) ||
			$this->selserData->oldText === null
		) {
			// If there's no old source, fall back to non-selective serialization.
			$r = $this->wts->serializeDOM( $body, false );
			$timing->end( 'html2wt.full.serialize' );
		} else {
			// Use provided diff-marked DOM (used during testing)
			// or generate one (used in production)
			if ( $this->env->getDOMDiff() ) {
				$diff = $this->env->getDOMDiff();
				$body = $diff->dom;
			} else {
				$domDiffTiming = Timing::start( $this->metrics );

				// Strip <section> and mw:FallbackId <span> tags, if present.
				// This ensures that we can accept HTML from CX / VE
				// and other clients that might have stripped them.
				ContentUtils::stripSectionTagsAndFallbackIds( $body );
				ContentUtils::stripSectionTagsAndFallbackIds( $this->env->getOrigDOM() );

				$diff = ( new DOMDiff( $this->env ) )->diff( $this->env->getOrigDOM(), $body );

				$domDiffTiming->end( 'html2wt.selser.domDiff' );
			}

			if ( $diff['isEmpty'] ) {
				// Nothing was modified, just re-use the original source
				$r = $this->selserData->oldText;
			} else {
				if ( $this->trace || $this->env->hasDumpFlag( 'dom:post-dom-diff' ) ) {
					$options = [ 'storeDiffMark' => true, 'env' => $this->env ];
					ContentUtils::dumpDOM( $body, 'DOM after running DOMDiff', $options );
				}

				// Call the WikitextSerializer to do our bidding
				$r = $this->wts->serializeDOM( $body, true );
			}
			$timing->end( 'html2wt.selser.serialize' );
		}
		return $r;
	}
}
