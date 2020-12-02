<?php

namespace Wikimedia\ParamValidator\TypeDef;

use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Type definition for a floating-point type
 *
 * A valid representation consists of:
 *  - an optional sign (`+` or `-`)
 *  - a decimal number, using `.` as the decimal separator and no grouping
 *  - an optional E-notation suffix: the letter 'e' or 'E', an optional
 *    sign, and an integer
 *
 * Thus, for example, "12", "-.4", "6.022e23", or "+1.7e-10".
 *
 * The result from validate() is a PHP float.
 *
 * Failure codes:
 *  - 'badfloat': The value was invalid. No data.
 *  - 'badfloat-notfinite': The value was in a valid format, but conversion resulted in
 *    infinity or NAN.
 *
 * @since 1.34
 * @unstable
 */
class FloatDef extends NumericDef {

	protected $valueType = 'double';

	public function validate( $name, $value, array $settings, array $options ) {
		// Use a regex so as to avoid any potential oddness PHP's default conversion might allow.
		if ( !preg_match( '/^[+-]?(?:\d*\.)?\d+(?:[eE][+-]?\d+)?$/D', $value ) ) {
			$this->failure( 'badfloat', $name, $value, $settings, $options );
		}

		$ret = (float)$value;
		if ( !is_finite( $ret ) ) {
			$this->failure( 'badfloat-notfinite', $name, $value, $settings, $options );
		}

		return $this->checkRange( $ret, $name, $value, $settings, $options );
	}

	/**
	 * Attempt to fix locale weirdness
	 *
	 * We don't have any usable number formatting function that's not locale-aware,
	 * and `setlocale()` isn't safe in multithreaded environments. Sigh.
	 *
	 * @param string $value Value to fix
	 * @return string
	 */
	private function fixLocaleWeirdness( $value ) {
		$localeData = localeconv();
		if ( $localeData['decimal_point'] !== '.' ) {
			$value = strtr( $value, [
				$localeData['decimal_point'] => '.',
				// PHP's number formatting currently uses only the first byte from 'decimal_point'.
				// See upstream bug https://bugs.php.net/bug.php?id=78113
				$localeData['decimal_point'][0] => '.',
			] );
		}
		return $value;
	}

	public function stringifyValue( $name, $value, array $settings, array $options ) {
		// Ensure sufficient precision for round-tripping. PHP_FLOAT_DIG was added in PHP 7.2.
		$digits = defined( 'PHP_FLOAT_DIG' ) ? PHP_FLOAT_DIG : 15;
		return $this->fixLocaleWeirdness( sprintf( "%.{$digits}g", $value ) );
	}

	public function getHelpInfo( $name, array $settings, array $options ) {
		$info = parent::getHelpInfo( $name, $settings, $options );

		$info[ParamValidator::PARAM_TYPE] = MessageValue::new( 'paramvalidator-help-type-float' )
			->params( empty( $settings[ParamValidator::PARAM_ISMULTI] ) ? 1 : 2 );

		return $info;
	}

}
