<?php

/**
 * Class responsible for validating Gadget definition contents
 *
 * @todo maybe this should use a formal JSON schema validator or something
 */
class GadgetDefinitionValidator {
	/**
	 * Validation metadata.
	 * 'foo.bar.baz' => [ 'type check callback',
	 *   'type name' [, 'member type check callback', 'member type name'] ]
	 */
	protected static $propertyValidation = [
		'settings' => [ 'is_array', 'array' ],
		'settings.rights' => [ 'is_array', 'array' , 'is_string', 'string' ],
		'settings.default' => [ 'is_bool', 'boolean' ],
		'settings.hidden' => [ 'is_bool', 'boolean' ],
		'settings.skins' => [ [ __CLASS__, 'isArrayOrTrue' ], 'array or true', 'is_string', 'string' ],
		'settings.category' => [ 'is_string', 'string' ],
		'module' => [ 'is_array', 'array' ],
		'module.scripts' => [ 'is_array', 'array', 'is_string', 'string' ],
		'module.styles' => [ 'is_array', 'array', 'is_string', 'string' ],
		'module.dependencies' => [ 'is_array', 'array', 'is_string', 'string' ],
		'module.peers' => [ 'is_array', 'array', 'is_string', 'string' ],
		'module.messages' => [ 'is_array', 'array', 'is_string', 'string' ],
		'module.type' => [ 'is_string', 'string' ],
	];

	/**
	 * @param mixed $value
	 * @return bool
	 */
	public static function isArrayOrTrue( $value ) {
		return is_array( $value ) || $value === true;
	}

	/**
	 * Check the validity of the given properties array
	 * @param array $properties Return value of FormatJson::decode( $blob, true )
	 * @param bool $tolerateMissing If true, don't complain about missing keys
	 * @return Status object with error message if applicable
	 */
	public function validate( array $properties, $tolerateMissing = false ) {
		foreach ( self::$propertyValidation as $property => $validation ) {
			$path = explode( '.', $property );
			$val = $properties;

			// Walk down and verify that the path from the root to this property exists
			foreach ( $path as $p ) {
				if ( !array_key_exists( $p, $val ) ) {
					if ( $tolerateMissing ) {
						// Skip validation of this property altogether
						continue 2;
					} else {
						return Status::newFatal( 'gadgets-validate-notset', $property );
					}
				}
				$val = $val[$p];
			}

			// Do the actual validation of this property
			$func = $validation[0];
			if ( !call_user_func( $func, $val ) ) {
				return Status::newFatal(
						'gadgets-validate-wrongtype',
						$property,
						$validation[1],
						gettype( $val )
				);
			}

			if ( isset( $validation[2] ) && is_array( $val ) ) {
				// Descend into the array and check the type of each element
				$func = $validation[2];
				foreach ( $val as $i => $v ) {
					if ( !call_user_func( $func, $v ) ) {
						return Status::newFatal(
								'gadgets-validate-wrongtype',
								"{$property}[{$i}]",
								$validation[3],
								gettype( $v )
						);
					}
				}
			}
		}

		return Status::newGood();
	}
}
