<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Ext\Poem;

use DOMDocument;
use Wikimedia\Parsoid\Ext\ExtensionModule;
use Wikimedia\Parsoid\Ext\ExtensionTagHandler;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;
use Wikimedia\Parsoid\Utils\DOMCompat;

class Poem extends ExtensionTagHandler implements ExtensionModule {

	/** @inheritDoc */
	public function getConfig(): array {
		return [
			'name' => 'Poem',
			'domProcessors' => [
				PoemProcessor::class,
			],
			'tags' => [
				[
					'name' => 'poem',
					'handler' => self::class,
				]
			]
		];
	}

	/**
	 * @param ParsoidExtensionAPI|null $extApi
	 */
	public function __construct( ParsoidExtensionAPI $extApi = null ) {
		/* @phan-suppress-previous-line PhanEmptyPublicMethod */
		/* The dom post processor doesn't need to use $extApi, so ignore it */
	}

	/** @inheritDoc */
	public function sourceToDom(
		ParsoidExtensionAPI $extApi, string $content, array $extArgs
	): DOMDocument {
		/*
		 * Transform wikitext found in <poem>...</poem>
		 * 1. Strip leading & trailing newlines
		 * 2. Suppress indent-pre by replacing leading space with &nbsp;
		 * 3. Replace colons with <span class='...' style='...'>...</span>
		 * 4. Add <br/> for newlines except (a) in nowikis (b) after ----
		 */

		if ( strlen( $content ) > 0 ) {
			// 1. above
			$content = preg_replace( '/^\n/', '', $content, 1 );
			$content = preg_replace( '/\n$/D', '', $content, 1 );

			// 2. above
			$content = preg_replace( '/^ /m', '&nbsp;', $content );

			// 3. above
			$contentArray = explode( "\n", $content );
			$contentMap = array_map( function ( $line ) use ( $extApi ) {
				$i = 0;
				$lineLength = strlen( $line );
				while ( $i < $lineLength && $line[$i] === ':' ) {
					$i++;
				}
				if ( $i > 0 && $i < $lineLength ) {
					$doc = $extApi->htmlToDom( '' ); // Empty doc
					$span = $doc->createElement( 'span' );
					$span->setAttribute( 'class', 'mw-poem-indented' );
					$span->setAttribute( 'style', 'display: inline-block; margin-inline-start: ' . $i . 'em;' );
					$span->appendChild( $doc->createTextNode( ltrim( $line, ':' ) ) );
					return DOMCompat::getOuterHTML( $span );
				} else {
					return $line;
				}
			}, $contentArray );
			$content = implode( "\n", $contentMap ); // use faster? preg_replace

			// 4. above
			// Split on <nowiki>..</nowiki> fragments.
			// Process newlines inside nowikis in a post-processing pass.
			// If <br/>s are added here, Parsoid will escape them to plaintext.
			$splitContent = preg_split( '/(<nowiki>[\s\S]*?<\/nowiki>)/', $content,
				-1, PREG_SPLIT_DELIM_CAPTURE );
			$content = implode( '',
				array_map( function ( $p, $i ) {
					if ( $i % 2 === 1 ) {
						return $p;
					}

					// This is a hack that exploits the fact that </poem>
					// cannot show up in the extension's content.
					return preg_replace( '/^(-+)<\/poem>/m', "\$1\n",
						preg_replace( '/\n/m', "<br/>\n",
							preg_replace( '/(^----+)\n/m', '$1</poem>', $p ) ) );
				},
				$splitContent,
				range( 0, count( $splitContent ) - 1 ) )
			);

		}

		// Add the 'poem' class to the 'class' attribute, or if not found, add it
		$value = $extApi->findAndUpdateArg( $extArgs, 'class', function ( string $value ) {
			return strlen( $value ) ? "poem {$value}" : 'poem';
		} );

		if ( !$value ) {
			$extApi->addNewArg( $extArgs, 'class', 'poem' );
		}

		return $extApi->extTagToDOM( $extArgs, '', $content, [
				'wrapperTag' => 'div',
				'parseOpts' => [ 'extTag' => 'poem' ],
				// Create new frame, because $content doesn't literally appear in
				// the parent frame's sourceText (our copy has been munged)
				'processInNewFrame' => true,
				// We've shifted the content around quite a bit when we preprocessed
				// it.  In the future if we wanted to enable selser inside the <poem>
				// body we should create a proper offset map and then apply it to the
				// result after the parse, like we do in the Gallery extension.
				// But for now, since we don't selser the contents, just strip the
				// DSR info so it doesn't cause problems/confusion with unicode
				// offset conversion (and so it's clear you can't selser what we're
				// currently emitting).
				'clearDSROffsets' => true
			]
		);
	}
}
