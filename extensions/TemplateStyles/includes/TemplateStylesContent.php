<?php

/**
 * @file
 * @license GPL-2.0-or-later
 */

use Wikimedia\CSS\Parser\Parser as CSSParser;
use Wikimedia\CSS\Util as CSSUtil;

/**
 * Content object for sanitized CSS.
 */
class TemplateStylesContent extends TextContent {

	public function __construct( $text, $modelId = 'sanitized-css' ) {
		parent::__construct( $text, $modelId );
	}

	/**
	 * Handle errors from the CSS parser and/or sanitizer
	 * @param StatusValue $status Object to add errors to
	 * @param array[] $errors Error array
	 * @param string $severity Whether to consider errors as 'warning' or 'fatal'
	 */
	protected static function processErrors( StatusValue $status, array $errors, $severity ) {
		if ( $severity !== 'warning' && $severity !== 'fatal' ) {
			// @codeCoverageIgnoreStart
			throw new \InvalidArgumentException( 'Invalid $severity' );
			// @codeCoverageIgnoreEnd
		}
		foreach ( $errors as $error ) {
			$error[0] = 'templatestyles-error-' . $error[0];
			call_user_func_array( [ $status, $severity ], $error );
		}
	}

	/**
	 * Sanitize the content
	 * @param array $options Options are:
	 *  - class: (string) Class to prefix selectors with
	 *  - extraWrapper: (string) Extra simple selector to prefix selectors with
	 *  - flip: (bool) Have CSSJanus flip the stylesheet.
	 *  - minify: (bool) Whether to minify. Default true.
	 *  - novalue: (bool) Don't bother returning the actual stylesheet, just
	 *    fill the Status with warnings.
	 *  - severity: (string) Whether to consider errors as 'warning' or 'fatal'
	 * @return Status
	 */
	public function sanitize( array $options = [] ) {
		$options += [
			'class' => false,
			'extraWrapper' => null,
			'flip' => false,
			'minify' => true,
			'novalue' => false,
			'severity' => 'warning',
		];

		$status = Status::newGood();

		$style = $this->getNativeData();
		$maxSize = TemplateStylesHooks::getConfig()->get( 'TemplateStylesMaxStylesheetSize' );
		if ( $maxSize !== null && strlen( $style ) > $maxSize ) {
			$status->fatal(
				// Status::getWikiText() chokes on the Message::sizeParam if we
				// don't wrap it in a Message ourself.
				wfMessage( 'templatestyles-size-exceeded', $maxSize, Message::sizeParam( $maxSize ) )
			);
			return $status;
		}

		if ( $options['flip'] ) {
			$style = CSSJanus::transform( $style, true, false );
		}

		// Parse it, and collect any errors
		$cssParser = CSSParser::newFromString( $style );
		$stylesheet = $cssParser->parseStylesheet();
		self::processErrors( $status, $cssParser->getParseErrors(), $options['severity'] );

		// Sanitize it, and collect any errors
		$sanitizer = TemplateStylesHooks::getSanitizer(
			$options['class'] ?: 'mw-parser-output', $options['extraWrapper']
		);
		$sanitizer->clearSanitizationErrors(); // Just in case
		$stylesheet = $sanitizer->sanitize( $stylesheet );
		self::processErrors( $status, $sanitizer->getSanitizationErrors(), $options['severity'] );
		$sanitizer->clearSanitizationErrors();

		// Stringify it while minifying
		$value = CSSUtil::stringify( $stylesheet, [ 'minify' => $options['minify'] ] );

		// Sanity check, don't allow "</style" if one somehow sneaks through the sanitizer
		if ( preg_match( '!</style!i', $value ) ) {
			$value = '';
			$status->fatal( 'templatestyles-end-tag-injection' );
		}

		if ( !$options['novalue'] ) {
			$status->value = $value;

			// Sanity check, don't allow raw U+007F if one somehow sneaks through the sanitizer
			$status->value = strtr( $status->value, [ "\x7f" => '�' ] );
		}

		return $status;
	}

	public function prepareSave( WikiPage $page, $flags, $parentRevId, User $user ) {
		return $this->sanitize( [ 'novalue' => true, 'severity' => 'fatal' ] );
	}

	/**
	 * @return string CSS wrapped in a <pre> tag.
	 */
	protected function getHtml() {
		$html = "";
		$html .= "<pre class=\"mw-code mw-css\" dir=\"ltr\">\n";
		$html .= htmlspecialchars( $this->getNativeData(), ENT_NOQUOTES );
		$html .= "\n</pre>\n";

		return $html;
	}

	public function getParserOutput( Title $title, $revId = null,
		ParserOptions $options = null, $generateHtml = true
	) {
		if ( $options === null ) {
			$options = ParserOptions::newCanonical( 'canonical' );
		}

		// Inject our warnings into the resulting ParserOutput
		$po = parent::getParserOutput( $title, $revId, $options, $generateHtml );
		$status = $this->sanitize( [ 'novalue' => true, 'class' => $options->getWrapOutputClass() ] );
		if ( $status->getErrors() ) {
			foreach ( $status->getErrors() as $error ) {
				$po->addWarning(
					Message::newFromSpecifier( array_merge( [ $error['message'] ], $error['params'] ) )->parse()
				);
			}
			$po->addTrackingCategory( 'templatestyles-stylesheet-error-category', $title );
		}
		return $po;
	}
}
