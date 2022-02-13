<?php declare( strict_types=1 );

/**
 * Base class for SecurityCheckPlugin. Extend if you want to customize.
 *
 * Copyright (C) 2017  Brian Wolff <bawolff@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */
require_once __DIR__ . '/TaintednessBaseVisitor.php';
require_once __DIR__ . '/PreTaintednessVisitor.php';
require_once __DIR__ . '/TaintednessVisitor.php';
require_once __DIR__ . '/GetReturnObjsVisitor.php';

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\FQSEN\FullyQualifiedFunctionLikeName;
use Phan\PluginV2;
use Phan\PluginV2\PostAnalyzeNodeCapability;
use Phan\PluginV2\PreAnalyzeNodeCapability;

/**
 * @suppress PhanUnreferencedPublicClassConstant They're for use in custom plugins, too
 */
abstract class SecurityCheckPlugin extends PluginV2
	implements PostAnalyzeNodeCapability, PreAnalyzeNodeCapability
{

	// Various taint flags. The _EXEC_ varieties mean
	// that it is unsafe to assign that type of taint
	// to the variable in question.

	public const NO_TAINT = 0;
	// For declaration type things. Given a special value for
	// debugging purposes, but inapplicable taint should not
	// actually show up anywhere.
	public const INAPPLICABLE_TAINT = 1;
	// Flag to denote that we don't know
	public const UNKNOWN_TAINT = 2;
	// Flag for function parameters and the like, where it
	// preserves whatever taint the function is given.
	public const PRESERVE_TAINT = 4;

	// In future might separate out different types of html quoting.
	// e.g. "<div data-foo='" . htmlspecialchars( $bar ) . "'>";
	// is unsafe.
	public const HTML_TAINT = 8;
	public const HTML_EXEC_TAINT = 16;
	public const SQL_TAINT = 32;
	public const SQL_EXEC_TAINT = 64;
	public const SHELL_TAINT = 128;
	public const SHELL_EXEC_TAINT = 256;
	public const SERIALIZE_TAINT = 512;
	public const SERIALIZE_EXEC_TAINT = 1024;
	// To allow people to add other application specific
	// taints.
	public const CUSTOM1_TAINT = 2048;
	public const CUSTOM1_EXEC_TAINT = 4096;
	public const CUSTOM2_TAINT = 8192;
	public const CUSTOM2_EXEC_TAINT = 16384;
	// For stuff that doesn't fit another
	// category (For the moment, this is stuff like `require $foo`)
	public const MISC_TAINT = 32768;
	public const MISC_EXEC_TAINT = 65536;
	// Special purpose for supporting MediaWiki's IDatabase::select
	// and friends. Like SQL_TAINT, but only applies to the numeric
	// keys of an array. Note: These are not included in YES_TAINT/EXEC_TAINT.
	// e.g. given $f = [ $_GET['foo'] ]; $f would have the flag, but
	// $g = $_GET['foo']; or $h = [ 's' => $_GET['foo'] ] would not.
	// The associative keys also have this flag if they are tainted.
	// It is also assumed anything with this flag will also have
	// the SQL_TAINT flag set.
	public const SQL_NUMKEY_TAINT = 131072;
	public const SQL_NUMKEY_EXEC_TAINT = 262144;
	// For double escaped variables
	public const ESCAPED_TAINT = 524288;
	public const ESCAPED_EXEC_TAINT = 1048576;

	// Special purpose flags(Starting at 2^28)
	// Cancel's out all EXEC flags on a function arg if arg is array.
	public const ARRAY_OK = 268435456;
	// Represents a parameter expecting a raw value, for which escaping should have already
	// taken place. E.g. in MW this happens for Message::rawParams. In practice, this turns
	// the func taint into EXEC, but without propagation.
	public const RAW_PARAM = 1073741824;
	// Do not allow autodetected taint info override given taint.
	public const NO_OVERRIDE = 0x20000000;

	// Combination flags.
	// YES_TAINT denotes all taint a user controlled variable would have
	public const YES_TAINT = 43688;
	public const EXEC_TAINT = 87376;
	public const YES_EXEC_TAINT = 131064;
	// ALL taint is YES + special purpose taints, but not including special flags.
	public const ALL_TAINT = self::YES_TAINT | self::SQL_NUMKEY_TAINT | self::ESCAPED_TAINT;
	public const ALL_EXEC_TAINT =
		self::EXEC_TAINT | self::SQL_NUMKEY_EXEC_TAINT | self::ESCAPED_EXEC_TAINT;
	public const ALL_YES_EXEC_TAINT = self::ALL_TAINT | self::ALL_EXEC_TAINT;
	// Taints that support backpropagation. Does not include numkey
	// due to special array handling.
	public const BACKPROP_TAINTS = self::ALL_EXEC_TAINT & ~self::SQL_NUMKEY_EXEC_TAINT;
	public const ESCAPES_HTML = ( self::YES_TAINT & ~self::HTML_TAINT ) | self::ESCAPED_EXEC_TAINT;

	/**
	 * @var SecurityCheckPlugin Passed to the visitor for context
	 */
	public static $pluginInstance;

	/**
	 * Save the subclass instance to make it accessible from the visitor
	 */
	public function __construct() {
		self::$pluginInstance = $this;
	}

	/**
	 * Get the taintedness of a function
	 *
	 * This allows overriding the default taint of a function
	 *
	 * If you want to provide custom taint hints for your application,
	 * normally you would override the getCustomFuncTaints() method, not this one.
	 *
	 * @param FullyQualifiedFunctionLikeName $fqsen The function/method in question
	 * @return array|null Null to autodetect taintedness. Otherwise an array
	 *   Numeric keys reflect how the taintedness the parameter reflect the
	 *   return value, or whether the parameter is directly executed.
	 *   The special key overall controls the taint of the function
	 *   irrespective of its parameters. The overall keys must always be specified.
	 *   As a special case, if the overall key has self::PRESERVE_TAINT
	 *   then any unspecified keys behave like they are self::YES_TAINT.
	 *
	 *   For example: [ self::YES_TAINT, 'overall' => self::NO_TAINT ]
	 *   means that the taint of the return value is the same as the taint
	 *   of the the first arg, and all other args are ignored.
	 *   [ self::HTML_EXEC_TAINT, 'overall' => self::NO_TAINT ]
	 *   Means that the first arg is output in an html context (e.g. like echo)
	 *   [ self::YES_TAINT & ~self::HTML_TAINT, 'overall' => self::NO_TAINT ]
	 *   Means that the function removes html taint (escapes) e.g. htmlspecialchars
	 *   [ 'overall' => self::YES_TAINT ]
	 *   Means that it returns a tainted value (e.g. return $_POST['foo']; )
	 */
	public function getBuiltinFuncTaint( FullyQualifiedFunctionLikeName $fqsen ) {
		$funcTaints = $this->getCustomFuncTaints() + $this->getPHPFuncTaints();
		$name = (string)$fqsen;

		if ( isset( $funcTaints[$name] ) ) {
			$taint = $funcTaints[$name];
			// For backcompat, make self::NO_OVERRIDE always be set.
			foreach ( $taint as $_ => &$val ) {
				$val |= self::NO_OVERRIDE;
			}
			return $taint;
		}
		return null;
	}

	/**
	 * Get an array of function taints custom for the application
	 *
	 * @return array Array of function taints (See getBuiltinFuncTaint())
	 */
	abstract protected function getCustomFuncTaints() : array;

	/**
	 * Can be used to force specific issues to be marked false positives
	 *
	 * For example, a specific application might be able to recognize
	 * that we are in a CLI context, and thus the XSS is really a false positive.
	 *
	 * @note The $lhsTaint parameter uses the self::*_TAINT constants,
	 *   NOT the *_EXEC_TAINT constants.
	 * @param int $lhsTaint The dangerous taints to be output (e.g. LHS of assignment)
	 * @param int $rhsTaint The taint of the expression
	 * @param string &$msg Issue description (so plugin can modify to state why false)
	 * @param Context $context
	 * @param CodeBase $code_base
	 * @return bool Is this a false positive?
	 * @suppress PhanUnusedPublicMethodParameter No param is used
	 */
	public function isFalsePositive(
		int $lhsTaint,
		int $rhsTaint,
		string &$msg,
		Context $context,
		CodeBase $code_base
	) : bool {
		return false;
	}

	/**
	 * Given a param description line, extract taint
	 *
	 * This is to allow putting taint information in method docblocks.
	 * If a function has a docblock comment like:
	 *  *  @param-taint $foo escapes_html
	 * This converts that line into:
	 *   ( self::YES_TAINT & ~self::SQL_TAINT )
	 * Multiple taint types are separated by commas
	 * (which are interpreted as bitwise OR ( "|" ). Future versions
	 * might support more complex bitwise operators, but for now it
	 * doesn't seem needed.
	 *
	 * The following keywords are supported where {type} can be
	 * html, sql, shell, serialize, custom1, custom2, misc, sql_numkey,
	 * escaped.
	 *  * {type} - just set the flag. 99% you should only use 'none' or 'tainted'
	 *  * exec_{type} - sets the exec flag.
	 *  * escapes_{type} - self::YES_TAINT & ~self::{type}_TAINT.
	 *     Note: escapes_html adds the exec_escaped flag, use
	 *     escapes_htmlnoent if the value is safe to double encode.
	 *  * onlysafefor_{type}
	 *     Same as above, intended for return type declarations.
	 *     Only difference is that onlysafefor_html sets ESCAPED_TAINT instead
	 *     of ESCAPED_EXEC_TAINT
	 *  * none - self::NO_TAINT
	 *  * tainted - self::YES_TAINT
	 *  * array_ok - sets self::ARRAY_OK
	 *  * allow_override - Allow autodetected taints to override annotation
	 *
	 * @todo Should UNKOWN_TAINT be in here? What about ~ operator?
	 * @note The special casing to have escapes_html always add exec_escaped
	 *   (and having htmlnoent exist) is "experimental" and may change in
	 *   future versions (Maybe all types should set exec_escaped. Maybe it
	 *   should be explicit)
	 * @param string $line A line from the docblock
	 * @return null|int null on no info, or taint info integer.
	 */
	public static function parseTaintLine( string $line ) {
		$types = '(?P<type>htmlnoent|html|sql|shell|serialize|custom1|'
			. 'custom2|misc|sql_numkey|escaped|none|tainted|raw_param)';
		$prefixes = '(?P<prefix>escapes|onlysafefor|exec)';
		$taintExpr = "/^(?P<taint>(?:${prefixes}_)?$types|array_ok|allow_override)$/";

		$taints = explode( ',', strtolower( $line ) );
		$taints = array_map( 'trim', $taints );

		$overallTaint = self::NO_OVERRIDE;
		$numberOfTaintsProcessed = 0;
		foreach ( $taints as $taint ) {
			$taintParts = [];
			if ( !preg_match( $taintExpr, $taint, $taintParts ) ) {
				continue;
			}
			$numberOfTaintsProcessed++;
			if ( $taintParts['taint'] === 'array_ok' ) {
				$overallTaint |= self::ARRAY_OK;
				continue;
			}
			if ( $taintParts['taint'] === 'allow_override' ) {
				$overallTaint = $overallTaint & ( ~self::NO_OVERRIDE );
				continue;
			}
			$taintAsInt = self::convertTaintNameToConstant( $taintParts['type'] );
			switch ( $taintParts['prefix'] ) {
				case '':
					$overallTaint |= $taintAsInt;
					break;
				case 'exec':
					$overallTaint |= ( $taintAsInt << 1 );
					break;
				case 'escapes':
				case 'onlysafefor':
					$overallTaint |= ( self::YES_TAINT & ~$taintAsInt );
					if ( $taintParts['type'] === 'html' ) {
						if ( $taintParts['prefix'] === 'escapes' ) {
							$overallTaint |= self::ESCAPED_EXEC_TAINT;
						} else {
							$overallTaint |= self::ESCAPED_TAINT;
						}
					}
					break;
			}
		}
		if ( $numberOfTaintsProcessed === 0 ) {
			return null;
		}
		return $overallTaint;
	}

	/**
	 * Convert a string like 'html' to self::HTML_TAINT.
	 *
	 * @note htmlnoent treated like self::HTML_TAINT.
	 * @param string $name one of:
	 *   html, sql, shell, serialize, custom1, custom2, misc, sql_numkey,
	 *   escaped, none (= self::NO_TAINT), tainted (= self::YES_TAINT)
	 * @return int One of the TAINT constants
	 */
	public static function convertTaintNameToConstant( string $name ) : int {
		switch ( $name ) {
			case 'html':
			case 'htmlnoent':
				return self::HTML_TAINT;
			case 'sql':
				return self::SQL_TAINT;
			case 'shell':
				return self::SHELL_TAINT;
			case 'serialize':
				return self::SERIALIZE_TAINT;
			case 'custom1':
				return self::CUSTOM1_TAINT;
			case 'custom2':
				return self::CUSTOM2_TAINT;
			case 'misc':
				return self::MISC_TAINT;
			case 'sql_numkey':
				return self::SQL_NUMKEY_TAINT;
			case 'escaped':
				return self::ESCAPED_TAINT;
			case 'tainted':
				return self::YES_TAINT;
			case 'none':
				return self::NO_TAINT;
			case 'raw_param':
				return self::RAW_PARAM;
			default:
				throw new AssertionError( "$name not valid taint" );
		}
	}

	/**
	 * Taints for builtin php functions
	 *
	 * @return array List of func taints (See getBuiltinFuncTaint())
	 */
	protected function getPHPFuncTaints() : array {
		return [
			'\htmlentities' => [
				self::ESCAPES_HTML,
				'overall' => self::ESCAPED_TAINT
			],
			'\htmlspecialchars' => [
				self::ESCAPES_HTML,
				'overall' => self::ESCAPED_TAINT
			],
			'\escapeshellarg' => [
				~self::SHELL_TAINT & self::YES_TAINT,
				'overall' => self::NO_TAINT
			],
			// Or any time the serialized data comes from a trusted source.
			'\serialize' => [
				'overall' => self::YES_TAINT & ~self::SERIALIZE_TAINT,
			],
			'\unserialize' => [
				self::SERIALIZE_EXEC_TAINT,
				'overall' => self::NO_TAINT,
			],
			'\mysql_query' => [
				self::SQL_EXEC_TAINT,
				'overall' => self::UNKNOWN_TAINT
			],
			'\mysqli_query' => [
				self::NO_TAINT,
				self::SQL_EXEC_TAINT,
				'overall' => self::UNKNOWN_TAINT
			],
			'\mysqli::query' => [
				self::SQL_EXEC_TAINT,
				'overall' => self::UNKNOWN_TAINT
			],
			'\mysqli_real_query' => [
				self::NO_TAINT,
				self::SQL_EXEC_TAINT,
				'overall' => self::UNKNOWN_TAINT
			],
			'\mysqli::real_query' => [
				self::SQL_EXEC_TAINT,
				'overall' => self::UNKNOWN_TAINT
			],
			'\mysqli_escape_string' => [
				self::NO_TAINT,
				self::YES_TAINT & ~self::SQL_TAINT,
				'overall' => self::NO_TAINT
			],
			'\mysqli_real_escape_string' => [
				self::NO_TAINT,
				self::YES_TAINT & ~self::SQL_TAINT,
				'overall' => self::NO_TAINT
			],
			'\mysqli::escape_string' => [
				self::YES_TAINT & ~self::SQL_TAINT,
				'overall' => self::NO_TAINT
			],
			'\mysqli::real_escape_string' => [
				self::YES_TAINT & ~self::SQL_TAINT,
				'overall' => self::NO_TAINT
			],
			'\base64_encode' => [
				self::YES_TAINT & ~self::HTML_TAINT,
				'overall' => self::NO_TAINT
			],
		];
	}
}
