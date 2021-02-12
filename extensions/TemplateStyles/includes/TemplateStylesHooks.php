<?php

/**
 * @file
 * @license GPL-2.0-or-later
 */

use MediaWiki\Revision\SlotRecord;
use Wikimedia\CSS\Grammar\CheckedMatcher;
use Wikimedia\CSS\Grammar\GrammarMatch;
use Wikimedia\CSS\Grammar\MatcherFactory;
use Wikimedia\CSS\Objects\ComponentValueList;
use Wikimedia\CSS\Objects\Token;
use Wikimedia\CSS\Parser\Parser as CSSParser;
use Wikimedia\CSS\Sanitizer\FontFeatureValuesAtRuleSanitizer;
use Wikimedia\CSS\Sanitizer\KeyframesAtRuleSanitizer;
use Wikimedia\CSS\Sanitizer\MediaAtRuleSanitizer;
use Wikimedia\CSS\Sanitizer\NamespaceAtRuleSanitizer;
use Wikimedia\CSS\Sanitizer\PageAtRuleSanitizer;
use Wikimedia\CSS\Sanitizer\Sanitizer;
use Wikimedia\CSS\Sanitizer\StylePropertySanitizer;
use Wikimedia\CSS\Sanitizer\StyleRuleSanitizer;
use Wikimedia\CSS\Sanitizer\StylesheetSanitizer;
use Wikimedia\CSS\Sanitizer\SupportsAtRuleSanitizer;

/**
 * TemplateStyles extension hooks
 */
class TemplateStylesHooks {

	/** @var Config|null */
	private static $config = null;

	/** @var MatcherFactory|null */
	private static $matcherFactory = null;

	/** @var Sanitizer[] */
	private static $sanitizers = [];

	/** @var (false|Token[])[] */
	private static $wrappers = [];

	/**
	 * Get our Config
	 * @return Config
	 * @codeCoverageIgnore
	 */
	public static function getConfig() {
		if ( !self::$config ) {
			self::$config = \MediaWiki\MediaWikiServices::getInstance()
				->getConfigFactory()
				->makeConfig( 'templatestyles' );
		}
		return self::$config;
	}

	/**
	 * Get our MatcherFactory
	 * @return MatcherFactory
	 * @codeCoverageIgnore
	 */
	private static function getMatcherFactory() {
		if ( !self::$matcherFactory ) {
			$config = self::getConfig();
			self::$matcherFactory = new TemplateStylesMatcherFactory(
				$config->get( 'TemplateStylesAllowedUrls' )
			);
		}
		return self::$matcherFactory;
	}

	/**
	 * Validate an extra wrapper-selector
	 * @param string $wrapper
	 * @return Token[]|false Token representation of the selector, or false on failure
	 */
	private static function validateExtraWrapper( $wrapper ) {
		if ( !isset( self::$wrappers[$wrapper] ) ) {
			$cssParser = CSSParser::newFromString( $wrapper );
			$components = $cssParser->parseComponentValueList();
			if ( $cssParser->getParseErrors() ) {
				$match = false;
			} else {
				$match = self::getMatcherFactory()->cssSimpleSelectorSeq()
					->matchAgainst( $components, [ 'mark-significance' => true ] );
			}
			self::$wrappers[$wrapper] = $match ? $components->toTokenArray() : false;
		}
		return self::$wrappers[$wrapper];
	}

	/**
	 * Get our Sanitizer
	 * @param string $class Class to limit selectors to
	 * @param string|null $extraWrapper Extra selector to limit selectors to
	 * @return Sanitizer
	 */
	public static function getSanitizer( $class, $extraWrapper = null ) {
		$key = $extraWrapper !== null ? "$class $extraWrapper" : $class;
		if ( !isset( self::$sanitizers[$key] ) ) {
			$config = self::getConfig();
			$matcherFactory = self::getMatcherFactory();

			$propertySanitizer = new StylePropertySanitizer( $matcherFactory );
			$propertySanitizer->setKnownProperties( array_diff_key(
				$propertySanitizer->getKnownProperties(),
				array_flip( $config->get( 'TemplateStylesPropertyBlacklist' ) )
			) );
			Hooks::run( 'TemplateStylesPropertySanitizer', [ &$propertySanitizer, $matcherFactory ] );

			$htmlOrBodySimpleSelectorSeqMatcher = new CheckedMatcher(
				$matcherFactory->cssSimpleSelectorSeq(),
				function ( ComponentValueList $values, GrammarMatch $match, array $options ) {
					foreach ( $match->getCapturedMatches() as $m ) {
						if ( $m->getName() !== 'element' ) {
							continue;
						}
						$str = (string)$m;
						return $str === 'html' || $str === 'body';
					}
					return false;
				}
			);

			$prependSelectors = [
				new Token( Token::T_DELIM, '.' ),
				new Token( Token::T_IDENT, $class ),
			];
			if ( $extraWrapper !== null ) {
				$extraTokens = self::validateExtraWrapper( $extraWrapper );
				if ( !$extraTokens ) {
					throw new InvalidArgumentException( "Invalid value for \$extraWrapper: $extraWrapper" );
				}
				$prependSelectors = array_merge(
					$prependSelectors,
					[ new Token( Token::T_WHITESPACE, [ 'significant' => true ] ) ],
					$extraTokens
				);
			}

			$atRuleBlacklist = array_flip( $config->get( 'TemplateStylesAtRuleBlacklist' ) );
			$ruleSanitizers = [
				'styles' => new StyleRuleSanitizer(
					$matcherFactory->cssSelectorList(),
					$propertySanitizer,
					[
						'prependSelectors' => $prependSelectors,
						'hoistableComponentMatcher' => $htmlOrBodySimpleSelectorSeqMatcher,
					]
				),
				'@font-face' => new TemplateStylesFontFaceAtRuleSanitizer( $matcherFactory ),
				'@font-feature-values' => new FontFeatureValuesAtRuleSanitizer( $matcherFactory ),
				'@keyframes' => new KeyframesAtRuleSanitizer( $matcherFactory, $propertySanitizer ),
				'@page' => new PageAtRuleSanitizer( $matcherFactory, $propertySanitizer ),
				'@media' => new MediaAtRuleSanitizer( $matcherFactory->cssMediaQueryList() ),
				'@supports' => new SupportsAtRuleSanitizer( $matcherFactory, [
					'declarationSanitizer' => $propertySanitizer,
				] ),
			];
			$ruleSanitizers = array_diff_key( $ruleSanitizers, $atRuleBlacklist );
			if ( isset( $ruleSanitizers['@media'] ) ) { // In case @media was blacklisted
				$ruleSanitizers['@media']->setRuleSanitizers( $ruleSanitizers );
			}
			if ( isset( $ruleSanitizers['@supports'] ) ) { // In case @supports was blacklisted
				$ruleSanitizers['@supports']->setRuleSanitizers( $ruleSanitizers );
			}

			$allRuleSanitizers = $ruleSanitizers + [
				// Omit @import, it's not secure. Maybe someday we'll make an "@-mw-import" or something.
				'@namespace' => new NamespaceAtRuleSanitizer( $matcherFactory ),
			];
			$allRuleSanitizers = array_diff_key( $allRuleSanitizers, $atRuleBlacklist );
			$sanitizer = new StylesheetSanitizer( $allRuleSanitizers );
			Hooks::run( 'TemplateStylesStylesheetSanitizer',
				[ &$sanitizer, $propertySanitizer, $matcherFactory ]
			);
			self::$sanitizers[$key] = $sanitizer;
		}
		return self::$sanitizers[$key];
	}

	/**
	 * Update $wgTextModelsToParse
	 */
	public static function onRegistration() {
		// This gets called before ConfigFactory is set up, so I guess we need
		// to use globals.
		global $wgTextModelsToParse, $wgTemplateStylesAutoParseContent;

		if ( in_array( CONTENT_MODEL_CSS, $wgTextModelsToParse, true ) &&
			$wgTemplateStylesAutoParseContent
		) {
			$wgTextModelsToParse[] = 'sanitized-css';
		}
	}

	/**
	 * Add `<templatestyles>` to the parser.
	 * @param Parser $parser Parser object being cleared
	 * @return bool
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setHook( 'templatestyles', 'TemplateStylesHooks::handleTag' );
		/** @phan-suppress-next-line PhanUndeclaredProperty */
		$parser->extTemplateStylesCache = new MapCacheLRU( 100 ); // 100 is arbitrary
		return true;
	}

	/**
	 * Set the default content model to 'sanitized-css' when appropriate.
	 * @param Title $title the Title in question
	 * @param string &$model The model name
	 * @return bool
	 */
	public static function onContentHandlerDefaultModelFor( $title, &$model ) {
		// Allow overwriting attributes with config settings.
		// Attributes can not use namespaces as keys, as processing them does not preserve
		// integer keys.
		$enabledNamespaces = self::getConfig()->get( 'TemplateStylesNamespaces' ) +
			array_fill_keys(
				ExtensionRegistry::getInstance()->getAttribute( 'TemplateStylesNamespaces' ),
				true
			);

		if ( !empty( $enabledNamespaces[$title->getNamespace()] ) &&
			$title->isSubpage() && substr( $title->getText(), -4 ) === '.css'
		) {
			$model = 'sanitized-css';
			return false;
		}
		return true;
	}

	/**
	 * Edit our CSS content model like core's CSS
	 * @param Title $title Title being edited
	 * @param string &$lang CodeEditor language to use
	 * @param string $model Content model
	 * @param string $format Content format
	 * @return bool
	 */
	public static function onCodeEditorGetPageLanguage( $title, &$lang, $model, $format ) {
		if ( $model === 'sanitized-css' && self::getConfig()->get( 'TemplateStylesUseCodeEditor' ) ) {
			$lang = 'css';
			return false;
		}
		return true;
	}

	/**
	 * Clear our cache when the parser is reset
	 * @param Parser $parser
	 */
	public static function onParserClearState( Parser $parser ) {
		/** @phan-suppress-next-line PhanUndeclaredProperty */
		$parser->extTemplateStylesCache->clear();
	}

	/**
	 * Parser hook for `<templatestyles>`
	 * @param string $text Contents of the tag (ignored).
	 * @param array $params Tag attributes
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string HTML
	 * @suppress SecurityCheck-XSS
	 */
	public static function handleTag( $text, $params, $parser, $frame ) {
		if ( self::getConfig()->get( 'TemplateStylesDisable' ) ) {
			return '';
		}

		if ( !isset( $params['src'] ) || trim( $params['src'] ) === '' ) {
			return self::formatTagError( $parser, [ 'templatestyles-missing-src' ] );
		}

		$extraWrapper = null;
		if ( isset( $params['wrapper'] ) && trim( $params['wrapper'] ) !== '' ) {
			$extraWrapper = trim( $params['wrapper'] );
			if ( !self::validateExtraWrapper( $extraWrapper ) ) {
				return self::formatTagError( $parser, [ 'templatestyles-invalid-wrapper' ] );
			}
		}

		// Default to the Template namespace because that's the most likely
		// situation. We can't allow for subpage syntax like src="/styles.css"
		// or the like, though, because stuff like substing and Parsoid would
		// wind up wanting to make that relative to the wrong page.
		$title = Title::newFromText( $params['src'], NS_TEMPLATE );
		if ( !$title ) {
			return self::formatTagError( $parser, [ 'templatestyles-invalid-src' ] );
		}

		$revRecord = $parser->fetchCurrentRevisionRecordOfTitle( $title );

		// It's not really a "template", but it has the same implications
		// for needing reparse when the stylesheet is edited.
		$parser->getOutput()->addTemplate(
			$title,
			$title->getArticleId(),
			$revRecord ? $revRecord->getId() : null
		);

		$content = $revRecord ? $revRecord->getContent( SlotRecord::MAIN ) : null;
		if ( !$content ) {
			$titleText = $title->getPrefixedText();
			return self::formatTagError( $parser, [
				'templatestyles-bad-src-missing',
				$titleText,
				wfEscapeWikiText( $titleText )
			] );
		}
		if ( !$content instanceof TemplateStylesContent ) {
			$titleText = $title->getPrefixedText();
			return self::formatTagError( $parser, [
				'templatestyles-bad-src',
				$titleText,
				wfEscapeWikiText( $titleText ),
				ContentHandler::getLocalizedName( $content->getModel() )
			] );
		}

		// If the revision actually has an ID, cache based on that.
		// Otherwise, cache by hash.
		if ( $revRecord->getId() ) {
			$cacheKey = 'r' . $revRecord->getId();
		} else {
			$cacheKey = sha1( $content->getNativeData() );
		}

		// Include any non-default wrapper class in the cache key too
		$wrapClass = $parser->getOptions()->getWrapOutputClass();
		if ( $wrapClass === false ) { // deprecated
			$wrapClass = 'mw-parser-output';
		}
		if ( $wrapClass !== 'mw-parser-output' || $extraWrapper !== null ) {
			$cacheKey .= '/' . $wrapClass;
			if ( $extraWrapper !== null ) {
				$cacheKey .= '/' . $extraWrapper;
			}
		}

		// Already cached?
		/** @phan-suppress-next-line PhanUndeclaredProperty */
		if ( $parser->extTemplateStylesCache->has( $cacheKey ) ) {
			/** @phan-suppress-next-line PhanUndeclaredProperty */
			return $parser->extTemplateStylesCache->get( $cacheKey );
		}

		$targetDir = $parser->getTargetLanguage()->getDir();
		$contentDir = $parser->getContentLanguage()->getDir();
		$status = $content->sanitize( [
			'flip' => $targetDir !== $contentDir,
			'minify' => !ResourceLoader::inDebugMode(),
			'class' => $wrapClass,
			'extraWrapper' => $extraWrapper,
		] );
		$style = $status->isOk() ? $status->getValue() : '/* Fatal error, no CSS will be output */';

		// Prepend errors. This should normally never happen, but might if an
		// update or configuration change causes something that was formerly
		// valid to become invalid or something like that.
		if ( !$status->isGood() ) {
			$comment = wfMessage(
				'templatestyles-errorcomment',
				$title->getPrefixedText(),
				$revRecord->getId(),
				$status->getWikiText( false, 'rawmessage' )
			)->text();
			$comment = trim( strtr( $comment, [
				// Use some lookalike unicode characters to avoid things that might
				// otherwise confuse browsers.
				'*' => '•', '-' => '‐', '<' => '⧼', '>' => '⧽',
			] ) );
			$style = "/*\n$comment\n*/\n$style";
		}

		// Hide the CSS from Parser::doBlockLevels
		$marker = Parser::MARKER_PREFIX . '-templatestyles-' .
			sprintf( '%08X', $parser->mMarkerIndex++ ) . Parser::MARKER_SUFFIX;
		$parser->mStripState->addNoWiki( $marker, $style );

		// Return the inline <style>, which the Parser will wrap in a 'general'
		// strip marker.
		$ret = Html::inlineStyle( $marker, 'all', [
			'data-mw-deduplicate' => "TemplateStyles:$cacheKey",
		] );
		/** @phan-suppress-next-line PhanUndeclaredProperty */
		$parser->extTemplateStylesCache->set( $cacheKey, $ret );
		return $ret;
	}

	/**
	 * Format an error in the `<templatestyles>` tag
	 * @param Parser $parser
	 * @param array $msg Arguments to wfMessage()
	 * @return string HTML
	 */
	private static function formatTagError( Parser $parser, array $msg ) {
		$parser->addTrackingCategory( 'templatestyles-page-error-category' );
		return '<strong class="error">' .
			call_user_func_array( 'wfMessage', $msg )->inContentLanguage()->parse() .
			'</strong>';
	}

}
