<?php
/**
 * @file
 * @author Niklas Laxström
 * @license GPL-2.0-or-later
 */

namespace LocalisationUpdate;

/**
 * Executes the localisation update.
 */
class Updater {
	/**
	 * Whether the path is a pattern and thus we need to use appropriate
	 * code for fetching directories.
	 *
	 * @param string $path Url
	 * @return bool
	 */
	public function isDirectory( $path ) {
		$filename = basename( $path );
		return strpos( $filename, '*' ) !== false;
	}

	/**
	 * Expands repository relative path to full url with the given repository
	 * patterns. Extra variables in $info are used as variables and will be
	 * replaced the pattern.
	 *
	 * @param array $info Component information.
	 * @param array $repos Repository information.
	 * @return string
	 */
	public function expandRemotePath( $info, $repos ) {
		$pattern = $repos[$info['repo']];
		unset( $info['repo'], $info['orig'] );

		// This assumes all other keys are used as variables
		// in the pattern. For example name -> %NAME%.
		$keys = [];
		foreach ( array_keys( $info ) as $key ) {
			$keys[] = '%' . strtoupper( $key ) . '%';
		}

		$values = array_values( $info );
		return str_replace( $keys, $values, $pattern );
	}

	/**
	 * Parses translations from given list of files.
	 *
	 * @param ReaderFactory $readerFactory Factory to construct parsers.
	 * @param array $files List of files with their contents as array values.
	 * @return array List of translations indexed by language code.
	 */
	public function readMessages( ReaderFactory $readerFactory, array $files ) {
		$messages = [];

		foreach ( $files as $filename => $contents ) {
			$reader = $readerFactory->getReader( $filename );
			try {
				$parsed = $reader->parse( $contents );
			} catch ( \Exception $e ) {
				trigger_error( __METHOD__ . ": Unable to parse messages from $filename", E_USER_WARNING );
				continue;
			}

			foreach ( $parsed as $code => $langMessages ) {
				if ( !isset( $messages[$code] ) ) {
					$messages[$code] = [];
				}
				$messages[$code] = array_merge( $messages[$code], $langMessages );
			}

			$c = array_sum( array_map( 'count', $parsed ) );
			// Useful for debugging, maybe create interface to pass this to the script?
			# echo "$filename with " . get_class( $reader ) . " and $c\n";
		}

		return $messages;
	}

	/**
	 * Find new and changed translations in $remote and returns them.
	 *
	 * @param array $origin
	 * @param array $remote
	 * @param array $blacklist Array of message keys to ignore, keys as as array keys.
	 * @return array
	 */
	public function findChangedTranslations( $origin, $remote, $blacklist = [] ) {
		$changed = [];
		foreach ( $remote as $key => $value ) {
			if ( isset( $blacklist[$key] ) ) {
				continue;
			}

			if ( !isset( $origin[$key] ) || $value !== $origin[$key] ) {
				$changed[$key] = $value;
			}
		}
		return $changed;
	}

	/**
	 * Fetches files from given Url pattern.
	 *
	 * @param FetcherFactory $factory Factory to construct fetchers.
	 * @param string $path Url to the file or pattern of files.
	 * @return array List of Urls with file contents as path.
	 */
	public function fetchFiles( FetcherFactory $factory, $path ) {
		$fetcher = $factory->getFetcher( $path );

		if ( $this->isDirectory( $path ) ) {
			$files = $fetcher->fetchDirectory( $path );
		} else {
			$files = [ $path => $fetcher->fetchFile( $path ) ];
		}

		// Remove files which were not found
		return array_filter( $files );
	}

	public function execute(
		Finder $finder,
		ReaderFactory $readerFactory,
		FetcherFactory $fetcherFactory,
		array $repos,
		$logger
	) {
		$components = $finder->getComponents();

		$updatedMessages = [];

		foreach ( $components as $key => $info ) {
			$logger->logInfo( "Updating component $key" );

			$originFiles = $this->fetchFiles( $fetcherFactory, $info['orig'] );
			$remotePath = $this->expandRemotePath( $info, $repos );
			try {
				$remoteFiles = $this->fetchFiles( $fetcherFactory, $remotePath );
			} catch ( \Exception $e ) {
				$logger->logError( __METHOD__ . ": Unable to fetch messages from $remotePath" );
				continue;
			}

			if ( $remoteFiles === [] ) {
				// Small optimization: if nothing to compare with, skip
				continue;
			}

			$originMessages = $this->readMessages( $readerFactory, $originFiles );
			$remoteMessages = $this->readMessages( $readerFactory, $remoteFiles );

			if ( !isset( $remoteMessages['en'] ) ) {
				// Could not find remote messages
				continue;
			}

			// If remote translation in English is not present or differs, we do not want
			// translations for other languages for those messages, as they are either not
			// used in this version of code or can be incompatible.
			$forbiddenKeys = $this->findChangedTranslations(
				$originMessages['en'],
				$remoteMessages['en']
			);

			// We never accept updates for English strings
			unset( $originMessages['en'], $remoteMessages['en'] );

			// message: string in all languages; translation: string in one language.
			foreach ( $remoteMessages as $language => $remoteTranslations ) {
				// Check for completely new languages
				$originTranslations = [];
				if ( isset( $originMessages[$language] ) ) {
					$originTranslations = $originMessages[$language];
				}

				$updatedTranslations = $this->findChangedTranslations(
					$originTranslations,
					$remoteTranslations,
					$forbiddenKeys
				);

				// Avoid empty arrays
				if ( $updatedTranslations === [] ) {
					continue;
				}

				if ( !isset( $updatedMessages[$language] ) ) {
					$updatedMessages[$language] = [];
				}

				// In case of conflicts, which should not exist, this prefers the
				// first translation seen.
				$updatedMessages[$language] += $updatedTranslations;
			}
		}

		return $updatedMessages;
	}
}
