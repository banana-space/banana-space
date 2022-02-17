<?php

namespace CirrusSearch\Profile;

use BagOStuff;
use CirrusSearch\Util;
use Config;

/**
 * Wrapper to augment the phrase suggester profile settings
 * with customization on-wiki using system messages.
 */
class PhraseSuggesterProfileRepoWrapper implements SearchProfileRepository {

	const MAX_ERRORS_HARD_LIMIT = 2;
	const MAX_TERM_FREQ_HARD_LIMIT = 0.6;
	const PREFIX_LENGTH_HARD_LIMIT = 2;
	const CIRRUSSEARCH_DIDYOUMEAN_SETTINGS = 'cirrussearch-didyoumean-settings';

	/**
	 * @var string[]
	 */
	private static $ALLOWED_MODE = [ 'missing', 'popular', 'always' ];

	/**
	 * @var SearchProfileRepository
	 */
	private $wrapped;

	/**
	 * @var BagOStuff
	 */
	private $bagOStuff;

	/**
	 * @param SearchProfileRepository $wrapped
	 * @param BagOStuff $bagOStuff
	 */
	private function __construct( SearchProfileRepository $wrapped, BagOStuff $bagOStuff ) {
		$this->wrapped = $wrapped;
		$this->bagOStuff = $bagOStuff;
	}

	/**
	 * @param string $type
	 * @param string $name
	 * @param string $phpFile
	 * @param BagOStuff $cache
	 * @return SearchProfileRepository
	 */
	public static function fromFile( $type, $name, $phpFile, BagOStuff $cache ) {
		return new self( ArrayProfileRepository::fromFile( $type, $name, $phpFile ), $cache );
	}

	/**
	 * @param string $type
	 * @param string $name
	 * @param string $configEntry
	 * @param Config $config
	 * @param BagOStuff $cache
	 * @return PhraseSuggesterProfileRepoWrapper
	 */
	public static function fromConfig( $type, $name, $configEntry, Config $config, BagOStuff $cache ) {
		return new self( new ConfigProfileRepository( $type, $name, $configEntry, $config ), $cache );
	}

	/**
	 * The repository type
	 * @return string
	 */
	public function repositoryType() {
		return $this->wrapped->repositoryType();
	}

	/**
	 * The repository name
	 * @return string
	 */
	public function repositoryName() {
		return $this->wrapped->repositoryName();
	}

	/**
	 * Load a profile named $name
	 * @param string $name
	 * @return array|null the profile data or null if not found
	 */
	public function getProfile( $name ) {
		$settings = $this->wrapped->getProfile( $name );
		if ( $settings === null ) {
			return null;
		}
		$lines = $this->bagOStuff->getWithSetCallback(
			$this->bagOStuff->makeKey( self::CIRRUSSEARCH_DIDYOUMEAN_SETTINGS ),
			600,
			function () {
				$source = wfMessage( 'cirrussearch-didyoumean-settings' )->inContentLanguage();
				if ( !$source || $source->isDisabled() ) {
					return [];
				}
				return Util::parseSettingsInMessage( $source->plain() );
			}
		);

		$laplaceAlpha = null;
		$stupidBackoffDiscount = null;
		foreach ( $lines as $line ) {
			$linePieces = explode( ':', $line, 2 );
			if ( count( $linePieces ) !== 2 ) {
				// Skip improperly formatted lines without a key:value
				continue;
			}
			list( $k, $v ) = $linePieces;

			switch ( $k ) {
				case 'max_errors' :
					if ( is_numeric( $v ) && $v >= 1 && $v <= self::MAX_ERRORS_HARD_LIMIT ) {
						$settings['max_errors'] = floatval( $v );
					}
					break;
				case 'confidence' :
					if ( is_numeric( $v ) && $v >= 0 ) {
						$settings['confidence'] = floatval( $v );
					}
					break;
				case 'max_term_freq' :
					if ( is_numeric( $v ) && $v >= 0 && $v <= self::MAX_TERM_FREQ_HARD_LIMIT ) {
						$settings['max_term_freq'] = floatval( $v );
					}
					break;
				case 'min_doc_freq' :
					if ( is_numeric( $v ) && $v >= 0 && $v < 1 ) {
						$settings['min_doc_freq'] = floatval( $v );
					}
					break;
				case 'prefix_length' :
					if ( is_numeric( $v ) && $v >= 0 && $v <= self::PREFIX_LENGTH_HARD_LIMIT ) {
						$settings['prefix_length'] = intval( $v );
					}
					break;
				case 'suggest_mode' :
					if ( in_array( $v, self::$ALLOWED_MODE ) ) {
						$settings['mode'] = $v;
					}
					break;
				case 'collate' :
					if ( $v === 'true' ) {
						$settings['collate'] = true;
					} elseif ( $v === 'false' ) {
						$settings['collate'] = false;
					}
					break;
				case 'smoothing' :
					if ( $v === 'laplace' ) {
						$settings['smoothing_model'] = [
							'laplace' => [
								'alpha' => 0.5
							]
						];
					} elseif ( $v === 'stupid_backoff' ) {
						$settings['smoothing_model'] = [
							'stupid_backoff' => [
								'discount' => 0.4
							]
						];
					}
					break;
				case 'laplace_alpha' :
					if ( is_numeric( $v ) && $v >= 0 && $v <= 1 ) {
						$laplaceAlpha = floatval( $v );
					}
					break;
				case 'stupid_backoff_discount' :
					if ( is_numeric( $v ) && $v >= 0 && $v <= 1 ) {
						$stupidBackoffDiscount = floatval( $v );
					}
					break;
			}
		}

		// Apply smoothing model options, if none provided we'll use elasticsearch defaults
		if ( isset( $settings['smoothing_model']['laplace'] ) &&
			 isset( $laplaceAlpha ) ) {
			$settings['smoothing_model']['laplace'] = [
				'alpha' => $laplaceAlpha
			];
		}
		if ( isset( $settings['smoothing_model']['stupid_backoff'] ) &&
			 isset( $stupidBackoffDiscount ) ) {
			$settings['smoothing_model']['stupid_backoff'] = [
				'discount' => $stupidBackoffDiscount
			];
		}
		return $settings;
	}

	/**
	 * Check if a profile named $name exists in this repository
	 * @param string $name
	 * @return bool
	 */
	public function hasProfile( $name ) {
		return $this->wrapped->hasProfile( $name );
	}

	/**
	 * Get the list of profiles that we want to expose to the user.
	 *
	 * @return array[] list of profiles index by name
	 */
	public function listExposedProfiles() {
		return $this->wrapped->listExposedProfiles();
	}
}
