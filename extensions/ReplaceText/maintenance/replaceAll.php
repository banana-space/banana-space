#!/usr/bin/php
<?php
/**
 * Replace text in pages or page titles.
 *
 * Copyright © 2014 NicheWork, LLC
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @category Maintenance
 * @package  ReplaceText
 * @author   Mark A. Hershberger <mah@nichework.com>
 * @license  GPL-2.0-or-later
 * @link     https://www.mediawiki.org/wiki/Extension:Replace_Text
 *
 */
// @codingStandardsIgnoreStart
$IP = getenv( "MW_INSTALL_PATH" ) ?: __DIR__ . "/../../..";
if ( !is_readable( "$IP/maintenance/Maintenance.php" ) ) {
	die( "MW_INSTALL_PATH needs to be set to your MediaWiki installation.\n" );
}
require_once ( "$IP/maintenance/Maintenance.php" );
// @codingStandardsIgnoreEnd

/**
 * Maintenance script that replaces text in pages
 *
 * @ingroup Maintenance
 * @SuppressWarnings(StaticAccess)
 * @SuppressWarnings(LongVariable)
 */
class ReplaceAll extends Maintenance {
	private $user;
	private $target;
	private $replacement;
	private $namespaces;
	private $category;
	private $prefix;
	private $useRegex;
	private $titles;
	private $defaultContinue;
	private $doAnnounce;
	private $rename;

	public function __construct() {
		parent::__construct();
		$this->addDescription( "CLI utility to replace text wherever it is " .
			"found in the wiki." );

		$this->addArg( "target", "Target text to find.", false );
		$this->addArg( "replace", "Text to replace.", false );

		$this->addOption( "dry-run", "Only find the texts, don't replace.",
			false, false, 'n' );
		$this->addOption( "regex", "This is a regex (false).",
			false, false, 'r' );
		$this->addOption( "user", "The user to attribute this to (uid 1).",
			false, true, 'u' );
		$this->addOption( "yes", "Skip all prompts with an assumed 'yes'.",
			false, false, 'y' );
		$this->addOption( "summary", "Alternate edit summary. (%r is where to " .
			" place the replacement text, %f the text to look for.)",
			false, true, 's' );
		$this->addOption( "nsall", "Search all canonical namespaces (false). " .
			"If true, this option overrides the ns option.", false, false, 'a' );
		$this->addOption( "ns", "Comma separated namespaces to search in " .
			"(Main) .", false, true );
		$this->addOption( "replacements", "File containing the list of " .
			"replacements to be made.  Fields in the file are tab-separated. " .
			"See --show-file-format for more information.", false, true, "f" );
		$this->addOption( "show-file-format", "Show a description of the " .
			"file format to use with --replacements.", false, false );
		$this->addOption( "no-announce", "Do not announce edits on Special:RecentChanges or " .
			"watchlists.", false, false, "m" );
		$this->addOption( "debug", "Display replacements being made.", false, false );
		$this->addOption( "listns", "List out the namespaces on this wiki.",
			false, false );
		$this->addOption( 'rename', "Rename page titles instead of replacing contents.",
			false, false );

		// MW 1.28
		if ( method_exists( $this, 'requireExtension' ) ) {
			$this->requireExtension( 'Replace Text' );
		}
	}

	private function getUser() {
		$userReplacing = $this->getOption( "user", 1 );

		$user = is_numeric( $userReplacing ) ?
			User::newFromId( $userReplacing ) :
			User::newFromName( $userReplacing );

		if ( get_class( $user ) !== 'User' ) {
			$this->fatalError(
				"Couldn't translate '$userReplacing' to a user."
			);
		}

		return $user;
	}

	private function getTarget() {
		$ret = $this->getArg( 0 );
		if ( !$ret ) {
			$this->fatalError( "You have to specify a target." );
		}
		return [ $ret ];
	}

	private function getReplacement() {
		$ret = $this->getArg( 1 );
		if ( !$ret ) {
			$this->fatalError( "You have to specify replacement text." );
		}
		return [ $ret ];
	}

	private function getReplacements() {
		$file = $this->getOption( "replacements" );
		if ( !$file ) {
			return false;
		}

		if ( !is_readable( $file ) ) {
			throw new MWException( "File does not exist or is not readable: "
				. "$file\n" );
		}

		$handle = fopen( $file, "r" );
		if ( $handle === false ) {
			throw new MWException( "Trouble opening file: $file\n" );
			return false;
		}

		$this->defaultContinue = true;
		// @codingStandardsIgnoreStart
		while ( ( $line = fgets( $handle ) ) !== false ) {
		// @codingStandardsIgnoreEnd
			$field = explode( "\t", substr( $line, 0, -1 ) );
			if ( !isset( $field[1] ) ) {
				continue;
			}

			$this->target[] = $field[0];
			$this->replacement[] = $field[1];
			$this->useRegex[] = isset( $field[2] ) ? true : false;
		}
		return true;
	}

	private function shouldContinueByDefault() {
		if ( !is_bool( $this->defaultContinue ) ) {
			$this->defaultContinue =
				$this->getOption( "yes" ) ?
				true :
				false;
		}
		return $this->defaultContinue;
	}

	private function getSummary( $target, $replacement ) {
		$msg = wfMessage( 'replacetext_editsummary', $target, $replacement )->
			plain();
		if ( $this->getOption( "summary" ) !== null ) {
			$msg = str_replace( [ '%f', '%r' ],
				[ $this->target, $this->replacement ],
				$this->getOption( "summary" ) );
		}
		return $msg;
	}

	private function listNamespaces() {
		$this->output( "Index\tNamespace\n" );
		$nsList = MWNamespace::getCanonicalNamespaces();
		ksort( $nsList );
		foreach ( $nsList as $int => $val ) {
			if ( $val == "" ) {
				$val = "(main)";
			}
			$this->output( " $int\t$val\n" );
		}
	}

	private function showFileFormat() {
		$text = <<<EOF

The format of the replacements file is tab separated with three fields.
Any line that does not have a tab is ignored and can be considered a comment.

Fields are:

 1. String to search for.
 2. String to replace found text with.
 3. (optional) The presence of this field indicates that the previous two
	are considered a regular expression.

Example:

This is a comment
TARGET	REPLACE
regex(p*)	Count the Ps; \\1	true


EOF;
		$this->output( $text );
	}

	private function getNamespaces() {
		$nsall = $this->getOption( "nsall" );
		$ns = $this->getOption( "ns" );
		if ( !$nsall && !$ns ) {
			$namespaces = [ NS_MAIN ];
		} else {
			$canonical = MWNamespace::getCanonicalNamespaces();
			$canonical[NS_MAIN] = "_";
			$namespaces = array_flip( $canonical );
			if ( !$nsall ) {
				$namespaces = array_map(
					function ( $n ) use ( $canonical, $namespaces ) {
						if ( is_numeric( $n ) ) {
							if ( isset( $canonical[ $n ] ) ) {
								return intval( $n );
							}
						} else {
							if ( isset( $namespaces[ $n ] ) ) {
								return $namespaces[ $n ];
							}
						}
						return null;
					}, explode( ",", $ns ) );
				$namespaces = array_filter(
					$namespaces,
					function ( $val ) {
						return $val !== null;
					} );
			}
		}
		return $namespaces;
	}

	private function getCategory() {
		return null;
	}

	private function getPrefix() {
		return null;
	}

	private function useRegex() {
		return [ $this->getOption( "regex" ) ];
	}

	private function getRename() {
		return $this->hasOption( 'rename' );
	}

	private function listTitles( $titles, $target, $replacement, $regex, $rename ) {
		foreach ( $titles as $title ) {
			if ( $rename ) {
				$newTitle = ReplaceTextSearch::getReplacedTitle( $title, $target, $replacement, $regex );
				// Implicit conversion of objects to strings
				$this->output( "$title	->	$newTitle\n" );
			} else {
				$this->output( "$title\n" );
			}
		}
	}

	private function replaceTitles( $titles, $target, $replacement, $useRegex, $rename ) {
		foreach ( $titles as $title ) {
			$params = [
				'target_str'      => $target,
				'replacement_str' => $replacement,
				'use_regex'       => $useRegex,
				'user_id'         => $this->user->getId(),
				'edit_summary'    => $this->getSummary( $target, $replacement ),
				'doAnnounce'      => $this->doAnnounce
			];

			if ( $rename ) {
				$params[ 'move_page' ] = true;
				$params[ 'create_redirect' ] = false;
				$params[ 'watch_page' ] = false;
			}

			$this->output( "Replacing on $title... " );
			$job = new ReplaceTextJob( $title, $params );
			if ( $job->run() !== true ) {
				$this->error( "Trouble on the page '$title'." );
			}
			$this->output( "done.\n" );
		}
	}

	private function getReply( $question ) {
		$reply = "";
		if ( $this->shouldContinueByDefault() ) {
			return true;
		}
		while ( $reply !== "y" && $reply !== "n" ) {
			$reply = $this->readconsole( "$question (Y/N) " );
			$reply = substr( strtolower( $reply ), 0, 1 );
		}
		return $reply === "y";
	}

	private function localSetup() {
		if ( $this->getOption( "listns" ) ) {
			$this->listNamespaces();
			return false;
		}
		if ( $this->getOption( "show-file-format" ) ) {
			$this->showFileFormat();
			return false;
		}
		$this->user = $this->getUser();
		if ( !$this->getReplacements() ) {
			$this->target = $this->getTarget();
			$this->replacement = $this->getReplacement();
			$this->useRegex = $this->useRegex();
		}
		$this->namespaces = $this->getNamespaces();
		$this->category = $this->getCategory();
		$this->prefix = $this->getPrefix();
		$this->rename = $this->getRename();

		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		global $wgShowExceptionDetails;
		$wgShowExceptionDetails = true;

		$this->doAnnounce = true;
		if ( !$this->localSetup() ) {
			return;
		}

		if ( $this->namespaces === [] ) {
			$this->fatalError( "No matching namespaces." );
		}

		foreach ( array_keys( $this->target ) as $index ) {
			$target = $this->target[$index];
			$replacement = $this->replacement[$index];
			$useRegex = $this->useRegex[$index];

			if ( $this->getOption( "debug" ) ) {
				$this->output( "Replacing '$target' with '$replacement'" );
				if ( $useRegex ) {
					$this->output( " as regular expression." );
				}
				$this->output( "\n" );
			}

			if ( $this->rename ) {
				$res = ReplaceTextSearch::getMatchingTitles(
					$target,
					$this->namespaces,
					$this->category,
					$this->prefix,
					$useRegex
				);
			} else {
				$res = ReplaceTextSearch::doSearchQuery(
					$target,
					$this->namespaces,
					$this->category,
					$this->prefix,
					$useRegex
				);
			}

			$titles = new TitleArrayFromResult( $res );

			if ( count( $titles ) === 0 ) {
				$this->fatalError( 'No targets found to replace.' );
			}

			if ( $this->getOption( "dry-run" ) ) {
				$this->listTitles( $titles, $target, $replacement, $useRegex, $this->rename );
				continue;
			}

			if ( !$this->shouldContinueByDefault() ) {
				$this->listTitles( $titles, $target, $replacement, $useRegex, $this->rename );
				if ( !$this->getReply( 'Replace instances on these pages?' ) ) {
					return;
				}
			}

			$comment = "";
			if ( $this->getOption( "user", null ) === null ) {
				$comment = " (Use --user to override)";
			}
			if ( $this->getOption( "no-announce", false ) ) {
				$this->doAnnounce = false;
			}
			if ( !$this->getReply(
				"Attribute changes to the user '{$this->user}'?$comment"
			) ) {
				return;
			}

			$this->replaceTitles(
				$titles, $target, $replacement, $useRegex, $this->rename
			);
		}
	}
}

$maintClass = "ReplaceAll";
require_once RUN_MAINTENANCE_IF_MAIN;
