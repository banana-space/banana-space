<?php

/**
 * A bidirectional Language Converter, capable of round-tripping variant
 * conversion.
 *
 * Language conversion is as DOMPostProcessor pass, run over the
 * Parsoid-format HTML output, which may have embedded language converter
 * rules.  We first assign a (guessed) source variant to each DOM node,
 * which will be used when round-tripping the result back to the original
 * source variant.  Then for each applicable text node in the DOM, we
 * first "bracket" the text, splitting it into cleanly round-trippable
 * segments and lossy/unclean segments.  For the lossy segments we add
 * additional metadata to the output to record the original source variant
 * text to allow round-tripping (and variant-aware editing).
 *
 * Note that different wikis have different policies for source variant:
 * in some wikis all articles are authored in one particular variant, by
 * convention.  In others, it's a "first author gets to choose the variant"
 * situation.  In both cases, a constant/per-article "source variant" may
 * be specified via some as-of-yet-unimplemented mechanism; either part of
 * the site configuration, or per-article metadata like pageLanguage.
 * In other wikis (like zhwiki) the text is a random mix of variants; in
 * these cases the "source variant" will be null/unspecified, and we'll
 * dynamically pick the most likely source variant for each subtree.
 *
 * Each individual language has a dynamically-loaded subclass of `Language`,
 * which may also have a `LanguageConverter` subclass to load appropriate
 * `ReplacementMachine`s and do other language-specific customizations.
 */

namespace Wikimedia\Parsoid\Language;

use DOMDocument;
use DOMNode;
use Wikimedia\LangConv\ReplacementMachine;
use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Core\ClientError;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\Timing;

/**
 * Base class for language variant conversion.
 */
class LanguageConverter {

	/** @var Language */
	private $language;

	/** @var string */
	private $langCode;

	/** @var string[] */
	private $variants;

	/** @var array */
	private $variantFallbacks;

	/** @var ReplacementMachine|null */
	private $machine;

	/**
	 * @param Language $language
	 * @param string $langCode The main language code of this language
	 * @param string[] $variants The supported variants of this language
	 * @param array|null $variantfallbacks The fallback language of each variant
	 * @param array|null $flags Defining the custom strings that maps to the flags
	 * @param array|null $manualLevel Limit for supported variants
	 */
	public function __construct(
		Language $language,
		$langCode,
		array $variants,
		array $variantfallbacks = null,
		array $flags = null,
		array $manualLevel = null
	) {
		$this->language = $language;
		$this->langCode = $langCode;
		$this->variants = $variants; // XXX subtract disabled variants
		$this->variantFallbacks = $variantfallbacks;
		// this.mVariantNames = Language.// XXX

		// Eagerly load conversion tables.
		// XXX we could defer loading in the future, or cache more
		// aggressively
		$this->loadDefaultTables();
	}

	public function loadDefaultTables() {
	}

	/**
	 * Return the {@link ReplacementMachine} powering this conversion.
	 * @return ReplacementMachine|null
	 */
	public function getMachine(): ?ReplacementMachine {
		return $this->machine;
	}

	/**
	 * @param ReplacementMachine $machine
	 */
	public function setMachine( ReplacementMachine $machine ): void {
		$this->machine = $machine;
	}

	/**
	 * Try to return a classname from a given code.
	 * @param string $code
	 * @param bool $fallback Whether we're going through language fallback
	 * @return class-string Name of the language class (if one were to exist)
	 */
	public static function classFromCode( string $code, bool $fallback ): string {
		if ( $fallback && $code === 'en' ) {
			return '\Wikimedia\Parsoid\Language\Language';
		} else {
			$code = preg_replace_callback( '/^\w/', function ( $matches ) {
				return strtoupper( $matches[0] );
			}, $code, 1 );
			$code = preg_replace( '/-/', '_', $code );
			$code = preg_replace( '#/|^\.+#', '', $code ); // avoid path attacks
			return "\Wikimedia\Parsoid\Language\Language{$code}";
		}
	}

	/**
	 * @param Env $env
	 * @param string $lang
	 * @param bool $fallback
	 * @return Language
	 */
	public static function loadLanguage( Env $env, string $lang, bool $fallback = false ): Language {
		try {
			if ( Language::isValidCode( $lang ) ) {
				$languageClass = self::classFromCode( $lang, $fallback );
				return new $languageClass();
			}
		} catch ( \Error $e ) {
			/* fall through */
		}
		$env->log( 'info', "Couldn't load language: {$lang} fallback={$fallback}" );
		return new Language();
	}

	// phpcs:ignore MediaWiki.Commenting.FunctionComment.MissingDocumentationPublic
	public function findVariantLink( $link, $nt, $ignoreOtherCond ) {
		// XXX unimplemented
		return [ 'nt' => $nt, 'link' => $link ];
	}

	/**
	 * @param string $fromVariant
	 * @param string $text
	 * @param string $toVariant
	 * @suppress PhanEmptyPublicMethod
	 */
	public function translate( $fromVariant, $text, $toVariant ) {
		// XXX unimplemented
	}

	/**
	 * @param string $text
	 * @param string $variant
	 * @return bool
	 */
	public function guessVariant( $text, $variant ) {
		return false;
	}

	/**
	 * Convert the given document into $targetVariant, if:
	 *  1) language converter is enabled on this wiki, and
	 *  2) the targetVariant is specified, and it is a known variant (not a
	 *     base language code)
	 *
	 * The `$sourceVariant`, if provided is expected to be per-wiki or
	 * per-article metadata which specifies a standard "authoring variant"
	 * for this article or wiki.  For example, all articles are authored in
	 * Cyrillic by convention.  It should be left blank if there is no
	 * consistent convention on the wiki (as for zhwiki, for instance).
	 *
	 * @param Env $env
	 * @param DOMDocument $doc The input document.
	 * @param string|null $targetVariant The desired output variant.
	 * @param string|null $sourceVariant The variant used by convention when
	 *   authoring pages, if there is one; otherwise left null.
	 */
	public static function maybeConvert(
		Env $env,
		DOMDocument $doc,
		?string $targetVariant,
		?string $sourceVariant
	): void {
		// language converter must be enabled for the pagelanguage
		if ( !$env->langConverterEnabled() ) {
			return;
		}
		$variants = $env->getSiteConfig()->variants();

		// targetVariant must be specified, and a language-with-variants
		if ( !( $targetVariant && array_key_exists( $targetVariant, $variants ) ) ) {
			return;
		}

		// targetVariant must not be a base language code
		if ( $variants[$targetVariant]['base'] === $targetVariant ) {
			// XXX in the future we probably want to go ahead and expand
			// empty <span>s left by -{...}- constructs, etc.
			return;
		}

		// Record the fact that we've done conversion to targetVariant
		$env->getPageConfig()->setVariant( $targetVariant );

		// But don't actually do the conversion if __NOCONTENTCONVERT__
		if ( DOMCompat::querySelector( $doc, 'meta[property="mw:PageProp/nocontentconvert"]' ) ) {
			return;
		}

		// OK, convert!
		self::baseToVariant( $env, DOMCompat::getBody( $doc ), $targetVariant, $sourceVariant );
	}

	/**
	 * Convert a text in the "base variant" to a specific variant, given by `targetVariant`.  If
	 * `sourceVariant` is given, assume that the input wikitext is in `sourceVariant` to
	 * construct round-trip metadata, instead of using a heuristic to guess the best variant
	 * for each DOM subtree of wikitext.
	 * @param Env $env
	 * @param DOMNode $rootNode The root node of a fragment to convert.
	 * @param string $targetVariant The variant to be used for the output DOM.
	 * @param string|null $sourceVariant An optional variant assumed for the
	 *  input DOM in order to create roundtrip metadata.
	 */
	public static function baseToVariant(
		Env $env,
		DOMNode $rootNode,
		string $targetVariant,
		?string $sourceVariant
	): void {
		$pageLangCode = $env->getPageConfig()->getPageLanguage()
			?: $env->getSiteConfig()->lang()
			?: 'en';
		$guesser = null;

		$metrics = $env->getSiteConfig()->metrics();
		$loadTiming = Timing::start( $metrics );
		$languageClass = self::loadLanguage( $env, $pageLangCode );
		$lang = new $languageClass();
		$langconv = $lang->getConverter();
		// XXX we might want to lazily-load conversion tables here.
		$loadTiming->end( "langconv.{$targetVariant}.init" );
		$loadTiming->end( 'langconv.init' );

		// Check the the target variant is valid (and implemented!)
		$validTarget = $langconv !== null && $langconv->getMachine() !== null
			&& array_key_exists( $targetVariant, $langconv->getMachine()->getCodes() );
		if ( !$validTarget ) {
			// XXX create a warning header? (T197949)
			$env->log( 'info', "Unimplemented variant: {$targetVariant}" );
			return; /* no conversion */
		}
		// Check that the source variant is valid.
		$validSource = $sourceVariant === null ||
			array_key_exists( $sourceVariant, $langconv->getMachine()->getCodes() );
		if ( !$validSource ) {
			throw new ClientError( "Invalid source variant: $sourceVariant for target $targetVariant" );
		}

		$timing = Timing::start( $metrics );
		if ( $metrics ) {
			$metrics->increment( 'langconv.count' );
			$metrics->increment( "langconv.{$targetVariant}.count" );
		}

		// XXX Eventually we'll want to consult some wiki configuration to
		// decide whether a ConstantLanguageGuesser is more appropriate.
		if ( $sourceVariant ) {
			$guesser = new ConstantLanguageGuesser( $sourceVariant );
		} else {
			$guesser = new MachineLanguageGuesser(
				$langconv->getMachine(), $rootNode, $targetVariant
			);
		}

		$ct = new ConversionTraverser( $targetVariant, $guesser, $langconv->getMachine() );
		$ct->traverse( $env, $rootNode, [], true );

		// HACK: to avoid data-parsoid="{}" in the output, set the isNew flag
		// on synthetic spans
		DOMUtils::assertElt( $rootNode );
		foreach ( DOMCompat::querySelectorAll(
			$rootNode, 'span[typeof="mw:LanguageVariant"][data-mw-variant]'
		) as $span ) {
			$dmwv = DOMDataUtils::getJSONAttribute( $span, 'data-mw-variant', null );
			if ( $dmwv->rt ?? false ) {
				$dp = DOMDataUtils::getDataParsoid( $span );
				$dp->tmp->isNew = true;
			}
		}

		$timing->end( 'langconv.total' );
		$timing->end( "langconv.{$targetVariant}.total" );
		$loadTiming->end( 'langconv.totalWithInit' );
	}
}
