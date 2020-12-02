<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Wt2Html\PP\Processors;

use DOMElement;
use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Wt2Html\Wt2HtmlDOMProcessor;

class AddRedLinks implements Wt2HtmlDOMProcessor {
	/**
	 * Add red links to a document.
	 *
	 * @inheritDoc
	 */
	public function run(
		Env $env, DOMElement $root, array $options = [], bool $atTopLevel = false
	): void {
		$wikiLinks = DOMCompat::querySelectorAll( $root, 'a[rel~="mw:WikiLink"]' );

		$titles = array_reduce(
			$wikiLinks,
			function ( array $s, DOMElement $a ): array {
				// Magic links, at least, don't have titles
				if ( $a->hasAttribute( 'title' ) ) {
					$s[] = $a->getAttribute( 'title' );
				}
				return $s;
			},
			[]
		);

		if ( !$titles ) {
			return;
		}

		$titleMap = $env->getDataAccess()->getPageInfo( $env->getPageConfig(), $titles );

		foreach ( $wikiLinks as $a ) {
			if ( !$a->hasAttribute( 'title' ) ) {
				continue;
			}
			$k = $a->getAttribute( 'title' );
			if ( empty( $titleMap[$k] ) ) {
				// Likely a consequence of T237535; can be removed once
				// that is fixed.
				$env->log( 'warn', 'We should have data for the title: ' . $k );
				continue;
			}
			$data = $titleMap[$k];
			$a->removeAttribute( 'class' ); // Clear all
			if ( !empty( $data['missing'] ) && empty( $data['known'] ) ) {
				DOMCompat::getClassList( $a )->add( 'new' );
			}
			if ( !empty( $data['redirect'] ) ) {
				DOMCompat::getClassList( $a )->add( 'mw-redirect' );
			}
			// Jforrester suggests that, "ideally this'd be a registry so that
			// extensions could, er, extend this functionality – this is an
			// API response/CSS class that is provided by the Disambiguation
			// extension." T237538
			if ( !empty( $data['disambiguation'] ) ) {
				DOMCompat::getClassList( $a )->add( 'mw-disambig' );
			}
		}
	}
}
