<?php
/**
 * Static analysis tool for MediaWiki extensions.
 *
 * To use, add this file to your phan plugins list.
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
 *
 */
require_once __DIR__ . "/src/SecurityCheckPlugin.php";
require_once __DIR__ . "/src/MWVisitor.php";
require_once __DIR__ . "/src/MWPreVisitor.php";

use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionLikeName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName as FQSENFunc;
use Phan\Language\FQSEN\FullyQualifiedMethodName as FQSENMethod;

class MediaWikiSecurityCheckPlugin extends SecurityCheckPlugin {
	/**
	 * @inheritDoc
	 */
	public static function getPostAnalyzeNodeVisitorClassName(): string {
		return MWVisitor::class;
	}

	/**
	 * @inheritDoc
	 */
	public static function getPreAnalyzeNodeVisitorClassName(): string {
		return MWPreVisitor::class;
	}

	/**
	 * @var array A mapping from hook names to FQSEN that implement it
	 */
	protected $hookSubscribers = [];

	/**
	 * @inheritDoc
	 */
	protected function getCustomFuncTaints() : array {
		$selectWrapper = [
				self::SQL_EXEC_TAINT,
				// List of fields. MW does not escape things like COUNT(*)
				self::SQL_EXEC_TAINT,
				// Where conditions
				self::SQL_NUMKEY_EXEC_TAINT,
				// the function name doesn't seem to be escaped
				self::SQL_EXEC_TAINT,
				// OPTIONS. Its complicated. HAVING is like WHERE
				// This is treated as special case
				self::NO_TAINT,
				// Join conditions. This is treated as special case
				self::NO_TAINT,
				// What should DB results be considered?
				'overall' => self::YES_TAINT
		];

		return [
			// Note, at the moment, this checks where the function
			// is implemented, so you can't use IDatabase.
			'\Wikimedia\Rdbms\Database::query' => [
				self::SQL_EXEC_TAINT,
				// What should DB results be considered?
				'overall' => self::YES_TAINT
			],
			'\Wikimedia\Rdbms\IDatabase::query' => [
				self::SQL_EXEC_TAINT,
				// What should DB results be considered?
				'overall' => self::YES_TAINT
			],
			'\Wikimedia\Rdbms\IMaintainableDatabase::query' => [
				self::SQL_EXEC_TAINT,
				// What should DB results be considered?
				'overall' => self::YES_TAINT
			],
			'\Wikimedia\Rdbms\DBConnRef::query' => [
				self::SQL_EXEC_TAINT,
				// What should DB results be considered?
				'overall' => self::YES_TAINT
			],
			'\Wikimedia\Rdbms\IDatabase::select' => $selectWrapper,
			'\Wikimedia\Rdbms\IMaintainableDatabase::select' => $selectWrapper,
			'\Wikimedia\Rdbms\Database::select' => $selectWrapper,
			'\Wikimedia\Rdbms\DBConnRef::select' => $selectWrapper,
			'\Wikimedia\Rdbms\IDatabase::selectField' => $selectWrapper,
			'\Wikimedia\Rdbms\IMaintainableDatabase::selectField' => $selectWrapper,
			'\Wikimedia\Rdbms\Database::selectField' => $selectWrapper,
			'\Wikimedia\Rdbms\DBConnRef::selectField' => $selectWrapper,
			'\Wikimedia\Rdbms\IDatabase::selectFieldValues' => $selectWrapper,
			'\Wikimedia\Rdbms\IMaintainableDatabase::selectFieldValues' => $selectWrapper,
			'\Wikimedia\Rdbms\DBConnRef::selectFieldValues' => $selectWrapper,
			'\Wikimedia\Rdbms\Database::selectFieldValues' => $selectWrapper,
			'\Wikimedia\Rdbms\IMaintainableDatabase::selectSQLText' => [
					'overall' => self::YES_TAINT & ~self::SQL_TAINT
				] + $selectWrapper,
			'\Wikimedia\Rdbms\IDatabase::selectSQLText' => [
					'overall' => self::YES_TAINT & ~self::SQL_TAINT
				] + $selectWrapper,
			'\Wikimedia\Rdbms\DBConnRef::selectSQLText' => [
					'overall' => self::YES_TAINT & ~self::SQL_TAINT
				] + $selectWrapper,
			'\Wikimedia\Rdbms\Database::selectSQLText' => [
					'overall' => self::YES_TAINT & ~self::SQL_TAINT
				] + $selectWrapper,
			'\Wikimedia\Rdbms\IDatabase::selectRowCount' => $selectWrapper,
			'\Wikimedia\Rdbms\IMaintainableDatabase::selectRowCount' => $selectWrapper,
			'\Wikimedia\Rdbms\Database::selectRowCount' => $selectWrapper,
			'\Wikimedia\Rdbms\DBConnRef::selectRowCount' => $selectWrapper,
			'\Wikimedia\Rdbms\IDatabase::selectRow' => $selectWrapper,
			'\Wikimedia\Rdbms\IMaintainableDatabase::selectRow' => $selectWrapper,
			'\Wikimedia\Rdbms\Database::selectRow' => $selectWrapper,
			'\Wikimedia\Rdbms\DBConnRef::selectRow' => $selectWrapper,
			'\Wikimedia\Rdbms\IDatabase::delete' => [
				self::SQL_EXEC_TAINT,
				self::SQL_NUMKEY_EXEC_TAINT,
				self::SQL_EXEC_TAINT,
				'overall' => self::NO_TAINT
			],
			'\Wikimedia\Rdbms\IMaintainableDatabase::delete' => [
				self::SQL_EXEC_TAINT,
				self::SQL_NUMKEY_EXEC_TAINT,
				self::SQL_EXEC_TAINT,
				'overall' => self::NO_TAINT
			],
			'\Wikimedia\Rdbms\Database::delete' => [
				self::SQL_EXEC_TAINT,
				self::SQL_NUMKEY_EXEC_TAINT,
				self::SQL_EXEC_TAINT,
				'overall' => self::NO_TAINT
			],
			'\Wikimedia\Rdbms\DBConnRef::delete' => [
				self::SQL_EXEC_TAINT,
				self::SQL_NUMKEY_EXEC_TAINT,
				self::SQL_EXEC_TAINT,
				'overall' => self::NO_TAINT
			],
			'\Wikimedia\Rdbms\IDatabase::insert' => [
				self::SQL_EXEC_TAINT, // table name
				// FIXME This doesn't correctly work
				// when inserting multiple things at once.
				self::SQL_NUMKEY_EXEC_TAINT,
				self::SQL_EXEC_TAINT, // method name
				self::SQL_EXEC_TAINT, // options. They are not escaped
				'overall' => self::NO_TAINT
			],
			'\Wikimedia\Rdbms\IMaintainableDatabase::insert' => [
				self::SQL_EXEC_TAINT, // table name
				// FIXME This doesn't correctly work
				// when inserting multiple things at once.
				self::SQL_NUMKEY_EXEC_TAINT,
				self::SQL_EXEC_TAINT, // method name
				self::SQL_EXEC_TAINT, // options. They are not escaped
				'overall' => self::NO_TAINT
			],
			'\Wikimedia\Rdbms\Database::insert' => [
				self::SQL_EXEC_TAINT, // table name
				// Insert values. The keys names are unsafe.
				// Unclear how well this works for the multi case.
				self::SQL_NUMKEY_EXEC_TAINT,
				self::SQL_EXEC_TAINT, // method name
				self::SQL_EXEC_TAINT, // options. They are not escaped
				'overall' => self::NO_TAINT
			],
			'\Wikimedia\Rdbms\DBConnRef::insert' => [
				self::SQL_EXEC_TAINT, // table name
				// Insert values. The keys names are unsafe.
				// Unclear how well this works for the multi case.
				self::SQL_NUMKEY_EXEC_TAINT,
				self::SQL_EXEC_TAINT, // method name
				self::SQL_EXEC_TAINT, // options. They are not escaped
				'overall' => self::NO_TAINT
			],
			'\Wikimedia\Rdbms\IDatabase::update' => [
				self::SQL_EXEC_TAINT, // table name
				self::SQL_NUMKEY_EXEC_TAINT,
				self::SQL_NUMKEY_EXEC_TAINT,
				self::SQL_EXEC_TAINT, // method name
				self::NO_TAINT, // options. They are validated
				'overall' => self::NO_TAINT
			],
			'\Wikimedia\Rdbms\IMaintainableDatabase::update' => [
				self::SQL_EXEC_TAINT, // table name
				self::SQL_NUMKEY_EXEC_TAINT,
				self::SQL_NUMKEY_EXEC_TAINT,
				self::SQL_EXEC_TAINT, // method name
				self::NO_TAINT, // options. They are validated
				'overall' => self::NO_TAINT
			],
			'\Wikimedia\Rdbms\Database::update' => [
				self::SQL_EXEC_TAINT, // table name
				self::SQL_NUMKEY_EXEC_TAINT,
				self::SQL_NUMKEY_EXEC_TAINT,
				self::SQL_EXEC_TAINT, // method name
				self::NO_TAINT, // options. They are validated
				'overall' => self::NO_TAINT
			],
			'\Wikimedia\Rdbms\DBConnRef::update' => [
				self::SQL_EXEC_TAINT, // table name
				self::SQL_NUMKEY_EXEC_TAINT,
				self::SQL_NUMKEY_EXEC_TAINT,
				self::SQL_EXEC_TAINT, // method name
				self::NO_TAINT, // options. They are validated
				'overall' => self::NO_TAINT
			],
			// This is subpar, as addIdentifierQuotes isn't always
			// the right type of escaping.
			'\Wikimedia\Rdbms\Database::addIdentifierQuotes' => [
				self::YES_TAINT & ~self::SQL_TAINT,
				'overall' => self::NO_TAINT
			],
			'\Wikimedia\Rdbms\DatabaseMysqlBase::addIdentifierQuotes' => [
				self::YES_TAINT & ~self::SQL_TAINT,
				'overall' => self::NO_TAINT
			],
			'\Wikimedia\Rdbms\DatabaseMssql::addIdentifierQuotes' => [
				self::YES_TAINT & ~self::SQL_TAINT,
				'overall' => self::NO_TAINT
			],
			'\Wikimedia\Rdbms\IDatabase::addIdentifierQuotes' => [
				self::YES_TAINT & ~self::SQL_TAINT,
				'overall' => self::NO_TAINT
			],
			'\Wikimedia\Rdbms\DBConnRef::addIdentifierQuotes' => [
				self::YES_TAINT & ~self::SQL_TAINT,
				'overall' => self::NO_TAINT
			],
			'\Wikimedia\Rdbms\Database::addQuotes' => [
				self::YES_TAINT & ~self::SQL_TAINT,
				'overall' => self::NO_TAINT
			],
			'\Wikimedia\Rdbms\DBConnRef::addQuotes' => [
				self::YES_TAINT & ~self::SQL_TAINT,
				'overall' => self::NO_TAINT
			],
			'\Wikimedia\Rdbms\DatabaseMysqlBase::addQuotes' => [
				self::YES_TAINT & ~self::SQL_TAINT,
				'overall' => self::NO_TAINT
			],
			'\Wikimedia\Rdbms\DatabaseMssql::addQuotes' => [
				self::YES_TAINT & ~self::SQL_TAINT,
				'overall' => self::NO_TAINT
			],
			'\Wikimedia\Rdbms\IDatabase::addQuotes' => [
				self::YES_TAINT & ~self::SQL_TAINT,
				'overall' => self::NO_TAINT
			],
			'\Wikimedia\Rdbms\IMaintainableDatabase::addQuotes' => [
				self::YES_TAINT & ~self::SQL_TAINT,
				'overall' => self::NO_TAINT
			],
			'\Wikimedia\Rdbms\DatabasePostgres::addQuotes' => [
				self::YES_TAINT & ~self::SQL_TAINT,
				'overall' => self::NO_TAINT
			],
			'\Wikimedia\Rdbms\DatabaseSqlite::addQuotes' => [
				self::YES_TAINT & ~self::SQL_TAINT,
				'overall' => self::NO_TAINT
			],
			'\Wikimedia\Rdbms\Database::buildLike' => [
				self::YES_TAINT & ~self::SQL_TAINT,
				self::YES_TAINT & ~self::SQL_TAINT,
				self::YES_TAINT & ~self::SQL_TAINT,
				self::YES_TAINT & ~self::SQL_TAINT,
				self::YES_TAINT & ~self::SQL_TAINT,
				self::YES_TAINT & ~self::SQL_TAINT,
				self::YES_TAINT & ~self::SQL_TAINT,
				self::YES_TAINT & ~self::SQL_TAINT,
				self::YES_TAINT & ~self::SQL_TAINT,
				self::YES_TAINT & ~self::SQL_TAINT,
				self::YES_TAINT & ~self::SQL_TAINT,
				self::YES_TAINT & ~self::SQL_TAINT,
				self::YES_TAINT & ~self::SQL_TAINT,
				self::YES_TAINT & ~self::SQL_TAINT,
				self::YES_TAINT & ~self::SQL_TAINT,
				self::YES_TAINT & ~self::SQL_TAINT,
				self::YES_TAINT & ~self::SQL_TAINT,
				self::YES_TAINT & ~self::SQL_TAINT,
				'overall' => self::NO_TAINT
			],
			// makeList is special cased in MWVistor::checkMakeList
			// so simply disable auto-taint detection here.
			'\Wikimedia\Rdbms\IDatabase::makeList' => [
				self::YES_TAINT & ~self::SQL_TAINT,
				self::NO_TAINT,
				'overall' => self::NO_TAINT
			],
			// '\Message::__construct' => self::YES_TAINT,
			// '\wfMessage' => self::YES_TAINT,
			'\Message::plain' => [ 'overall' => self::YES_TAINT ],
			'\Message::text' => [ 'overall' => self::YES_TAINT ],
			'\Message::parseAsBlock' => [ 'overall' => self::ESCAPED_TAINT ],
			'\Message::parse' => [ 'overall' => self::ESCAPED_TAINT ],
			'\Message::__toString' => [ 'overall' => self::ESCAPED_TAINT ],
			'\Message::escaped' => [ 'overall' => self::ESCAPED_TAINT ],
			'\Message::rawParams' => [
				self::HTML_TAINT | self::RAW_PARAM,
				self::HTML_TAINT | self::RAW_PARAM,
				self::HTML_TAINT | self::RAW_PARAM,
				self::HTML_TAINT | self::RAW_PARAM,
				self::HTML_TAINT | self::RAW_PARAM,
				self::HTML_TAINT | self::RAW_PARAM,
				self::HTML_TAINT | self::RAW_PARAM,
				self::HTML_TAINT | self::RAW_PARAM,
				self::HTML_TAINT | self::RAW_PARAM,
				self::HTML_TAINT | self::RAW_PARAM,
				// meh, not sure how right the overall is.
				'overall' => self::HTML_TAINT
			],
			// AddItem should also take care of addGeneral and friends.
			'\StripState::addItem' => [
				self::NO_TAINT, // type
				self::NO_TAINT, // marker
				self::HTML_EXEC_TAINT, // contents
				'overall' => self::NO_TAINT
			],
			// FIXME Doesn't handle array args right.
			'\wfShellExec' => [
				self::SHELL_EXEC_TAINT | self::ARRAY_OK,
				'overall' => self::YES_TAINT
			],
			'\wfShellExecWithStderr' => [
				self::SHELL_EXEC_TAINT | self::ARRAY_OK,
				'overall' => self::YES_TAINT
			],
			'\wfEscapeShellArg' => [
				self::YES_TAINT & ~self::SHELL_TAINT,
				self::YES_TAINT & ~self::SHELL_TAINT,
				self::YES_TAINT & ~self::SHELL_TAINT,
				self::YES_TAINT & ~self::SHELL_TAINT,
				self::YES_TAINT & ~self::SHELL_TAINT,
				self::YES_TAINT & ~self::SHELL_TAINT,
				self::YES_TAINT & ~self::SHELL_TAINT,
				self::YES_TAINT & ~self::SHELL_TAINT,
				self::YES_TAINT & ~self::SHELL_TAINT,
				'overall' => self::NO_TAINT
			],
			'\MediaWiki\Shell\Shell::escape' => [
				self::YES_TAINT & ~self::SHELL_TAINT,
				self::YES_TAINT & ~self::SHELL_TAINT,
				self::YES_TAINT & ~self::SHELL_TAINT,
				self::YES_TAINT & ~self::SHELL_TAINT,
				self::YES_TAINT & ~self::SHELL_TAINT,
				self::YES_TAINT & ~self::SHELL_TAINT,
				self::YES_TAINT & ~self::SHELL_TAINT,
				self::YES_TAINT & ~self::SHELL_TAINT,
				self::YES_TAINT & ~self::SHELL_TAINT,
				'overall' => self::NO_TAINT
			],
			'\MediaWiki\Shell\Command::unsafeParams' => [
				self::SHELL_EXEC_TAINT,
				'overall' => self::NO_TAINT
			],
			'\MediaWiki\Shell\Result::getStdout' => [
				// This is a bit unclear. Most of the time
				// you should probably be escaping the results
				// of a shell command, but not all the time.
				'overall' => self::YES_TAINT
			],
			'\MediaWiki\Shell\Result::getStderr' => [
				// This is a bit unclear. Most of the time
				// you should probably be escaping the results
				// of a shell command, but not all the time.
				'overall' => self::YES_TAINT
			],
			'\Html::rawElement' => [
				self::YES_TAINT,
				self::ESCAPES_HTML,
				self::YES_TAINT,
				'overall' => self::ESCAPED_TAINT
			],
			'\Html::element' => [
				self::YES_TAINT,
				self::ESCAPES_HTML,
				self::ESCAPES_HTML,
				'overall' => self::ESCAPED_TAINT
			],
			'\Xml::tags' => [
				self::YES_TAINT,
				self::ESCAPES_HTML,
				self::YES_TAINT,
				'overall' => self::ESCAPED_TAINT
			],
			'\Xml::element' => [
				self::YES_TAINT,
				self::ESCAPES_HTML,
				self::ESCAPES_HTML,
				'overall' => self::ESCAPED_TAINT
			],
			'\Xml::encodeJsVar' => [
				self::ESCAPES_HTML,
				self::NO_TAINT, /* pretty */
				'overall' => self::NO_TAINT
			],
			'\Xml::encodeJsCall' => [
				self::YES_TAINT, /* func name. unescaped */
				self::ESCAPES_HTML,
				self::NO_TAINT, /* pretty */
				'overall' => self::NO_TAINT
			],
			'\OutputPage::addHeadItem' => [
				self::HTML_EXEC_TAINT,
				'overall' => self::NO_TAINT
			],
			'\OutputPage::addHTML' => [
				self::HTML_EXEC_TAINT,
				'overall' => self::NO_TAINT
			],
			'\OutputPage::prependHTML' => [
				self::HTML_EXEC_TAINT,
				'overall' => self::NO_TAINT
			],
			'\OutputPage::addInlineStyle' => [
				self::HTML_EXEC_TAINT,
				'overall' => self::NO_TAINT,
			],
			'\OutputPage::parse' => [ 'overall' => self::NO_TAINT, ],
			'\Sanitizer::removeHTMLtags' => [
				self::ESCAPES_HTML,
				self::SHELL_EXEC_TAINT, /* attribute callback */
				self::NO_TAINT, /* callback args */
				self::YES_TAINT, /* extra tags */
				self::NO_TAINT, /* remove tags */
				'overall' => self::ESCAPED_TAINT
			],
			'\Sanitizer::escapeHtmlAllowEntities' => [
				( self::YES_TAINT & ~self::HTML_TAINT ),
				'overall' => self::ESCAPED_TAINT
			],
			'\Sanitizer::safeEncodeAttribute' => [
				self::ESCAPES_HTML,
				'overall' => self::ESCAPED_TAINT
			],
			'\Sanitizer::encodeAttribute' => [
				self::ESCAPES_HTML,
				'overall' => self::ESCAPED_TAINT
			],
			'\WebRequest::getGPCVal' => [ 'overall' => self::YES_TAINT, ],
			'\WebRequest::getRawVal' => [ 'overall' => self::YES_TAINT, ],
			'\WebRequest::getVal' => [ 'overall' => self::YES_TAINT, ],
			'\WebRequest::getArray' => [ 'overall' => self::YES_TAINT, ],
			'\WebRequest::getIntArray' => [ 'overall' => self::NO_TAINT, ],
			'\WebRequest::getInt' => [ 'overall' => self::NO_TAINT, ],
			'\WebRequest::getIntOrNull' => [ 'overall' => self::NO_TAINT, ],
			'\WebRequest::getFloat' => [ 'overall' => self::NO_TAINT, ],
			'\WebRequest::getBool' => [ 'overall' => self::NO_TAINT, ],
			'\WebRequest::getFuzzyBool' => [ 'overall' => self::NO_TAINT, ],
			'\WebRequest::getCheck' => [ 'overall' => self::NO_TAINT, ],
			'\WebRequest::getText' => [ 'overall' => self::YES_TAINT, ],
			'\WebRequest::getValues' => [ 'overall' => self::YES_TAINT, ],
			'\WebRequest::getValueNames' => [ 'overall' => self::YES_TAINT, ],
			'\WebRequest::getQueryValues' => [ 'overall' => self::YES_TAINT, ],
			'\WebRequest::getRawQueryString' => [ 'overall' => self::YES_TAINT, ],
			'\WebRequest::getRawPostString' => [ 'overall' => self::YES_TAINT, ],
			'\WebRequest::getRawInput' => [ 'overall' => self::YES_TAINT, ],
			'\WebRequest::getCookie' => [ 'overall' => self::YES_TAINT, ],
			'\WebRequest::getGlobalRequestURL' => [ 'overall' => self::YES_TAINT, ],
			'\WebRequest::getRequestURL' => [ 'overall' => self::YES_TAINT, ],
			'\WebRequest::getFullRequestURL' => [ 'overall' => self::YES_TAINT, ],
			'\WebRequest::getAllHeaders' => [ 'overall' => self::YES_TAINT, ],
			'\WebRequest::getHeader' => [ 'overall' => self::YES_TAINT, ],
			'\WebRequest::getAcceptLang' => [ 'overall' => self::YES_TAINT, ],
			'\HtmlArmor::__construct' => [
				self::HTML_TAINT | self::RAW_PARAM,
				'overall' => self::NO_TAINT
			],
			// Due to limitations in how we handle list()
			// elements, hard code CommentStore stuff.
			'\CommentStore::insert' => [
				'overall' => self::NO_TAINT
			],
			'\CommentStore::getJoin' => [
				'overall' => self::NO_TAINT
			],
			'\CommentStore::insertWithTempTable' => [
				'overall' => self::NO_TAINT
			],
			// TODO FIXME, Why couldn't it figure out
			// that this is safe on its own?
			// It seems that it has issue with
			// the url query parameters.
			'\Linker::linkKnown' => [
				self::NO_TAINT, /* target */
				self::HTML_TAINT | self::RAW_PARAM, /* raw html text */
				// The array keys for this aren't escaped (!)
				self::NO_TAINT, /* customAttribs */
				self::NO_TAINT, /* query */
				self::NO_TAINT, /* options. All are safe */
				'overall' => self::ESCAPED_TAINT
			],
			'\MediaWiki\Linker\LinkRenderer::buildAElement' => [
				self::NO_TAINT, /* target */
				self::ESCAPES_HTML, /* text (using HtmlArmor) */
				// The array keys for this aren't escaped (!)
				self::NO_TAINT, /* attribs */
				self::NO_TAINT, /* known */
				'overall' => self::ESCAPED_TAINT
			],
			'\MediaWiki\Linker\LinkRenderer::makeLink' => [
				self::NO_TAINT, /* target */
				self::ESCAPES_HTML, /* text (using HtmlArmor) */
				// The array keys for this aren't escaped (!)
				self::NO_TAINT, /* attribs */
				self::NO_TAINT, /* query */
				'overall' => self::ESCAPED_TAINT
			],
			'\MediaWiki\Linker\LinkRenderer::makeKnownLink' => [
				self::NO_TAINT, /* target */
				self::ESCAPES_HTML, /* text (using HtmlArmor) */
				// The array keys for this aren't escaped (!)
				self::NO_TAINT, /* attribs */
				self::NO_TAINT, /* query */
				'overall' => self::ESCAPED_TAINT
			],
			'\MediaWiki\Linker\LinkRenderer::makePreloadedLink' => [
				self::NO_TAINT, /* target */
				self::ESCAPES_HTML, /* text (using HtmlArmor) */
				// The array keys for this aren't escaped (!)
				self::NO_TAINT, /* attribs */
				self::NO_TAINT, /* query */
				'overall' => self::ESCAPED_TAINT
			],
		];
	}

	/**
	 * Add a hook implementation to our list.
	 *
	 * This also handles parser hooks which aren't normal hooks.
	 * Non-normal hooks start their name with a "!"
	 *
	 * @param string $hookName Name of hook
	 * @param FullyQualifiedFunctionLikeName $fqsen The implementing method
	 * @return bool true if already registered, false otherwise
	 */
	public function registerHook( string $hookName, FullyQualifiedFunctionLikeName $fqsen ) {
		if ( !isset( $this->hookSubscribers[$hookName] ) ) {
			$this->hookSubscribers[$hookName] = [];
		}
		foreach ( $this->hookSubscribers[$hookName] as $subscribe ) {
			if ( (string)$subscribe === (string)$fqsen ) {
				// dupe
				return true;
			}
		}
		$this->hookSubscribers[$hookName][] = $fqsen;
		return false;
	}

	/**
	 * Register hooks from extension.json/skin.json
	 *
	 * Assumes extension.json/skin.json is in project root directory
	 * unless SECURITY_CHECK_EXT_PATH is set
	 */
	protected function loadExtensionJson() {
		static $done;
		if ( $done ) {
			return;
		}
		$done = true;
		foreach ( [ 'extension.json', 'skin.json' ] as $filename ) {
			$envPath = getenv( 'SECURITY_CHECK_EXT_PATH' );
			if ( $envPath ) {
				$jsonPath = $envPath . '/' . $filename;
			} else {
				$jsonPath = Config::projectPath( $filename );
			}
			if ( file_exists( $jsonPath ) ) {
				$json = json_decode( file_get_contents( $jsonPath ), true );
				if ( !is_array( $json ) ) {
					continue;
				}
				if ( isset( $json['Hooks'] ) && is_array( $json['Hooks'] ) ) {
					foreach ( $json['Hooks'] as $hookName => $cbList ) {
						foreach ( (array)$cbList as $cb ) {
							// All callbacks here are simple
							// "someFunction" or "Class::SomeMethod"
							if ( strpos( $cb, '::' ) === false ) {
								$callback = FQSENFunc::fromFullyQualifiedString(
									$cb
								);
							} else {
								$callback = FQSENMethod::fromFullyQualifiedString(
									$cb
								);
							}
							$this->registerHook( $hookName, $callback );
						}
					}
				}
			}
		}
	}

	/**
	 * Get a list of subscribers for hook
	 *
	 * @param string $hookName Hook in question. Hooks starting with ! are special.
	 * @return FullyQualifiedFunctionLikeName[]
	 */
	public function getHookSubscribers( string $hookName ) : array {
		$this->loadExtensionJson();
		if ( isset( $this->hookSubscribers[$hookName] ) ) {
			return $this->hookSubscribers[$hookName];
		}
		return [];
	}

	/**
	 * Is a particular function implementing a special hook.
	 *
	 * @note This assumes that any given func will only implement
	 *   one hook
	 * @param FullyQualifiedFunctionLikeName $fqsen The function to check
	 * @return string|null The hook it is implementing or null if no hook
	 */
	public function isSpecialHookSubscriber( FullyQualifiedFunctionLikeName $fqsen ) {
		$this->loadExtensionJson();
		$specialHooks = [
			'!ParserFunctionHook',
			'!ParserHook'
		];

		// @todo This is probably not the most efficient thing.
		foreach ( $specialHooks as $hook ) {
			if ( !isset( $this->hookSubscribers[$hook] ) ) {
				continue;
			}
			foreach ( $this->hookSubscribers[$hook] as $implFQSEN ) {
				if ( (string)$implFQSEN === (string)$fqsen ) {
					return $hook;
				}
			}
		}
		return null;
	}

	/**
	 * Mark XSS's that happen in a Maintenance subclass as false a positive
	 *
	 * @param int $lhsTaint The dangerous taints to be output (e.g. LHS of assignment)
	 * @param int $rhsTaint The taint of the expression
	 * @param string &$msg The issue description
	 * @param Context $context
	 * @param CodeBase $code_base
	 * @return bool Is this a false positive?
	 */
	public function isFalsePositive(
		int $lhsTaint,
		int $rhsTaint,
		string &$msg,
		Context $context,
		CodeBase $code_base
	) : bool {
		if (
			( $lhsTaint & $rhsTaint ) === self::HTML_TAINT
		) {
			if (
				strpos( $context->getFile(), "maintenance/" ) === 0 ||
				strpos( $context->getFile(), "./maintenance/" ) === 0
			) {
				// For classes not using Maintenance subclasses
				$msg = ' [Likely false positive because in maintenance'
					. ' subdirectory, thus probably CLI]';
				return true;
			}
			if ( !$context->isInClassScope() ) {
				return false;
			}
			$class = $context->getClassInScope( $code_base );
			$maintFQSEN = FullyQualifiedClassName::fromFullyQualifiedString(
				'\\Maintenance'
			);
			if ( !$code_base->hasClassWithFQSEN( $maintFQSEN ) ) {
				return false;
			}
			$maint = $code_base->getClassByFQSEN( $maintFQSEN );
			$isMaint = $class->isSubclassOf( $code_base, $maint );
			if ( $isMaint ) {
				$msg .= ' [Likely false positive because in a subclass ' .
					'of Maintenance, thus probably CLI]';
				return true;
			}
		}
		return false;
	}

}

return new MediaWikiSecurityCheckPlugin;
