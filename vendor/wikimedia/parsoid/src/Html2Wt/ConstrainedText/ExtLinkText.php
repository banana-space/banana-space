<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Html2Wt\ConstrainedText;

use DOMElement;
use stdClass;
use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Config\SiteConfig;

/**
 * An external link, like `[http://example.com]`.
 */
class ExtLinkText extends ConstrainedText {
	/**
	 * @param string $text
	 * @param DOMElement $node
	 * @param SiteConfig $siteConfig
	 * @param string $type
	 *   The type of the link, as described by the `rel` attribute.
	 */
	public function __construct(
		string $text, DOMElement $node,
		SiteConfig $siteConfig, string $type
	) {
		parent::__construct( [
				'text' => $text,
				'node' => $node
			]
		);
	}

	/**
	 * @param string $text
	 * @param DOMElement $node
	 * @param stdClass $dataParsoid
	 * @param Env $env
	 * @param array $opts
	 * @return ?ExtLinkText
	 */
	protected static function fromSelSerImpl(
		string $text, DOMElement $node, stdClass $dataParsoid,
		Env $env, array $opts
	): ?ExtLinkText {
		$type = $node->getAttribute( 'rel' ) ?? '';
		$stx = $dataParsoid->stx ?? '';
		if ( $type === 'mw:ExtLink' && !preg_match( '/^(simple|piped)$/D', $stx ) ) {
			return new ExtLinkText( $text, $node, $env->getSiteConfig(), $type );
		}
		return null;
	}
}
