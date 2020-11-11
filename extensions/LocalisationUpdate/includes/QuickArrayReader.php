<?php

/**
 * Quickie parser class that can happily read the subset of PHP we need
 * for our localization arrays safely.
 *
 * Still an order of magnitude slower than eval().
 */
class QuickArrayReader {
	private $vars = [];

	/**
	 * @param string $string
	 */
	function __construct( $string ) {
		$scalarTypes = [
			T_LNUMBER => true,
			T_DNUMBER => true,
			T_STRING => true,
			T_CONSTANT_ENCAPSED_STRING => true,
		];
		$skipTypes = [
			T_WHITESPACE => true,
			T_COMMENT => true,
			T_DOC_COMMENT => true,
		];
		$tokens = token_get_all( $string );
		$count = count( $tokens );
		for ( $i = 0; $i < $count; ) {
			while ( isset( $skipTypes[$tokens[$i][0]] ) ) {
				$i++;
			}
			switch ( $tokens[$i][0] ) {
				case T_OPEN_TAG:
					$i++;
					break;
				case T_VARIABLE:
					// '$messages' -> 'messages'
					$varname = trim( substr( $tokens[$i][1], 1 ) );
					$varindex = null;

					while ( isset( $skipTypes[$tokens[++$i][0]] ) ) {
					}

					if ( $tokens[$i] === '[' ) {
						while ( isset( $skipTypes[$tokens[++$i][0]] ) ) {
						}

						if ( isset( $scalarTypes[$tokens[$i][0]] ) ) {
							$varindex = $this->parseScalar( $tokens[$i] );
						} else {
							throw $this->except( $tokens[$i], 'scalar index' );
						}
						while ( isset( $skipTypes[$tokens[++$i][0]] ) ) {
						}

						if ( $tokens[$i] !== ']' ) {
							throw $this->except( $tokens[$i], ']' );
						}
						while ( isset( $skipTypes[$tokens[++$i][0]] ) ) {
						}
					}

					if ( $tokens[$i] !== '=' ) {
						throw $this->except( $tokens[$i], '=' );
					}
					while ( isset( $skipTypes[$tokens[++$i][0]] ) ) {
					}

					if ( isset( $scalarTypes[$tokens[$i][0]] ) ) {
						$buildval = $this->parseScalar( $tokens[$i] );
					} elseif ( $tokens[$i][0] === T_ARRAY ) {
						while ( isset( $skipTypes[$tokens[++$i][0]] ) ) {
						}
						if ( $tokens[$i] !== '(' ) {
							throw $this->except( $tokens[$i], '(' );
						}
						$buildval = [];
						do {
							while ( isset( $skipTypes[$tokens[++$i][0]] ) ) {
							}

							if ( $tokens[$i] === ')' ) {
								break;
							}
							if ( isset( $scalarTypes[$tokens[$i][0]] ) ) {
								$key = $this->parseScalar( $tokens[$i] );
							}
							while ( isset( $skipTypes[$tokens[++$i][0]] ) ) {
							}

							if ( $tokens[$i][0] !== T_DOUBLE_ARROW ) {
								throw $this->except( $tokens[$i], '=>' );
							}
							while ( isset( $skipTypes[$tokens[++$i][0]] ) ) {
							}

							if ( isset( $scalarTypes[$tokens[$i][0]] ) ) {
								$val = $this->parseScalar( $tokens[$i] );
							}
							wfSuppressWarnings();
							$buildval[$key] = $val;
							wfRestoreWarnings();
							while ( isset( $skipTypes[$tokens[++$i][0]] ) ) {
							}

							if ( $tokens[$i] === ',' ) {
								continue;
							} elseif ( $tokens[$i] === ')' ) {
								break;
							} else {
								throw $this->except( $tokens[$i], ', or )' );
							}
						} while ( true );
					} else {
						throw $this->except( $tokens[$i], 'scalar or array' );
					}
					if ( is_null( $varindex ) ) {
						$this->vars[$varname] = $buildval;
					} else {
						wfSuppressWarnings();
						$this->vars[$varname][$varindex] = $buildval;
						wfRestoreWarnings();
					}
					while ( isset( $skipTypes[$tokens[++$i][0]] ) ) {
					}
					if ( $tokens[$i] !== ';' ) {
						throw $this->except( $tokens[$i], ';' );
					}
					$i++;
					break;
				default:
					throw $this->except( $tokens[$i], 'open tag, whitespace, or variable.' );
			}
		}
	}

	/**
	 * @param string $got
	 * @param string $expected
	 * @return Exception
	 */
	private function except( $got, $expected ) {
		if ( is_array( $got ) ) {
			$got = token_name( $got[0] ) . " ('" . $got[1] . "')";
		} else {
			$got = "'" . $got . "'";
		}

		return new Exception( "Expected $expected, got $got" );
	}

	/**
	 * Parse a scalar value in PHP
	 *
	 * @param string $token
	 *
	 * @return mixed Parsed value
	 */
	function parseScalar( $token ) {
		if ( is_array( $token ) ) {
			$str = $token[1];
		} else {
			$str = $token;
		}
		if ( $str !== '' && $str[0] == '\'' ) {
			// Single-quoted string
			// @fixme trim() call is due to mystery bug where whitespace gets
			// appended to the token; without it we ended up reading in the
			// extra quote on the end!
			return strtr( substr( trim( $str ), 1, -1 ),
				[ '\\\'' => '\'', '\\\\' => '\\' ] );
		}

		wfSuppressWarnings();
		if ( $str !== '' && $str[0] == '"' ) {
			// Double-quoted string
			// @fixme trim() call is due to mystery bug where whitespace gets
			// appended to the token; without it we ended up reading in the
			// extra quote on the end!
			wfRestoreWarnings();
			return stripcslashes( substr( trim( $str ), 1, -1 ) );
		}
		wfRestoreWarnings();

		if ( substr( $str, 0, 4 ) === 'true' ) {
			return true;
		}

		if ( substr( $str, 0, 5 ) === 'false' ) {
			return false;
		}

		if ( substr( $str, 0, 4 ) === 'null' ) {
			return null;
		}

		// Must be some kind of numeric value, so let PHP's weak typing
		// be useful for a change
		return $str;
	}

	/**
	 * @param string $varname
	 * @return null|string|array
	 */
	function getVar( $varname ) {
		if ( isset( $this->vars[$varname] ) ) {
			return $this->vars[$varname];
		} else {
			return null;
		}
	}
}
