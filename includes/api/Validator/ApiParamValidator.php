<?php

namespace MediaWiki\Api\Validator;

use ApiBase;
use ApiMain;
use ApiMessage;
use ApiUsageException;
use MediaWiki\Message\Converter as MessageConverter;
use MediaWiki\ParamValidator\TypeDef\NamespaceDef;
use MediaWiki\ParamValidator\TypeDef\TagsDef;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use Message;
use Wikimedia\Message\DataMessageValue;
use Wikimedia\Message\MessageValue;
use Wikimedia\ObjectFactory;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\EnumDef;
use Wikimedia\ParamValidator\TypeDef\ExpiryDef;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;
use Wikimedia\ParamValidator\TypeDef\LimitDef;
use Wikimedia\ParamValidator\TypeDef\PasswordDef;
use Wikimedia\ParamValidator\TypeDef\PresenceBooleanDef;
use Wikimedia\ParamValidator\TypeDef\StringDef;
use Wikimedia\ParamValidator\TypeDef\TimestampDef;
use Wikimedia\ParamValidator\TypeDef\UploadDef;
use Wikimedia\ParamValidator\ValidationException;

/**
 * This wraps a bunch of the API-specific parameter validation logic.
 *
 * It's intended to be used in ApiMain by composition.
 *
 * @since 1.35
 * @ingroup API
 */
class ApiParamValidator {

	/** @var ParamValidator */
	private $paramValidator;

	/** @var MessageConverter */
	private $messageConverter;

	/** Type defs for ParamValidator */
	private const TYPE_DEFS = [
		'boolean' => [ 'class' => PresenceBooleanDef::class ],
		'enum' => [ 'class' => EnumDef::class ],
		'expiry' => [ 'class' => ExpiryDef::class ],
		'integer' => [ 'class' => IntegerDef::class ],
		'limit' => [ 'class' => LimitDef::class ],
		'namespace' => [
			'class' => NamespaceDef::class,
			'services' => [ 'NamespaceInfo' ],
		],
		'NULL' => [
			'class' => StringDef::class,
			'args' => [ [
				'allowEmptyWhenRequired' => true,
			] ],
		],
		'password' => [ 'class' => PasswordDef::class ],
		'string' => [ 'class' => StringDef::class ],
		'submodule' => [ 'class' => SubmoduleDef::class ],
		'tags' => [ 'class' => TagsDef::class ],
		'text' => [ 'class' => StringDef::class ],
		'timestamp' => [
			'class' => TimestampDef::class,
			'args' => [ [
				'defaultFormat' => TS_MW,
			] ],
		],
		'user' => [ 'class' => UserDef::class ],
		'upload' => [ 'class' => UploadDef::class ],
	];

	/**
	 * @internal
	 * @param ApiMain $main
	 * @param ObjectFactory $objectFactory
	 */
	public function __construct( ApiMain $main, ObjectFactory $objectFactory ) {
		$this->paramValidator = new ParamValidator(
			new ApiParamValidatorCallbacks( $main ),
			$objectFactory,
			[
				'typeDefs' => self::TYPE_DEFS,
				'ismultiLimits' => [ ApiBase::LIMIT_SML1, ApiBase::LIMIT_SML2 ],
			]
		);
		$this->messageConverter = new MessageConverter();
	}

	/**
	 * List known type names
	 * @return string[]
	 */
	public function knownTypes() : array {
		return $this->paramValidator->knownTypes();
	}

	/**
	 * Map deprecated styles for messages for ParamValidator
	 * @param array $settings
	 * @return array
	 */
	private function mapDeprecatedSettingsMessages( array $settings ) : array {
		if ( isset( $settings[EnumDef::PARAM_DEPRECATED_VALUES] ) ) {
			foreach ( $settings[EnumDef::PARAM_DEPRECATED_VALUES] as &$v ) {
				if ( $v === null || $v === true || $v instanceof MessageValue ) {
					continue;
				}

				// Convert the message specification to a DataMessageValue. Flag in the data
				// that it was so converted, so ApiParamValidatorCallbacks::recordCondition() can
				// take that into account.
				// @phan-suppress-next-line PhanTypeMismatchArgument
				$msg = $this->messageConverter->convertMessage( ApiMessage::create( $v ) );
				$v = DataMessageValue::new(
					$msg->getKey(),
					$msg->getParams(),
					'bogus',
					[ '💩' => 'back-compat' ]
				);
			}
			unset( $v );
		}

		return $settings;
	}

	/**
	 * Adjust certain settings where ParamValidator differs from historical Action API behavior
	 * @param array|mixed $settings
	 * @return array
	 */
	public function normalizeSettings( $settings ) : array {
		if ( is_array( $settings ) ) {
			if ( !isset( $settings[ParamValidator::PARAM_IGNORE_UNRECOGNIZED_VALUES] ) ) {
				$settings[ParamValidator::PARAM_IGNORE_UNRECOGNIZED_VALUES] = true;
			}

			if ( !isset( $settings[IntegerDef::PARAM_IGNORE_RANGE] ) ) {
				$settings[IntegerDef::PARAM_IGNORE_RANGE] = empty( $settings[ApiBase::PARAM_RANGE_ENFORCE] );
			}

			$settings = $this->mapDeprecatedSettingsMessages( $settings );
		}

		return $this->paramValidator->normalizeSettings( $settings );
	}

	/**
	 * Check an API settings message
	 * @param ApiBase $module
	 * @param string $key
	 * @param mixed $value
	 * @param array &$ret
	 */
	private function checkSettingsMessage( ApiBase $module, string $key, $value, array &$ret ) : void {
		$msg = ApiBase::makeMessage( $value, $module );
		if ( $msg instanceof Message ) {
			$ret['messages'][] = $this->messageConverter->convertMessage( $msg );
		} else {
			$ret['issues'][] = "Message specification for $key is not valid";
		}
	}

	/**
	 * Check settings for the Action API.
	 * @param ApiBase $module
	 * @param array $params All module params to test
	 * @param string $name Parameter to test
	 * @param array $options Options array
	 * @return array As for ParamValidator::checkSettings()
	 */
	public function checkSettings(
		ApiBase $module, array $params, string $name, array $options
	) : array {
		$options['module'] = $module;
		$settings = $params[$name];
		if ( is_array( $settings ) ) {
			$settings = $this->mapDeprecatedSettingsMessages( $settings );
		}
		$ret = $this->paramValidator->checkSettings(
			$module->encodeParamName( $name ), $settings, $options
		);

		$ret['allowedKeys'] = array_merge( $ret['allowedKeys'], [
			ApiBase::PARAM_RANGE_ENFORCE, ApiBase::PARAM_HELP_MSG, ApiBase::PARAM_HELP_MSG_APPEND,
			ApiBase::PARAM_HELP_MSG_INFO, ApiBase::PARAM_HELP_MSG_PER_VALUE, ApiBase::PARAM_TEMPLATE_VARS,
		] );

		if ( !is_array( $settings ) ) {
			$settings = [];
		}

		if ( array_key_exists( ApiBase::PARAM_VALUE_LINKS, $settings ) ) {
			$ret['issues'][ApiBase::PARAM_VALUE_LINKS]
				= 'PARAM_VALUE_LINKS was deprecated in MediaWiki 1.35';
		}

		if ( !is_bool( $settings[ApiBase::PARAM_RANGE_ENFORCE] ?? false ) ) {
			$ret['issues'][ApiBase::PARAM_RANGE_ENFORCE] = 'PARAM_RANGE_ENFORCE must be boolean, got '
				. gettype( $settings[ApiBase::PARAM_RANGE_ENFORCE] );
		}

		if ( isset( $settings[ApiBase::PARAM_HELP_MSG] ) ) {
			$this->checkSettingsMessage(
				$module, 'PARAM_HELP_MSG', $settings[ApiBase::PARAM_HELP_MSG], $ret
			);
		}

		if ( isset( $settings[ApiBase::PARAM_HELP_MSG_APPEND] ) ) {
			if ( !is_array( $settings[ApiBase::PARAM_HELP_MSG_APPEND] ) ) {
				$ret['issues'][ApiBase::PARAM_HELP_MSG_APPEND] = 'PARAM_HELP_MSG_APPEND must be an array, got '
					. gettype( $settings[ApiBase::PARAM_HELP_MSG_APPEND] );
			} else {
				foreach ( $settings[ApiBase::PARAM_HELP_MSG_APPEND] as $k => $v ) {
					$this->checkSettingsMessage( $module, "PARAM_HELP_MSG_APPEND[$k]", $v, $ret );
				}
			}
		}

		if ( isset( $settings[ApiBase::PARAM_HELP_MSG_INFO] ) ) {
			if ( !is_array( $settings[ApiBase::PARAM_HELP_MSG_INFO] ) ) {
				$ret['issues'][ApiBase::PARAM_HELP_MSG_INFO] = 'PARAM_HELP_MSG_INFO must be an array, got '
					. gettype( $settings[ApiBase::PARAM_HELP_MSG_INFO] );
			} else {
				$path = $module->getModulePath();
				foreach ( $settings[ApiBase::PARAM_HELP_MSG_INFO] as $k => $v ) {
					if ( !is_array( $v ) ) {
						$ret['issues'][] = "PARAM_HELP_MSG_INFO[$k] must be an array, got " . gettype( $v );
					} elseif ( !is_string( $v[0] ) ) {
						$ret['issues'][] = "PARAM_HELP_MSG_INFO[$k][0] must be a string, got " . gettype( $v[0] );
					} else {
						$v[0] = "apihelp-{$path}-paraminfo-{$v[0]}";
						$this->checkSettingsMessage( $module, "PARAM_HELP_MSG_INFO[$k]", $v, $ret );
					}
				}
			}
		}

		if ( isset( $settings[ApiBase::PARAM_HELP_MSG_PER_VALUE] ) ) {
			if ( !is_array( $settings[ApiBase::PARAM_HELP_MSG_PER_VALUE] ) ) {
				$ret['issues'][ApiBase::PARAM_HELP_MSG_PER_VALUE] = 'PARAM_HELP_MSG_PER_VALUE must be an array,'
				   . ' got ' . gettype( $settings[ApiBase::PARAM_HELP_MSG_PER_VALUE] );
			} elseif ( !is_array( $settings[ParamValidator::PARAM_TYPE] ?? '' ) ) {
				$ret['issues'][ApiBase::PARAM_HELP_MSG_PER_VALUE] = 'PARAM_HELP_MSG_PER_VALUE can only be used '
					. 'with PARAM_TYPE as an array';
			} else {
				$values = array_map( 'strval', $settings[ParamValidator::PARAM_TYPE] );
				foreach ( $settings[ApiBase::PARAM_HELP_MSG_PER_VALUE] as $k => $v ) {
					if ( !in_array( (string)$k, $values, true ) ) {
						// Or should this be allowed?
						$ret['issues'][] = "PARAM_HELP_MSG_PER_VALUE contains \"$k\", which is not in PARAM_TYPE.";
					}
					$this->checkSettingsMessage( $module, "PARAM_HELP_MSG_PER_VALUE[$k]", $v, $ret );
				}
			}
		}

		if ( isset( $settings[ApiBase::PARAM_TEMPLATE_VARS] ) ) {
			if ( !is_array( $settings[ApiBase::PARAM_TEMPLATE_VARS] ) ) {
				$ret['issues'][ApiBase::PARAM_TEMPLATE_VARS] = 'PARAM_TEMPLATE_VARS must be an array,'
				   . ' got ' . gettype( $settings[ApiBase::PARAM_TEMPLATE_VARS] );
			} elseif ( $settings[ApiBase::PARAM_TEMPLATE_VARS] === [] ) {
				$ret['issues'][ApiBase::PARAM_TEMPLATE_VARS] = 'PARAM_TEMPLATE_VARS cannot be the empty array';
			} else {
				foreach ( $settings[ApiBase::PARAM_TEMPLATE_VARS] as $key => $target ) {
					if ( !preg_match( '/^[^{}]+$/', $key ) ) {
						$ret['issues'][] = "PARAM_TEMPLATE_VARS keys may not contain '{' or '}', got \"$key\"";
					} elseif ( strpos( $name, '{' . $key . '}' ) === false ) {
						$ret['issues'][] = "Parameter name must contain PARAM_TEMPLATE_VARS key {{$key}}";
					}
					if ( !is_string( $target ) && !is_int( $target ) ) {
						$ret['issues'][] = "PARAM_TEMPLATE_VARS[$key] has invalid target type " . gettype( $target );
					} elseif ( !isset( $params[$target] ) ) {
						$ret['issues'][] = "PARAM_TEMPLATE_VARS[$key] target parameter \"$target\" does not exist";
					} else {
						$settings2 = $params[$target];
						if ( empty( $settings2[ParamValidator::PARAM_ISMULTI] ) ) {
							$ret['issues'][] = "PARAM_TEMPLATE_VARS[$key] target parameter \"$target\" must have "
								. 'PARAM_ISMULTI = true';
						}
						if ( isset( $settings2[ApiBase::PARAM_TEMPLATE_VARS] ) ) {
							if ( $target === $name ) {
								$ret['issues'][] = "PARAM_TEMPLATE_VARS[$key] cannot target the parameter itself";
							}
							if ( array_diff(
								$settings2[ApiBase::PARAM_TEMPLATE_VARS],
								$settings[ApiBase::PARAM_TEMPLATE_VARS]
							) ) {
								$ret['issues'][] = "PARAM_TEMPLATE_VARS[$key]: Target's "
									. 'PARAM_TEMPLATE_VARS must be a subset of the original';
							}
						}
					}
				}

				$keys = implode( '|', array_map(
					function ( $key ) {
						return preg_quote( $key, '/' );
					},
					array_keys( $settings[ApiBase::PARAM_TEMPLATE_VARS] )
				) );
				if ( !preg_match( '/^(?>[^{}]+|\{(?:' . $keys . ')\})+$/', $name ) ) {
					$ret['issues'][] = "Parameter name may not contain '{' or '}' other than '
						. 'as defined by PARAM_TEMPLATE_VARS";
				}
			}
		} elseif ( !preg_match( '/^[^{}]+$/', $name ) ) {
			$ret['issues'][] = "Parameter name may not contain '{' or '}' without PARAM_TEMPLATE_VARS";
		}

		return $ret;
	}

	/**
	 * Convert a ValidationException to an ApiUsageException
	 * @param ApiBase $module
	 * @param ValidationException $ex
	 * @throws ApiUsageException always
	 */
	private function convertValidationException( ApiBase $module, ValidationException $ex ) : array {
		$mv = $ex->getFailureMessage();
		throw ApiUsageException::newWithMessage(
			$module,
			$this->messageConverter->convertMessageValue( $mv ),
			$mv->getCode(),
			$mv->getData(),
			0,
			$ex
		);
	}

	/**
	 * Get and validate a value
	 * @param ApiBase $module
	 * @param string $name Parameter name, unprefixed
	 * @param array|mixed $settings Default value or an array of settings
	 *  using PARAM_* constants.
	 * @param array $options Options array
	 * @return mixed Validated parameter value
	 * @throws ApiUsageException if the value is invalid
	 */
	public function getValue( ApiBase $module, string $name, $settings, array $options = [] ) {
		$options['module'] = $module;
		$name = $module->encodeParamName( $name );
		$settings = $this->normalizeSettings( $settings );
		try {
			return $this->paramValidator->getValue( $name, $settings, $options );
		} catch ( ValidationException $ex ) {
			$this->convertValidationException( $module, $ex );
		}
	}

	/**
	 * Valiate a parameter value using a settings array
	 *
	 * @param ApiBase $module
	 * @param string $name Parameter name, unprefixed
	 * @param mixed $value Parameter value
	 * @param array|mixed $settings Default value or an array of settings
	 *  using PARAM_* constants.
	 * @param array $options Options array
	 * @return mixed Validated parameter value(s)
	 * @throws ApiUsageException if the value is invalid
	 */
	public function validateValue(
		ApiBase $module, string $name, $value, $settings, array $options = []
	) {
		$options['module'] = $module;
		$name = $module->encodeParamName( $name );
		$settings = $this->normalizeSettings( $settings );
		try {
			return $this->paramValidator->validateValue( $name, $value, $settings, $options );
		} catch ( ValidationException $ex ) {
			$this->convertValidationException( $module, $ex );
		}
	}

	/**
	 * Describe parameter settings in a machine-readable format.
	 *
	 * @param ApiBase $module
	 * @param string $name Parameter name.
	 * @param array|mixed $settings Default value or an array of settings
	 *  using PARAM_* constants.
	 * @param array $options Options array.
	 * @return array
	 */
	public function getParamInfo( ApiBase $module, string $name, $settings, array $options ) : array {
		$options['module'] = $module;
		$name = $module->encodeParamName( $name );
		return $this->paramValidator->getParamInfo( $name, $settings, $options );
	}

	/**
	 * Describe parameter settings in human-readable format
	 *
	 * @param ApiBase $module
	 * @param string $name Parameter name being described.
	 * @param array|mixed $settings Default value or an array of settings
	 *  using PARAM_* constants.
	 * @param array $options Options array.
	 * @return Message[]
	 */
	public function getHelpInfo( ApiBase $module, string $name, $settings, array $options ) : array {
		$options['module'] = $module;
		$name = $module->encodeParamName( $name );

		$ret = $this->paramValidator->getHelpInfo( $name, $settings, $options );
		foreach ( $ret as &$m ) {
			$k = $m->getKey();
			$m = $this->messageConverter->convertMessageValue( $m );
			if ( substr( $k, 0, 20 ) === 'paramvalidator-help-' ) {
				$m = new Message(
					[ 'api-help-param-' . substr( $k, 20 ), $k ],
					$m->getParams()
				);
			}
		}
		'@phan-var Message[] $ret'; // The above loop converts it

		return $ret;
	}
}
