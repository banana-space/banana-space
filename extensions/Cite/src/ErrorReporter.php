<?php

namespace Cite;

use Html;
use Language;
use Message;
use Parser;
use Sanitizer;

/**
 * @license GPL-2.0-or-later
 */
class ErrorReporter {

	/**
	 * @var ReferenceMessageLocalizer
	 */
	private $messageLocalizer;

	/**
	 * @var Language
	 */
	private $cachedInterfaceLanguage = null;

	/**
	 * @param ReferenceMessageLocalizer $messageLocalizer
	 */
	public function __construct( ReferenceMessageLocalizer $messageLocalizer ) {
		$this->messageLocalizer = $messageLocalizer;
	}

	/**
	 * @param Parser $parser
	 * @param string $key Message name of the error or warning
	 * @param mixed ...$params
	 *
	 * @return string Half-parsed wikitext with extension's tags already being expanded
	 */
	public function halfParsed( Parser $parser, string $key, ...$params ) : string {
		$msg = $this->msg( $parser, $key, ...$params );
		$wikitext = $parser->recursiveTagParse( $msg->plain() );
		return $this->wrapInHtmlContainer( $wikitext, $key, $msg->getLanguage() );
	}

	/**
	 * @param Parser $parser
	 * @param string $key Message name of the error or warning
	 * @param mixed ...$params
	 *
	 * @return string Plain, unparsed wikitext
	 * @return-taint tainted
	 */
	public function plain( Parser $parser, string $key, ...$params ) : string {
		$msg = $this->msg( $parser, $key, ...$params );
		$wikitext = $msg->plain();
		return $this->wrapInHtmlContainer( $wikitext, $key, $msg->getLanguage() );
	}

	/**
	 * @param Parser $parser
	 * @param string $key
	 * @param mixed ...$params
	 *
	 * @return Message
	 */
	private function msg( Parser $parser, string $key, ...$params ) : Message {
		$language = $this->getInterfaceLanguageAndSplitCache( $parser );
		$msg = $this->messageLocalizer->msg( $key, ...$params )->inLanguage( $language );

		[ $type, ] = $this->parseTypeAndIdFromMessageKey( $msg->getKey() );

		if ( $type === 'error' ) {
			// Take care; this is a sideeffect that might not belong to this class.
			$parser->addTrackingCategory( 'cite-tracking-category-cite-error' );
		}

		// Messages: cite_error, cite_warning
		return $this->messageLocalizer->msg( "cite_$type", $msg->plain() )->inLanguage( $language );
	}

	/**
	 * Note the startling side effect of splitting ParserCache by user interface language!
	 *
	 * @param Parser $parser
	 *
	 * @return Language
	 */
	private function getInterfaceLanguageAndSplitCache( Parser $parser ) : Language {
		if ( !$this->cachedInterfaceLanguage ) {
			$this->cachedInterfaceLanguage = $parser->getOptions()->getUserLangObj();
		}
		return $this->cachedInterfaceLanguage;
	}

	/**
	 * @param string $wikitext
	 * @param string $key
	 * @param Language $language
	 *
	 * @return string
	 */
	private function wrapInHtmlContainer(
		string $wikitext,
		string $key,
		Language $language
	) : string {
		[ $type, $id ] = $this->parseTypeAndIdFromMessageKey( $key );
		$extraClass = $type === 'warning'
			? ' mw-ext-cite-warning-' . Sanitizer::escapeClass( $id )
			: '';

		return Html::rawElement(
			'span',
			[
				'class' => "$type mw-ext-cite-$type" . $extraClass,
				'lang' => $language->getHtmlCode(),
				'dir' => $language->getDir(),
			],
			$wikitext
		);
	}

	/**
	 * @param string $messageKey Expected to be a message key like "cite_error_ref_too_many_keys"
	 *
	 * @return string[]
	 */
	private function parseTypeAndIdFromMessageKey( string $messageKey ) : array {
		return array_slice( explode( '_', $messageKey, 3 ), 1 );
	}

}
