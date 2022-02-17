<?php

namespace CirrusSearch;

use CirrusSearch\Profile\SearchProfileServiceFactoryFactory;
use GlobalVarConfig;
use InvalidArgumentException;
use MultiConfig;

/**
 * SearchConfig implemenation backed by a simple \HashConfig
 */
class HashSearchConfig extends SearchConfig {
	const FLAG_INHERIT = 'inherit';
	const FLAG_LOAD_CONT_LANG = 'load-cont-lang';

	/** @var bool */
	private $localWiki = false;

	/**
	 * @param array $settings config vars
	 * @param string[] $flags customization flags:
	 * - inherit: config vars not part the settings provided are fetched from GlobalVarConfig
	 * - load-cont-lang: eagerly load ContLang from \Language::factory( 'LanguageCode' )
	 * @param \Config|null $inherited (only useful when the inherit flag is set)
	 * @param SearchProfileServiceFactoryFactory|null $searchProfileServiceFactoryFactory
	 * @throws \MWException
	 */
	public function __construct(
		array $settings,
		array $flags = [],
		\Config $inherited = null,
		SearchProfileServiceFactoryFactory $searchProfileServiceFactoryFactory = null
	) {
		parent::__construct( $searchProfileServiceFactoryFactory );
		$config = new \HashConfig( $settings );
		$extra = array_diff( $flags, [ self::FLAG_LOAD_CONT_LANG, self::FLAG_INHERIT ] );
		if ( $extra ) {
			throw new InvalidArgumentException( "Unknown config flags: " . implode( ',', $extra ) );
		}
		if ( in_array( self::FLAG_LOAD_CONT_LANG, $flags ) && !$config->has( 'ContLang' ) && $config->has( 'LanguageCode' ) ) {
			$config->set( 'ContLang', \Language::factory( $config->get( 'LanguageCode' ) ) );
		}

		if ( in_array( self::FLAG_INHERIT, $flags ) ) {
			$config = new MultiConfig( [ $config, $inherited ?? new GlobalVarConfig ] );
			$this->localWiki = !isset( $settings['_wikiID' ] );
		}
		$this->setSource( $config );
	}

	/**
	 * Allow overriding Wiki ID
	 * @return mixed|string
	 */
	public function getWikiId() {
		if ( $this->has( '_wikiID' ) ) {
			return $this->get( '_wikiID' );
		}
		return parent::getWikiId();
	}

	public function getHostWikiConfig(): SearchConfig {
		if ( $this->localWiki ) {
			return $this;
		}
		return parent::getHostWikiConfig();
	}

	public function isLocalWiki() {
		return $this->localWiki;
	}
}
