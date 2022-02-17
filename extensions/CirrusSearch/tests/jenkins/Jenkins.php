<?php

namespace CirrusSearch\Jenkins;

use DatabaseUpdater;
use Language;
use Title;

/**
 * Sets up configuration required to run the browser tests on Jenkins.
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
 */

// All of this has to be done at setup time so it has the right globals.  No putting
// it in a class or anything.

// Configuration we have to override before installing Cirrus but only if we're using
// Jenkins as a prototype for development.

require_once __DIR__ . '/FullyFeaturedConfig.php';

// Extra Cirrus stuff for Jenkins
$wgAutoloadClasses['CirrusSearch\Jenkins\CleanSetup'] = __DIR__ . '/cleanSetup.php';
$wgAutoloadClasses['CirrusSearch\Jenkins\NukeAllIndexes'] = __DIR__ . '/nukeAllIndexes.php';
$wgHooks['LoadExtensionSchemaUpdates'][] = 'CirrusSearch\Jenkins\Jenkins::installDatabaseUpdatePostActions';
$wgHooks['PageContentLanguage'][] = 'CirrusSearch\Jenkins\Jenkins::setLanguage';

// Dependencies
// Jenkins will automatically load these for us but it makes this file more generally useful
// to require them ourselves.
wfLoadExtension( 'TimedMediaHandler' );
wfLoadExtension( 'PdfHandler' );
wfLoadExtension( 'Cite' );
wfLoadExtension( 'SiteMatrix' );

// Configuration
$wgOggThumbLocation = '/usr/bin/oggThumb';
$wgGroupPermissions['*']['deleterevision'] = true;
$wgFileExtensions[] = 'pdf';
$wgFileExtensions[] = 'svg';
$wgCapitalLinks = false;
$wgUseInstantCommons = true;
$wgEnableUploads = true;
$wgJobTypeConf['default'] = [
	'class' => 'JobQueueRedis',
	'daemonized'  => true,
	'order' => 'fifo',
	'redisServer' => 'localhost',
	'checkDelay' => true,
	'redisConfig' => [
		'password' => null,
	],
];

$wgCiteEnablePopups = true;
$wgExtraNamespaces[760] = 'Mó';

// Extra helpful configuration but not really required
$wgShowExceptionDetails = true;

$wgCirrusSearchLanguageWeight['user'] = 10.0;
$wgCirrusSearchLanguageWeight['wiki'] = 5.0;
$wgCirrusSearchAllowLeadingWildcard = false;
// $wgCirrusSearchInterwikiSources['c'] = 'commonswiki';

// Test only API action to expose freezing/thawing writes to the elasticsearch cluster
$wgAPIModules['cirrus-freeze-writes'] = 'CirrusSearch\Api\FreezeWritesToCluster';
$wgAPIModules['cirrus-suggest-index'] = 'CirrusSearch\Api\SuggestIndex';
// Bring the ElasticWrite backoff down to between 2^-1 and 2^3 seconds during browser tests
$wgCirrusSearchWriteBackoffExponent = -1;
$wgCirrusSearchUseCompletionSuggester = "yes";

class Jenkins {
	/**
	 * Installs maintenance scripts that provide a clean Elasticsearch index for testing.
	 * @param DatabaseUpdater $updater
	 * @return bool true so we let other extensions install more maintenance actions
	 */
	public static function installDatabaseUpdatePostActions( $updater ) {
		$updater->addPostDatabaseUpdateMaintenance( NukeAllIndexes::class );
		$updater->addPostDatabaseUpdateMaintenance( CleanSetup::class );
		return true;
	}

	/**
	 * If the page ends in '/<language code>' then set the page's language to that code.
	 * @param Title $title
	 * @param string|Language|StubUserLang &$pageLang the page content language
	 * @param Language|StubUserLang $wgLang the user language
	 */
	public static function setLanguage( $title, &$pageLang, $wgLang ) {
		$matches = [];
		if ( preg_match( '/\/..$/', $title->getText(), $matches ) ) {
			$pageLang = Language::factory( substr( $matches[0], 1 ) );
		}
	}
}
